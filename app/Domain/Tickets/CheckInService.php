<?php

namespace App\Domain\Tickets;

use App\Domain\Shared\Actor;
use App\Domain\Shared\DomainError;
use App\Enums\ActivityType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\TicketCheckedIn;
use App\Events\TicketCheckinReset;
use App\Models\Event;
use App\Models\ExpiredQrCode;
use App\Models\Order;
use App\Models\TicketCheckin;
use App\Models\TicketHolder;
use App\Settings\FeatureFlagSettings;
use App\Support\Audit;
use App\Support\Formats;
use App\Support\PhoneNumber;
use App\Support\QatarClock;
use App\Support\Text;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One check-in service for the scanner, manual check-in and the API
 * (R-128…R-136, F-GATE-039…047, D-005, D-030, D-040, D-041, D-102, T-118…T-121).
 * Business refusals are returned (HTTP 200 semantics), never thrown.
 */
class CheckInService
{
    /** @return array<string,mixed> */
    private function refuse(string $code, string $message, ?TicketHolder $h = null, array $extra = []): array
    {
        return ['ok' => false, 'success' => false, 'code' => $code, 'message' => $message, 'ticket' => $h ? $this->ticketInfo($h) : null] + $extra;
    }

    /** Admission eligibility in the mandatory rebuild order (F-GATE-039). */
    public function evaluate(TicketHolder $h): array
    {
        $order = $h->order;
        $event = $order?->event;
        if (! $order || ! $event || $order->trashed()) {
            return $this->refuse('not_found', __('gate.not_found'));
        }
        if ($order->payment_status === PaymentStatus::Cancelled) {
            return $this->refuse('order_cancelled', __('gate.order_cancelled'), $h); // D-005 / T-119
        }
        if ($event->isExpired('checkin')) {
            return $this->refuse('event_expired', __('gate.event_expired', ['date' => Formats::longDate($event->end_date ?? $event->event_date, false)]), $h);
        }
        if (! $event->isToday()) {
            return $this->refuse('wrong_day', __('gate.wrong_day', ['date' => Formats::longDate($event->event_date, false)]), $h);
        }
        if ($h->is_present) {
            return $this->refuse('already_checked_in', __('gate.already_used'), $h, ['already' => true]);
        }
        $collectCash = false;
        if ($order->payment_status !== PaymentStatus::Confirmed) {
            $cashAtVenue = $order->isCashAtVenue() && $order->payment_status === PaymentStatus::Pending;
            if (! ($cashAtVenue && app(FeatureFlagSettings::class)->admit_unpaid_cash_bookings)) {
                return $this->refuse('payment_pending', __('gate.payment_pending'), $h);
            }
            $collectCash = true; // R-129 «تحصيل نقدي»
        }

        return ['ok' => true, 'collect_cash' => $collectCash];
    }

    /**
     * Default mode: admit by scanned ticket code (R-128, T-120).
     *
     * @return array<string,mixed>
     */
    public function admitByCode(string $raw, Actor $actor, ?string $staffLabel = null, string $source = 'camera'): array
    {
        $code = $this->verifiedCode(Text::normalizeScannedCode($raw));
        if ($code === '') {
            return $this->refuse('invalid', __('gate.reference_required'));
        }
        $holder = TicketHolder::query()->where('qr_code', $code)->with(['order.event', 'order.customer'])->first();
        if (! $holder) {
            if ($expired = ExpiredQrCode::query()->where('qr_code', $code)->first()) {
                $this->record(null, null, 'rejected', 'expired_code', $actor, $staffLabel, $code, $source);

                return $this->refuse('code_revoked', __('gate.code_revoked', ['event' => $expired->moved_to_event_title ?? '']));
            }
            // F-GATE-041: legacy order-level codes were migrated to holders (Part 4.9) — no separate path.
            $this->record(null, null, 'rejected', 'not_found', $actor, $staffLabel, $code, $source);

            return $this->refuse('not_found', __('gate.not_found'));
        }

        return $this->admit($holder, $actor, $staffLabel, $source, $code, manual: false);
    }

    /** Manual mode (POS dialog / scanner list), F-GATE-045. */
    public function admitHolder(string $holderId, Actor $actor, ?string $staffLabel = null, string $source = 'manual'): array
    {
        if ($holderId === '') {
            return $this->refuse('invalid', __('gate.ticket_unspecified'));
        }
        $holder = TicketHolder::query()->whereKey($holderId)->with(['order.event', 'order.customer'])->first();
        if (! $holder) {
            return $this->refuse('not_found', __('gate.not_found'));
        }

        return $this->admit($holder, $actor, $staffLabel, $source, (string) $holder->qr_code, manual: true);
    }

    /**
     * Sequential multi-ticket confirm with per-ticket results (F-GATE-027 rebuild).
     *
     * @param  list<string>  $holderIds
     * @return list<array<string,mixed>>
     */
    public function admitMany(array $holderIds, Actor $actor, ?string $staffLabel = null, string $source = 'manual'): array
    {
        return array_map(fn ($id) => ['holder_id' => $id] + $this->admitHolder($id, $actor, $staffLabel, $source), array_values(array_unique($holderIds)));
    }

    private function admit(TicketHolder $holder, Actor $actor, ?string $staffLabel, string $source, string $code, bool $manual): array
    {
        if (! $actor->can('scanner.admit') && ! $actor->can('checkin.manual')) {
            throw DomainError::forbidden();
        }
        $label = $this->label($staffLabel) ?? $actor->displayName();
        $verdict = $this->evaluate($holder);
        if (! $verdict['ok']) {
            if ($manual && $verdict['code'] === 'already_checked_in') {
                $verdict['message'] = __('gate.already_marked');
            }
            $this->record($holder, $holder->order?->event_id, 'rejected', $verdict['code'], $actor, $label, $code, $source);

            return $verdict;
        }

        $now = QatarClock::nowUtc();
        // R-130 atomic single admission: exactly one concurrent scanner wins.
        $affected = TicketHolder::query()->whereKey($holder->id)->where('is_present', false)->update([
            'is_present' => true,
            'checked_in_at' => $now,
            'checked_in_by' => $actor->userId(),
            'checked_in_by_name' => $label,
            'checked_in_device_id' => $actor->deviceId(),
            'updated_at' => $now,
        ]);
        if ($affected !== 1) {
            $holder->refresh();
            $this->record($holder, $holder->order?->event_id, 'rejected', 'already_used', $actor, $label, $code, $source);

            return $this->refuse('already_checked_in', $manual ? __('gate.already_marked') : __('gate.already_used'), $holder, ['already' => true]);
        }

        // R-132 automation marker when no automation reply yet.
        Order::query()->whereKey($holder->order_id)->whereNull('n8n_responded_at')->update([
            'n8n_response_message' => Order::MANUAL_CHECKIN_MARKER,
            'n8n_responded_at' => $now,
            'automation_status' => 'checked_in',
        ]);

        $holder->refresh()->load('order.event');
        $this->record($holder, $holder->order->event_id, 'admit', null, $actor, $label, $code, $source);
        event(new TicketCheckedIn($holder, $holder->order->event_id));
        Audit::log(ActivityType::TicketScan, __('gate.audit_admit', ['name' => $holder->name]), $holder->order, [
            'ticket_holder_id' => $holder->id, 'ticket_holder_name' => $holder->name, 'ticket_type' => $holder->ticket_type->value,
            'qr_code' => $holder->qr_code, 'action' => 'confirm_presence', 'staff' => $label, 'source' => $source,
        ], $actor);

        return [
            'ok' => true, 'success' => true, 'code' => 'admitted',
            'message' => $manual ? __('gate.admitted_manual', ['name' => $holder->name]) : __('gate.admitted', ['name' => $holder->name]),
            'ticket' => $this->ticketInfo($holder, $actor), 'collect_cash' => $verdict['collect_cash'],
            'confirmed_at' => $holder->checked_in_at?->toIso8601String(),
        ];
    }

    /** R-133 admin-only undo. */
    public function reset(string $holderId, Actor $actor, ?string $reason = null): array
    {
        if (! $actor->can('checkin.reset')) {
            return $this->refuse('forbidden', __('gate.reset_admin_only'));
        }
        if ($holderId === '') {
            return $this->refuse('invalid', __('gate.holder_id_required'));
        }
        $holder = TicketHolder::query()->with('order')->find($holderId);
        if (! $holder) {
            return $this->refuse('not_found', __('gate.not_found'));
        }
        $affected = TicketHolder::query()->whereKey($holderId)->where('is_present', true)->update([
            'is_present' => false, 'checked_in_at' => null, 'checked_in_by' => null, 'checked_in_by_name' => null, 'checked_in_device_id' => null, 'updated_at' => now(),
        ]);
        if ($affected !== 1) {
            return $this->refuse('not_scanned', __('gate.not_scanned'));
        }
        $holder->refresh();
        $this->record($holder, $holder->order?->event_id, 'reset', $reason ? mb_substr($reason, 0, 40) : null, $actor, $actor->displayName(), (string) $holder->qr_code, 'manual');
        event(new TicketCheckinReset($holder, (string) $holder->order?->event_id));
        Audit::log(ActivityType::TicketCheckinReset, __('gate.audit_reset', ['name' => $holder->name]), $holder->order, [
            'holder_id' => $holder->id, 'qr_code' => $holder->qr_code, 'holder_name' => $holder->name, 'reason' => $reason,
        ], $actor);

        return ['ok' => true, 'success' => true, 'message' => __('gate.reset_done')];
    }

    // ---- Lookups ------------------------------------------------------------------

    /** F-GATE-043 read-only lookup (not date-limited, proper joins — D-102). */
    public function lookup(string $raw, Actor $actor): array
    {
        $code = Text::normalizeScannedCode($raw);
        if ($code === '') {
            return ['success' => false, 'message' => __('gate.code_required'), 'results' => []];
        }
        $base = fn () => TicketHolder::query()->with(['order.customer', 'order.event'])->whereHas('order');
        $rows = $base()->where('qr_code', $code)->limit(20)->get();
        if ($rows->isEmpty()) {
            $rows = $base()->where('qr_code', 'like', Text::like($code))->limit(20)->get();
        }
        if ($rows->isEmpty()) {
            $rows = $base()->whereHas('order', fn ($q) => $q->where('booking_reference', $code))->reorder()->orderBy('position')->limit(20)->get();
        }

        return ['success' => true, 'results' => $rows->map(fn ($h) => $this->ticketInfo($h, $actor))->values()->all()];
    }

    /** Booking reference → all tickets with admissibility (F-GATE-017 rebuild). */
    public function byReference(string $reference, Actor $actor): array
    {
        $order = Order::query()->where('booking_reference', trim($reference))->with(['holders', 'event', 'customer'])->first();
        if (! $order) {
            return ['success' => false, 'code' => 'not_found', 'message' => __('gate.booking_not_found'), 'results' => []];
        }

        return ['success' => true, 'order' => $order->booking_reference, 'results' => $order->holders->map(fn ($h) => $this->ticketInfo($h->setRelation('order', $order), $actor))->values()->all()];
    }

    /** R-136 phone search at the gate: last-8 match, admissible tickets of today's event(s). */
    public function byPhone(string $phone, Actor $actor): array
    {
        $digits = PhoneNumber::digits($phone);
        if (strlen($digits) < 8) {
            return ['success' => false, 'code' => 'phone_short', 'message' => __('gate.phone_short'), 'results' => []];
        }
        $todayEvents = Event::query()->onQatarDate(QatarClock::today())->pluck('id');
        $rows = TicketHolder::query()
            ->with(['order.customer', 'order.event'])
            ->where('phone_e164', 'like', '%'.substr($digits, -8))
            ->whereHas('order', fn ($q) => $q->whereIn('event_id', $todayEvents)->where('payment_status', '!=', 'cancelled'))
            ->orderBy('order_id')->orderBy('position')
            ->limit(60)->get()
            ->filter(fn ($h) => $this->evaluate($h)['ok'] || $h->is_present);

        return ['success' => true, 'results' => $rows->map(fn ($h) => $this->ticketInfo($h, $actor))->values()->all()];
    }

    /**
     * F-GATE-044 search (≥ 2 chars) for today + future events, merged by holder id.
     */
    public function search(string $value, Actor $actor, int $limit = 40): array
    {
        $value = trim($value);
        if (mb_strlen($value) < 2) {
            return ['success' => true, 'results' => []];
        }
        $like = Text::like($value);
        [$todayStart] = QatarClock::dayRange(QatarClock::today());
        $eventScope = fn ($q) => $q->where(fn ($w) => $w->where('event_date', '>=', $todayStart)->orWhere('end_date', '>=', $todayStart));
        $base = fn () => TicketHolder::query()->with(['order.customer', 'order.event'])->whereHas('order.event', $eventScope);

        $a = $base()->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('id_number', 'like', $like))->latest()->limit($limit)->get();
        $b = $base()->whereHas('order', fn ($q) => $q->where('booking_reference', 'like', $like))->latest()->limit($limit)->get();
        $c = $base()->whereHas('order.customer', fn ($q) => $q->where('name', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('email', 'like', $like))->latest()->limit($limit)->get();

        $rows = $a->concat($b)->concat($c)->unique('id')->values();

        return ['success' => true, 'results' => $rows->map(fn ($h) => $this->ticketInfo($h, $actor))->all()];
    }

    /** F-GATE-042 history: last N admissions (all events, optionally one event). */
    public function history(?string $eventId = null, int $limit = 30): Collection
    {
        return TicketHolder::query()
            ->where('is_present', true)->whereNotNull('checked_in_at')
            ->when($eventId, fn ($q) => $q->whereHas('order', fn ($o) => $o->where('event_id', $eventId)))
            ->with(['order:id,booking_reference,event_id', 'order.event:id,title'])
            ->orderByDesc('checked_in_at')
            ->limit($limit)->get();
    }

    /** Related tickets of the same phone (F-GATE-031/032), grouped by Qatar date. */
    public function related(TicketHolder $holder, Actor $actor): array
    {
        $digits = PhoneNumber::digits($holder->phone_e164 ?: $holder->phone);
        if (strlen($digits) < 8) {
            return ['same_day' => [], 'other_days' => []];
        }
        $holder->loadMissing('order.event');
        $rows = TicketHolder::query()->with(['order.event', 'order.customer'])
            ->where('phone_e164', 'like', '%'.substr($digits, -8))
            ->where('id', '!=', $holder->id)
            ->whereHas('order', fn ($q) => $q->where('payment_status', '!=', 'cancelled'))
            ->limit(80)->get();
        $today = QatarClock::today();
        $same = [];
        $other = [];
        foreach ($rows as $r) {
            $info = $this->ticketInfo($r, $actor);
            if ($r->order?->event?->isToday($today)) {
                $same[] = $info;
            } else {
                $other[$r->order?->event ? $r->order->event->firstDay() : 'unknown'][] = $info;
            }
        }
        ksort($other);

        return ['same_day' => $same, 'other_days' => $other];
    }

    // ---- Helpers ------------------------------------------------------------------

    /** @return array<string,mixed> */
    public function ticketInfo(TicketHolder $h, ?Actor $actor = null): array
    {
        $o = $h->order;
        $e = $o?->event;
        $verdict = $o && $e ? $this->evaluate($h) : ['ok' => false, 'code' => 'not_found'];
        $info = [
            'holder_id' => $h->id,
            'booking_reference' => $o?->booking_reference,
            'ticket_code' => $h->qr_code,
            'customer_name' => $o?->customer?->name ?? __('common.unknown'),
            'customer_phone' => $o?->customer ? $o->customer->fullPhone() : null,
            'customer_email' => $o?->customer?->email,
            'event_title' => $e?->title,
            'event_date' => $e?->event_date?->toIso8601String(),
            'event_date_label' => $e ? Formats::longDate($e->event_date, false) : null,
            'is_today' => $e?->isToday() ?? false,
            'ticket_type' => $h->ticket_type->value,
            'ticket_type_label' => $h->ticket_type->label(),
            'ticket_holder_name' => $h->name,
            'ticket_holder_phone' => $h->displayPhone(),
            'ticket_holder_nationality' => $h->nationality,
            'payment_status' => $o?->payment_status->value,
            'payment_method' => $o?->payment_method->value,
            'is_present' => $h->is_present,
            'confirmed_at' => $h->checked_in_at?->toIso8601String(),
            'confirmed_at_label' => $h->checked_in_at ? Formats::longDateTime($h->checked_in_at) : null,
            'confirmed_by_name' => $h->checked_in_by_name,
            'admissible' => (bool) ($verdict['ok'] ?? false),
            'refusal' => $verdict['ok'] ? null : ($verdict['code'] ?? null),
            'refusal_message' => $verdict['ok'] ? null : ($verdict['message'] ?? null),
            'collect_cash' => (bool) ($verdict['collect_cash'] ?? false),
            'quantity' => 1,
        ];
        if ($actor?->canSeePii()) {
            $info['ticket_holder_id_number'] = $h->getAttribute('id_number');
        }

        return $info;
    }

    private function label(?string $staffLabel): ?string
    {
        $l = Text::clean($staffLabel);

        return $l === '' ? null : mb_substr($l, 0, (int) config('qcamelmc.gate.staff_label_max', 80));
    }

    /** Tier A9: accept "{code}.{hmac8}" when HMAC is enabled, plus legacy plain codes. */
    private function verifiedCode(string $code): string
    {
        if (preg_match('/^(.+)\.([0-9a-f]{8})$/i', $code, $m)) {
            $key = (string) (config('qcamelmc.qr.hmac_key') ?: config('app.key'));
            if (hash_equals(substr(hash_hmac('sha256', $m[1], $key), 0, 8), strtolower($m[2]))) {
                return $m[1];
            }

            return ''; // forged suffix
        }

        return $code;
    }

    public static function signedPayload(string $code): string
    {
        if (! config('qcamelmc.gate.qr_hmac_enabled')) {
            return $code;
        }
        $key = (string) (config('qcamelmc.qr.hmac_key') ?: config('app.key'));

        return $code.'.'.substr(hash_hmac('sha256', $code, $key), 0, 8);
    }

    private function record(?TicketHolder $h, ?string $eventId, string $action, ?string $reason, Actor $actor, ?string $label, ?string $code, string $source): void
    {
        try {
            TicketCheckin::create([
                'ticket_holder_id' => $h?->id, 'event_id' => $eventId, 'action' => $action, 'reason' => $reason,
                'actor_id' => $actor->userId(), 'actor_label' => $label, 'kiosk_device_id' => $actor->deviceId(),
                'scanned_code' => $code ? mb_substr($code, 0, 100) : null, 'source' => mb_substr($source, 0, 12),
            ]);
        } catch (\Throwable) {
            // history is best effort
        }
    }

    /** Search activity log (F-GATE-011, server-side). */
    public function logSearch(string $query, string $type, Actor $actor): void
    {
        Audit::log(ActivityType::QrSearch, __('gate.audit_search'), null, ['search_query' => mb_substr($query, 0, 100), 'search_type' => $type], $actor);
    }

    /** F-GATE-012 input classification. */
    public static function classify(string $input): string
    {
        $input = trim($input);
        if (preg_match('/^[\d\s+\-()]+$/', PhoneNumber::normalizeDigits($input))) {
            return 'phone';
        }

        return str_contains(strtoupper($input), '-TKT') ? 'ticket_code' : 'booking_reference';
    }

    public static function isCashPos(Order $o): bool
    {
        return $o->payment_method === PaymentMethod::CashPos;
    }

    /** Unused helper kept for DB-level debugging. */
    public static function presentCount(string $eventId): int
    {
        return (int) DB::table('ticket_holders as h')->join('orders as o', 'o.id', '=', 'h.order_id')->where('o.event_id', $eventId)->where('h.is_present', true)->count();
    }
}
