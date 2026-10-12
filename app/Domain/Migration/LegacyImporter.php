<?php

namespace App\Domain\Migration;

use App\Domain\Booking\Services\StockLedger;
use App\Enums\Role;
use App\Models\User;
use App\Settings\AutomationSettings;
use App\Settings\BrandingSettings;
use App\Settings\ContactSettings;
use App\Settings\EventSettings;
use App\Settings\FeatureFlagSettings;
use App\Settings\SadadSettings;
use App\Support\Money;
use App\Support\PhoneNumber;
use App\Support\QatarClock;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Throwable;

/**
 * Part 4.9 — one-off migration of the production Supabase data into the new
 * schema. Reads a legacy connection, writes with the query builder (no model
 * events, no stock side effects), preserves UUIDs, upserts by id so every step
 * is idempotent, applies legacy fixes F-01…F-14, then recounts stock.
 */
class LegacyImporter
{
    public const STEPS = ['users', 'events', 'ticket_types', 'pos_users', 'customers', 'orders', 'holders', 'deleted_tickets',
        'expired_qr_codes', 'payment_events', 'payment_errors', 'email_events', 'operations', 'fixes', 'recount', 'settings'];

    private const HOLD_MINUTES = 30;

    /** F-12: test rows that must no longer exist in production. */
    private const TEST_EVENT_PREFIXES = ['11111111', '22222222'];

    private const TEST_EMAILS = ['salem@test.qa', 'pos@test.qa', 'x@test.qa', 'y@test.qa', 'z@test.qa'];

    /** @var array<string, array{source:int, imported:int, skipped:int}> */
    public array $counts = [];

    /** @var list<string> */
    public array $warnings = [];

    /** Σ total_amount of confirmed legacy orders, for the reconciliation report. */
    private ?string $legacyRevenue = null;

    /** @var list<string> */
    public array $fixes = [];

    /** @var array<string, array<string, array{price:string, id:string}>> event_id → type → ticket type */
    private array $types = [];

    /** @var array<string, list<array{type:string, unit:string, qty:int}>> order_id → derived lines */
    private array $lines = [];

    /** @var array<string, true> */
    private array $userIds = [];

    private CarbonImmutable $now;

    /**
     * @param  array{auth_users?:string, include_secrets?:bool}  $options
     */
    public function __construct(
        private readonly ConnectionInterface|SupabaseRestSource $legacy,
        private readonly array $options = [],
        private readonly ?\Closure $log = null,
    ) {
        $this->now = CarbonImmutable::now('UTC');
    }

    /**
     * @param  list<string>|null  $only  steps to run (default: all, in order)
     */
    public function run(bool $dryRun = false, ?array $only = null): self
    {
        $steps = $only ? array_values(array_intersect(self::STEPS, $only)) : self::STEPS;
        DB::beginTransaction();
        try {
            foreach ($steps as $step) {
                $this->say("→ {$step}");
                $this->{'step'.Str::studly($step)}();
            }
            $this->assertTestDataGone();
            $dryRun ? DB::rollBack() : DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $this;
    }

    // ------------------------------------------------------------------ steps

    /** Step 1 — auth users + admin_users + user_roles → users + roles (T-110, D-001). */
    private function stepUsers(): void
    {
        $authTable = $this->options['auth_users'] ?? 'auth.users';
        $admins = $this->rows('admin_users')->pluck('email')->map(fn ($e) => mb_strtolower((string) $e))->flip();
        $roles = $this->rows('user_roles')->groupBy('user_id');
        $rows = $this->rows($authTable);

        foreach ($rows as $u) {
            $email = mb_strtolower(trim((string) ($u['email'] ?? '')));
            if ($email === '') {
                $this->skip('users');

                continue;
            }
            $legacyRoles = collect($roles[$u['id']] ?? [])->pluck('role')->map(fn ($r) => (string) $r);
            $role = $admins->has($email) || $legacyRoles->contains('admin') ? Role::Admin : ($legacyRoles->contains('moderator') ? Role::Supervisor : null);
            $hash = (string) ($u['encrypted_password'] ?? '');
            $noHash = $hash === ''; // REST/Auth API never exposes hashes → password reset required
            $hash = str_starts_with($hash, '$2a$') ? '$2y$'.substr($hash, 4) : ($hash !== '' ? $hash : bcrypt(Str::random(40)));

            DB::table('users')->upsert([[
                'id' => $u['id'],
                'name' => mb_substr((string) (data_get($this->json($u['raw_user_meta_data'] ?? null), 'name') ?: strstr($email, '@', true)), 0, 100),
                'email' => $email,
                'email_verified_at' => $this->ts($u['email_confirmed_at'] ?? null) ?? $this->ts($u['created_at'] ?? null),
                'password' => $hash,
                'is_active' => $role !== null,
                'must_reset_password' => $role === Role::Admin || $noHash, // D-001 forced rotation for admins
                'created_at' => $this->ts($u['created_at'] ?? null) ?? $this->now,
                'updated_at' => $this->now,
            ]], ['id'], ['name', 'email', 'email_verified_at', 'password', 'is_active', 'must_reset_password', 'updated_at']);

            $user = User::query()->find($u['id']);
            $user?->syncRoles($role ? [$role->value] : []);
            $this->userIds[$u['id']] = true;
            $this->count('users', imported: 1);
        }
        $this->count('users', source: $rows->count());
    }

    /** Step 2 — events (is_active NULL → true, cut-offs default 18:00). */
    private function stepEvents(): void
    {
        $rows = $this->rows('events');
        foreach ($rows->chunk(200) as $chunk) {
            DB::table('events')->upsert($chunk->map(fn ($e) => [
                'id' => $e['id'],
                'title' => mb_substr((string) $e['title'], 0, 200),
                'description' => $e['description'] ?? null,
                'event_date' => $this->ts($e['event_date']),
                'end_date' => $this->ts($e['end_date'] ?? null),
                'start_time' => $this->time($e['start_time'] ?? null),
                'end_time' => $this->time($e['end_time'] ?? null),
                'location' => mb_substr((string) ($e['location'] ?? ''), 0, 200),
                'image_url' => $e['image_url'] ?? null,
                'video_url' => $e['video_url'] ?? null,
                'is_active' => ($e['is_active'] ?? null) === null ? true : (bool) $e['is_active'],
                'is_archived' => (bool) ($e['is_archived'] ?? false),
                'archived_at' => $this->ts($e['archived_at'] ?? null),
                'display_order' => (int) ($e['display_order'] ?? 0),
                'sales_cutoff_time' => '18:00:00',
                'checkin_cutoff_time' => '18:00:00',
                'created_at' => $this->ts($e['created_at'] ?? null) ?? $this->now,
                'updated_at' => $this->ts($e['updated_at'] ?? null) ?? $this->now,
            ])->values()->all(), ['id']);
        }
        $this->count('events', source: $rows->count(), imported: $rows->count());
    }

    /** Step 3 — tickets → ticket_types (sold_quantity recomputed in `recount`). */
    private function stepTicketTypes(): void
    {
        $rows = $this->rows('tickets');
        $valid = $rows->filter(fn ($t) => in_array($t['type'], ['vip', 'normal', 'parking'], true));
        foreach ($rows->diffKeys($valid) as $t) {
            $this->warn("ticket type {$t['id']} has unknown type '{$t['type']}' — skipped (F-10)");
        }
        DB::table('ticket_types')->upsert($valid->map(fn ($t) => [
            'id' => $t['id'], 'event_id' => $t['event_id'], 'type' => $t['type'],
            'price' => Money::of((string) $t['price'])->toDecimal(),
            'available_quantity' => (int) $t['available_quantity'], 'sold_quantity' => (int) ($t['sold_quantity'] ?? 0),
            'description' => $t['description'] ?? null,
            'created_at' => $this->ts($t['created_at'] ?? null) ?? $this->now, 'updated_at' => $this->now,
        ])->values()->all(), ['id']);
        $this->count('ticket_types', source: $rows->count(), imported: $valid->count(), skipped: $rows->count() - $valid->count());
    }

    private function stepPosUsers(): void
    {
        $rows = $this->rows('pos_users');
        DB::table('pos_users')->upsert($rows->map(fn ($p) => [
            'id' => $p['id'], 'name' => mb_substr((string) $p['name'], 0, 80), 'icon' => $p['icon'] ?? '⭐',
            'is_active' => ($p['is_active'] ?? null) === null ? true : (bool) $p['is_active'],
            'created_at' => $this->ts($p['created_at'] ?? null) ?? $this->now, 'updated_at' => $this->now,
        ])->values()->all(), ['id']);
        $this->count('pos_users', source: $rows->count(), imported: $rows->count());
    }

    /** Step 5 — customers (+ phone_e164, person_key; F-07, F-14). */
    private function stepCustomers(): void
    {
        $rows = $this->rows('customers');
        foreach ($rows->chunk(500) as $chunk) {
            DB::table('customers')->upsert($chunk->map(function ($c) {
                [$cc, $phone] = $this->splitPhone((string) ($c['phone'] ?? ''), $c['country_code'] ?? null);

                return [
                    'id' => $c['id'], 'name' => mb_substr((string) $c['name'], 0, 100), 'email' => (string) ($c['email'] ?? ''),
                    'phone' => $phone, 'country_code' => $cc, 'phone_e164' => PhoneNumber::e164($phone, $cc),
                    'nationality' => $c['nationality'] ?? null, 'id_number' => $c['id_number'] ?? null,
                    'person_key' => PhoneNumber::personKey($c['id_number'] ?? null, $phone),
                    'created_at' => $this->ts($c['created_at'] ?? null) ?? $this->now, 'updated_at' => $this->now,
                ];
            })->values()->all(), ['id']);
        }
        $this->count('customers', source: $rows->count(), imported: $rows->count());
    }

    /** Step 6 + 7 — orders and derived order_items (F-04, F-08). */
    private function stepOrders(): void
    {
        $this->loadTypes();
        $rows = $this->rows('orders');
        $holders = $this->rows('ticket_holders')->groupBy('order_id');

        // F-04: a payment id may appear on several orders; keep it on the confirmed / earliest one.
        $keepPaymentOn = $rows->filter(fn ($o) => ! empty($o['payment_id']))->groupBy('payment_id')
            ->map(fn (Collection $g) => $g->sortBy([fn ($a, $b) => ($b['payment_status'] === 'confirmed') <=> ($a['payment_status'] === 'confirmed'), fn ($a, $b) => strcmp((string) $a['created_at'], (string) $b['created_at'])])->first()['id']);

        $this->legacyRevenue = $rows->filter(fn (array $o): bool => ($o['payment_status'] ?? null) === 'confirmed')
            ->reduce(fn (Money $sum, array $o): Money => $sum->add(Money::of((string) $o['total_amount'])), Money::zero())->toDecimal();

        foreach ($rows as $o) {
            $created = $this->ts($o['created_at'] ?? null) ?? $this->ts($o['confirmed_at'] ?? null) ?? $this->ts($o['paid_at'] ?? null) ?? $this->now; // F-08
            if (($o['created_at'] ?? null) === null) {
                $this->fix("F-08 order {$o['booking_reference']} had no created_at");
            }
            $status = $o['payment_status'] ?? null;
            if ($status === null) {
                $status = 'pending';
                $this->fix("F-08 order {$o['booking_reference']} had no payment_status → pending");
            }
            $paymentId = $o['payment_id'] ?? null;
            $note = $o['payment_note'] ?? null;
            if ($paymentId && $keepPaymentOn[$paymentId] !== $o['id']) {
                $note = 'duplicate_payment_id:'.$paymentId;
                $this->fix("F-04 duplicate payment id {$paymentId} moved off {$o['booking_reference']}");
                $refundFor = $paymentId; // created after the order row exists (FK)
                $paymentId = null;
            }
            $refundFor ??= null;
            $isPos = str_starts_with((string) $o['booking_reference'], 'POS-') || ! empty($o['pos_user_id']);

            DB::table('orders')->upsert([[
                'id' => $o['id'], 'customer_id' => $o['customer_id'], 'event_id' => $o['event_id'],
                'source' => $isPos ? 'pos' : 'web',
                'ticket_type' => $o['ticket_type'] ?? 'normal', 'quantity' => (int) $o['quantity'],
                'total_amount' => Money::of((string) $o['total_amount'])->toDecimal(),
                'payment_method' => $o['payment_method'], 'payment_status' => $status, 'payment_note' => $note,
                'payment_error_reason' => $o['payment_error_reason'] ?? null, 'payment_id' => $paymentId,
                'booking_reference' => $o['booking_reference'], // F-11 legacy formats kept untouched
                'qr_code' => $o['qr_code'] ?? null,
                'payment_expires_at' => $this->ts($o['payment_expires_at'] ?? null),
                'paid_at' => $this->ts($o['paid_at'] ?? null), 'confirmed_at' => $this->ts($o['confirmed_at'] ?? null),
                'confirmed_by' => isset($this->userIds[$o['confirmed_by'] ?? '']) || User::query()->whereKey($o['confirmed_by'] ?? '')->exists() ? $o['confirmed_by'] : null,
                'pos_user_id' => $o['pos_user_id'] ?? null,
                'sadad_manually_verified' => (bool) ($o['sadad_manually_verified'] ?? false),
                'n8n_response_message' => $o['n8n_response_message'] ?? null, 'n8n_responded_at' => $this->ts($o['n8n_responded_at'] ?? null),
                'send_attempt_count' => (int) ($o['send_attempt_count'] ?? 0),
                'invoice_email_sent_at' => $this->ts($o['invoice_email_sent_at'] ?? null),
                'return_origin' => $o['return_origin'] ?? null, 'last_payment_check_at' => $this->ts($o['last_payment_check_at'] ?? null),
                'created_at' => $created, 'updated_at' => $this->now,
            ]], ['id']);

            if ($refundFor !== null && ! DB::table('refunds')->where('order_id', $o['id'])->where('reason', 'duplicate_payment')->exists()) {
                DB::table('refunds')->insert([
                    'id' => (string) Str::uuid7(), 'order_id' => $o['id'], 'reason' => 'duplicate_payment',
                    'amount' => Money::of((string) $o['total_amount'])->toDecimal(), 'status' => 'requested', 'gateway' => 'sadad',
                    'gateway_reference' => $refundFor, 'note' => 'Imported: payment id also used by another booking',
                    'created_at' => $this->now, 'updated_at' => $this->now,
                ]);
            }
            $refundFor = null;
            $this->deriveItems($o, collect($holders[$o['id']] ?? []));
            $this->count('orders', imported: 1);
        }
        $this->count('orders', source: $rows->count());
    }

    /** Step 8 — ticket holders (F-01, F-05, F-06, F-07, F-10, F-14) + holder-less orders. */
    private function stepHolders(): void
    {
        $this->loadTypes();
        $orders = DB::table('orders')->get(['id', 'event_id', 'booking_reference', 'quantity', 'ticket_type', 'customer_id', 'qr_code', 'created_at'])->keyBy('id');
        $rows = $this->rows('ticket_holders');
        $byOrder = $rows->groupBy('order_id');

        foreach ($orders as $order) {
            $list = collect($byOrder[$order->id] ?? [])->sortBy([
                fn (array $a, array $b): int => strcmp((string) ($a['created_at'] ?? ''), (string) ($b['created_at'] ?? '')),
                fn (array $a, array $b): int => strcmp((string) $a['id'], (string) $b['id']),
            ])->values();
            if ($list->isEmpty() && $order->quantity > 0) {
                $list = $this->synthesiseHolders($order); // R-049: legacy holder-less orders get holder rows
            }
            $unit = collect($this->lines[$order->id] ?? [])->mapWithKeys(fn ($l) => [$l['type'] => $l['unit']]);
            foreach ($list as $i => $h) {
                $type = (string) $h['ticket_type'];
                if (! in_array($type, ['vip', 'normal', 'parking'], true)) {
                    $this->warn("holder {$h['id']} of {$order->booking_reference} has unknown type '{$type}' — skipped, fix manually (F-10)");
                    $this->skip('ticket_holders');

                    continue;
                }
                [$code, $image] = $this->splitQr($h['qr_code'] ?? null, $h['qr_image_url'] ?? null, $order->booking_reference, $i + 1);
                [$cc, $phone] = $this->splitPhone((string) ($h['phone'] ?? ''), $h['country_code'] ?? null);
                $present = (bool) ($h['is_present'] ?? false);
                if ($present && empty($h['confirmed_at'])) {
                    $present = false; // F-06: POS used to mark tickets present at sale
                    $this->fix("F-06 holder {$code} was present without an admission time");
                }

                DB::table('ticket_holders')->upsert([[
                    'id' => $h['id'], 'order_id' => $order->id,
                    'ticket_type_id' => $this->types[$order->event_id][$type]['id'] ?? null, 'ticket_type' => $type,
                    'unit_price' => $unit[$type] ?? null,
                    'name' => mb_substr((string) $h['name'], 0, 100), 'phone' => $phone, 'country_code' => $cc,
                    'phone_e164' => PhoneNumber::e164($phone, $cc), 'nationality' => (string) ($h['nationality'] ?? ''),
                    'id_number' => $h['id_number'] ?? null, 'person_key' => PhoneNumber::personKey($h['id_number'] ?? null, $phone),
                    'position' => $i + 1, 'qr_code' => $code, 'qr_image_url' => $image,
                    'is_present' => $present, 'checked_in_at' => $present ? $this->ts($h['confirmed_at']) : null,
                    'checked_in_by' => $present && User::query()->whereKey($h['confirmed_by'] ?? '')->exists() ? $h['confirmed_by'] : null,
                    'checked_in_by_name' => $present ? ($h['confirmed_by_name'] ?? null) : null,
                    'created_at' => $this->ts($h['created_at'] ?? null) ?? $this->ts($order->created_at) ?? $this->now, 'updated_at' => $this->now,
                ]], ['id']);
                $this->count('ticket_holders', imported: 1);
            }
        }
        $this->count('ticket_holders', source: $rows->count());
    }

    /** Step 9 — recycle bin; rows of vanished orders are archived and skipped. */
    private function stepDeletedTickets(): void
    {
        $rows = $this->rows('deleted_tickets');
        $orders = DB::table('orders')->pluck('id')->flip();
        foreach ($rows as $d) {
            if (! $orders->has($d['order_id'])) {
                $this->warn("deleted ticket {$d['id']} belongs to a missing order — archived, not imported");
                $this->skip('deleted_tickets');

                continue;
            }
            [$code, $image] = $this->splitQr($d['qr_code'] ?? null, null, null, 0);
            DB::table('deleted_tickets')->upsert([[
                'id' => $d['id'], 'holder_id' => $d['holder_id'], 'order_id' => $d['order_id'],
                'name' => (string) $d['name'], 'phone' => (string) ($d['phone'] ?? ''), 'nationality' => (string) ($d['nationality'] ?? ''),
                'ticket_type' => $d['ticket_type'], 'qr_code' => $code, 'qr_image_url' => $image,
                'is_present' => (bool) ($d['is_present'] ?? false), 'checked_in_at' => $this->ts($d['confirmed_at'] ?? null),
                'checked_in_by_name' => $d['confirmed_by_name'] ?? null, 'id_number' => $d['id_number'] ?? null,
                'country_code' => $d['country_code'] ?? '+974', 'holder_created_at' => $this->ts($d['holder_created_at'] ?? null),
                'context' => is_array($d['context'] ?? null) ? json_encode($d['context']) : ($d['context'] ?? null),
                'deleted_at' => $this->ts($d['deleted_at']) ?? $this->now, 'deleted_by_name' => $d['deleted_by_name'] ?? null,
            ]], ['id']);
            $this->count('deleted_tickets', imported: 1);
        }
        $this->count('deleted_tickets', source: $rows->count());
    }

    /** Step 10 — de-duplicate by code, keep the earliest. */
    private function stepExpiredQrCodes(): void
    {
        $rows = $this->rows('expired_qr_codes');
        $orders = DB::table('orders')->pluck('id')->flip();
        $kept = $rows->filter(fn ($r) => $orders->has($r['order_id']))->sortBy('created_at')->unique('qr_code');
        DB::table('expired_qr_codes')->upsert($kept->map(fn ($r) => [
            'id' => $r['id'], 'qr_code' => $r['qr_code'], 'order_id' => $r['order_id'], 'reason' => $r['reason'] ?? 'event_date_changed',
            'expired_at' => $this->ts($r['expired_at'] ?? null) ?? $this->now, 'created_at' => $this->ts($r['created_at'] ?? null) ?? $this->now,
        ])->values()->all(), ['id']);
        $this->count('expired_qr_codes', source: $rows->count(), imported: $kept->count(), skipped: $rows->count() - $kept->count());
    }

    private function stepPaymentEvents(): void
    {
        $rows = $this->rows('payment_events');
        $orders = DB::table('orders')->pluck('id')->flip();
        foreach ($rows->chunk(500) as $chunk) {
            DB::table('payment_events')->upsert($chunk->map(fn ($p) => array_filter([
                'id' => is_numeric($p['id'] ?? null) ? (int) $p['id'] : null,
                'booking_reference' => $p['booking_reference'] ?? null,
                'order_id' => $orders->has($p['order_id'] ?? '') ? $p['order_id'] : null,
                'source' => $p['source'] ?? 'callback', 'payload' => $this->jsonString($p['payload'] ?? null),
                'checksum_valid' => isset($p['checksum_valid']) ? (bool) $p['checksum_valid'] : null,
                'api_result' => $p['api_result'] ?? null, 'decision' => $p['decision'] ?? null, 'outcome' => $p['outcome'] ?? null,
                'created_at' => $this->ts($p['created_at'] ?? null) ?? $this->now,
            ], fn ($v, $k) => $k !== 'id' || $v !== null, ARRAY_FILTER_USE_BOTH))->values()->all(), ['id']);
        }
        $this->count('payment_events', source: $rows->count(), imported: $rows->count());
    }

    private function stepPaymentErrors(): void
    {
        $rows = $this->rows('payment_errors');
        $orders = DB::table('orders')->pluck('id')->flip();
        $events = DB::table('events')->pluck('id')->flip();
        DB::table('payment_errors')->upsert($rows->map(fn ($e) => [
            'id' => $e['id'], 'order_id' => $orders->has($e['order_id'] ?? '') ? $e['order_id'] : null,
            'booking_reference' => $e['booking_reference'] ?? null, 'event_id' => $events->has($e['event_id'] ?? '') ? $e['event_id'] : null,
            'customer_name' => $e['customer_name'] ?? null, 'customer_phone' => $e['customer_phone'] ?? null,
            'quantity' => $e['quantity'] ?? null, 'amount' => $e['amount'] ?? null, 'payment_id' => $e['payment_id'] ?? null,
            'error_source' => $e['error_source'] ?? 'sadad', 'error_code' => $e['error_code'] ?? null, 'error_message' => $e['error_message'] ?? null,
            'raw' => $this->jsonString($e['raw'] ?? null), 'created_at' => $this->ts($e['created_at'] ?? null) ?? $this->now,
        ])->values()->all(), ['id']);
        $this->count('payment_errors', source: $rows->count(), imported: $rows->count());
    }

    private function stepEmailEvents(): void
    {
        $rows = $this->rows('email_delivery_events');
        $orders = DB::table('orders')->pluck('id')->flip();
        $kept = $rows->filter(fn ($e) => $orders->has($e['order_id'] ?? ''));
        DB::table('email_delivery_events')->upsert($kept->map(fn ($e) => [
            'id' => $e['id'], 'order_id' => $e['order_id'], 'booking_reference' => $e['booking_reference'] ?? null,
            'recipient' => $e['recipient'] ?? null, 'recipient_normalized' => $e['recipient'] ? mb_strtolower(trim((string) $e['recipient'])) : null,
            'template' => $e['template'] ?? 'booking-invoice', 'status' => $e['status'], 'attempt' => (int) ($e['attempt'] ?? 1),
            'detail' => $e['detail'] ?? null, 'created_at' => $this->ts($e['created_at'] ?? null) ?? $this->now,
        ])->values()->all(), ['id']);
        $this->count('email_delivery_events', source: $rows->count(), imported: $kept->count(), skipped: $rows->count() - $kept->count());
    }

    /** Step 14 — attendance, receipts, banners, page views, activity logs (F-09), recent login attempts. */
    private function stepOperations(): void
    {
        $pos = DB::table('pos_users')->pluck('id')->flip();
        $users = DB::table('users')->pluck('id')->flip();
        $events = DB::table('events')->pluck('id')->flip();

        $att = $this->rows('staff_attendance')->filter(fn ($a) => $pos->has($a['pos_user_id']));
        DB::table('staff_attendance')->upsert($att->map(fn ($a) => [
            'id' => $a['id'], 'pos_user_id' => $a['pos_user_id'], 'attendance_date' => substr((string) $a['attendance_date'], 0, 10),
            'status' => $a['status'], 'marked_by' => $users->has($a['marked_by'] ?? '') ? $a['marked_by'] : null,
            'marked_at' => $this->ts($a['marked_at'] ?? null) ?? $this->now, 'created_at' => $this->ts($a['created_at'] ?? null) ?? $this->now, 'updated_at' => $this->now,
        ])->values()->all(), ['id']);
        $this->count('staff_attendance', source: $att->count(), imported: $att->count());

        $receipts = $this->rows('pos_receipts');
        DB::table('pos_receipts')->upsert($receipts->map(fn ($r) => [
            'id' => $r['id'], 'amount_qar' => $r['amount_qar'] ?? null, 'seq_number' => $r['seq_number'] ?? null,
            'card_number_masked' => $r['card_number_masked'] ?? null, 'time' => $r['time'] ?? null, 'auth_number' => $r['auth_number'] ?? null,
            'image_url' => $r['image_url'] ?? null, 'created_by' => $users->has($r['created_by'] ?? '') ? $r['created_by'] : null,
            'ticket_type' => $r['ticket_type'] ?? 'normal', 'num_tickets' => $r['num_tickets'] ?? null,
            'normal_tickets' => (int) ($r['normal_tickets'] ?? 0), 'vip_tickets' => (int) ($r['vip_tickets'] ?? 0), 'parking_tickets' => (int) ($r['parking_tickets'] ?? 0),
            'created_at' => $this->ts($r['created_at'] ?? null) ?? $this->now, 'updated_at' => $this->now,
        ])->values()->all(), ['id']);
        $this->count('pos_receipts', source: $receipts->count(), imported: $receipts->count());

        $banners = $this->rows('popup_banners');
        DB::table('popup_banners')->upsert($banners->map(fn ($b) => [
            'id' => $b['id'], 'title' => (string) $b['title'], 'message' => (string) $b['message'], 'image_url' => $b['image_url'] ?? null,
            'is_active' => (bool) ($b['is_active'] ?? false), 'created_at' => $this->ts($b['created_at'] ?? null) ?? $this->now, 'updated_at' => $this->now,
        ])->values()->all(), ['id']);
        $this->count('popup_banners', source: $banners->count(), imported: $banners->count());

        $views = $this->rows('page_views');
        foreach ($views->chunk(1000) as $chunk) {
            DB::table('page_views')->upsert($chunk->map(fn ($v) => [
                'id' => $v['id'], 'session_id' => mb_substr((string) $v['session_id'], 0, 64), 'page_path' => mb_substr((string) $v['page_path'], 0, 255),
                'country' => $v['country'] ?? null, 'country_code' => $v['country_code'] ?? null, 'city' => $v['city'] ?? null,
                'device_type' => $v['device_type'] ?? 'desktop', 'browser' => $v['browser'] ?? null, 'os' => $v['os'] ?? null,
                'traffic_source' => $v['traffic_source'] ?? 'direct', 'is_new_visitor' => (bool) ($v['is_new_visitor'] ?? true),
                'ip_address' => null, // privacy: raw legacy IPs are not carried over
                'viewed_at' => $this->ts($v['viewed_at']), 'viewed_on_qatar' => QatarClock::dateOf($this->ts($v['viewed_at'])),
            ])->values()->all(), ['id']);
        }
        $this->count('page_views', source: $views->count(), imported: $views->count());

        // F-09 activity logs → activity_log; a fixed batch id keeps re-runs idempotent.
        $batch = '00000000-0000-4000-8000-00000000a109';
        DB::table('activity_log')->where('batch_uuid', $batch)->delete();
        $logs = $this->rows('activity_logs');
        foreach ($logs->chunk(500) as $chunk) {
            DB::table('activity_log')->insert($chunk->map(function ($l) use ($batch) {
                $data = $this->json($l['action_data'] ?? null) ?? [];
                $type = (string) $l['activity_type'];
                if ($type === 'pos_form' && ($data['action'] ?? null) === 'invoice_download') {
                    $type = 'invoice_download';
                }

                return [
                    'log_name' => $type, 'description' => 'Imported '.$type, 'batch_uuid' => $batch,
                    'properties' => json_encode(['legacy_id' => $l['id'], 'action_data' => $data, 'metadata' => $this->json($l['metadata'] ?? null)], JSON_UNESCAPED_UNICODE),
                    'actor_type' => mb_substr((string) ($l['user_type'] ?? 'guest'), 0, 10), 'actor_identifier' => mb_substr((string) ($l['user_identifier'] ?? ''), 0, 160) ?: null,
                    'ip_address' => $l['ip_address'] ?? null, 'user_agent' => mb_substr((string) ($l['user_agent'] ?? ''), 0, 300) ?: null,
                    'created_at' => $this->ts($l['created_at']), 'updated_at' => $this->ts($l['created_at']),
                ];
            })->values()->all());
        }
        $this->count('activity_log', source: $logs->count(), imported: $logs->count());

        $attempts = $this->rows('login_attempts')->filter(fn ($a) => $this->ts($a['attempted_at'])?->greaterThan($this->now->subDays(7)));
        DB::table('login_attempts')->upsert($attempts->map(fn ($a) => [
            'id' => $a['id'], 'identifier' => mb_substr((string) $a['identifier'], 0, 160), 'kind' => $a['kind'] === 'passcode' ? 'passcode' : 'account',
            'ip_address' => $a['ip_address'] ?? null, 'user_agent' => mb_substr((string) ($a['user_agent'] ?? ''), 0, 300) ?: null,
            'success' => (bool) $a['success'], 'attempted_at' => $this->ts($a['attempted_at']), 'created_at' => $this->ts($a['created_at'] ?? null) ?? $this->now,
        ])->values()->all(), ['id']);
        $this->count('login_attempts', imported: $attempts->count());

        unset($events);
    }

    /** F-02 — stale pending online orders are closed as expired; fresh ones get a hold. */
    private function stepFixes(): void
    {
        $cutoff = $this->now->subMinutes(self::HOLD_MINUTES);
        $stale = DB::table('orders')->where('payment_method', 'sadad')->where('payment_status', 'pending')->whereNull('payment_expires_at')->where('created_at', '<', $cutoff)->get(['id', 'booking_reference']);
        foreach ($stale as $o) {
            DB::table('orders')->where('id', $o->id)->update(['payment_status' => 'cancelled', 'payment_note' => 'expired', 'updated_at' => $this->now]);
            $this->fix("F-02 stale online hold {$o->booking_reference} closed as expired");
        }
        $fresh = DB::table('orders')->where('payment_method', 'sadad')->where('payment_status', 'pending')->whereNull('payment_expires_at')->get(['id', 'created_at']);
        foreach ($fresh as $o) {
            DB::table('orders')->where('id', $o->id)->update(['payment_expires_at' => CarbonImmutable::parse($o->created_at, 'UTC')->addMinutes(self::HOLD_MINUTES)]);
        }
        $this->count('fixes', imported: $stale->count() + $fresh->count());
    }

    /** Step 15 / F-03 — recount every counter from live holders, then verify. */
    private function stepRecount(): void
    {
        $ledger = app(StockLedger::class);
        $changed = $ledger->recount(true);
        foreach ($changed as $row) {
            $this->fix('F-03 recount '.json_encode($row, JSON_UNESCAPED_UNICODE));
        }
        $drift = $ledger->recount(false);
        if ($drift !== []) {
            $this->warn(count($drift).' ticket types still drift after recount');
        }
        $this->count('recount', imported: count($changed));
    }

    /** Step 16 — settings row → settings groups. Secrets are NOT copied unless explicitly asked (D-002). */
    private function stepSettings(): void
    {
        $s = $this->rows('settings')->first();
        if (! $s) {
            $this->warn('no legacy settings row');

            return;
        }
        $branding = app(BrandingSettings::class);
        foreach (['logo_url', 'hero_image_url', 'hero_text', 'header_bg_color', 'header_bg_image_url', 'before_footer_image_url', 'copyright_text'] as $k) {
            if (property_exists($branding, $k) && array_key_exists($k, $s)) {
                $branding->{$k} = $s[$k];
            }
        }
        $branding->save();

        $contact = app(ContactSettings::class);
        $contact->admin_phone = $s['admin_phone'] ?? $contact->admin_phone;
        if (property_exists($contact, 'admin_emails') && ! empty($s['admin_email'])) {
            $contact->admin_emails = array_values(array_filter(array_map('trim', explode(',', (string) $s['admin_email']))));
        }
        $contact->save();

        $flags = app(FeatureFlagSettings::class);
        foreach (['show_delete_customer_button', 'show_delete_event_button', 'show_delete_ticket_button', 'show_generate_qr_button'] as $k) {
            if (array_key_exists($k, $s) && $s[$k] !== null) {
                $flags->{$k} = (bool) $s[$k];
            }
        }
        $flags->save();

        $sadad = app(SadadSettings::class);
        $sadad->merchant_id = $s['sadad_merchant_id'] ?? null;
        $sadad->website_domain = $s['sadad_website_domain'] ?? null;
        $sadad->environment = in_array($s['sadad_environment'] ?? 'auto', ['auto', 'sandbox', 'live'], true) ? ($s['sadad_environment'] ?? 'auto') : 'auto';
        $sadad->site_url = null; // T-001: starts null in the new system
        if ($this->options['include_secrets'] ?? false) {
            $sadad->checkout_secret = $s['sadad_secret'] ?? null;
            $this->warn('Sadad secret copied from the legacy database — rotate it after go-live (D-002)');
        }
        $sadad->save();

        $auto = app(AutomationSettings::class);
        $auto->webhook_enabled = (bool) ($s['webhook_enabled'] ?? false);
        $auto->email_webhook_enabled = (bool) ($s['email_webhook_enabled'] ?? false);
        $auto->payment_failed_email_enabled = (bool) ($s['payment_failed_email_enabled'] ?? true);
        foreach (['auto_invoice_interval_seconds', 'invoice_batch_min', 'invoice_batch_max', 'invoice_send_delay_min', 'invoice_send_delay_max'] as $k) {
            if (isset($s[$k])) {
                $auto->{$k} = (int) $s[$k];
            }
        }
        if ($this->options['include_secrets'] ?? false) {
            $auto->webhook_url = $s['webhook_url'] ?? null;
            $auto->email_webhook_url = $s['email_webhook_url'] ?? null;
        }
        $auto->save();

        $current = $s['current_event_id'] ?? null;
        $events = app(EventSettings::class);
        $events->current_event_id = $current && DB::table('events')->where('id', $current)->where('is_archived', false)->exists() ? $current : null;
        $events->save();
        $this->count('settings', source: 1, imported: 1);
    }

    // --------------------------------------------------------------- helpers

    /** @return Collection<int, array<string, mixed>> */
    private function rows(string $table): Collection
    {
        try {
            /** @var Collection<int, array<string, mixed>> $rows */
            $rows = collect($this->legacy->table($table)->get()->all())->map(fn (object $r): array => (array) $r)->values();

            return $rows;
        } catch (Throwable $e) {
            $this->warn("legacy table {$table} not readable: ".Str::limit($e->getMessage(), 120));

            return collect();
        }
    }

    private function loadTypes(): void
    {
        $this->types = [];
        foreach (DB::table('ticket_types')->get(['id', 'event_id', 'type', 'price']) as $t) {
            $this->types[$t->event_id][$t->type] = ['id' => $t->id, 'price' => (string) $t->price];
        }
    }

    /** Step 7 — order_items derived from the holders (or the legacy order line). */
    private function deriveItems(array $order, Collection $holders): void
    {
        $counts = $holders->isNotEmpty()
            ? $holders->countBy('ticket_type')->filter(fn ($n, $t) => in_array($t, ['vip', 'normal', 'parking'], true))
            : collect([(string) ($order['ticket_type'] ?? 'normal') => (int) $order['quantity']]);
        $prices = $this->types[$order['event_id']] ?? [];
        $total = Money::of((string) $order['total_amount']);
        $atList = $counts->reduce(fn (Money $sum, $n, $t) => $sum->add(Money::of($prices[$t]['price'] ?? '0')->multiply($n)), Money::zero());

        $lines = [];
        $remaining = $total->cents;
        $keys = $counts->keys()->values();
        foreach ($keys as $i => $type) {
            $n = (int) $counts[$type];
            if ($atList->cents === $total->cents && $atList->cents > 0) {
                $lineCents = Money::of($prices[$type]['price'] ?? '0')->cents * $n;
            } else {
                $weight = $atList->cents > 0 ? (Money::of($prices[$type]['price'] ?? '0')->cents * $n) / $atList->cents : $n / max(1, $counts->sum());
                $lineCents = (int) floor($total->cents * $weight);
            }
            $lines[] = ['type' => $type, 'qty' => $n, 'cents' => $lineCents];
            $remaining -= $lineCents;
        }
        if ($lines !== [] && $remaining !== 0) {
            $lines[0]['cents'] += $remaining; // remainder on the first line
        }

        DB::table('order_items')->where('order_id', $order['id'])->delete();
        $this->lines[$order['id']] = [];
        foreach ($lines as $l) {
            $unit = number_format($l['qty'] > 0 ? round($l['cents'] / $l['qty'] / 100, 2) : 0, 2, '.', '');
            DB::table('order_items')->insert([
                'id' => (string) Str::uuid7(), 'order_id' => $order['id'], 'ticket_type_id' => $prices[$l['type']]['id'] ?? null,
                'type' => $l['type'], 'unit_price' => $unit, 'quantity' => $l['qty'],
                'line_total' => number_format($l['cents'] / 100, 2, '.', ''), 'price_source' => 'derived',
                'created_at' => $this->now, 'updated_at' => $this->now,
            ]);
            $this->lines[$order['id']][] = ['type' => $l['type'], 'unit' => $unit, 'qty' => $l['qty']];
        }
        $this->count('order_items', imported: count($lines));
    }

    /** @return Collection<int, array<string, mixed>> */
    private function synthesiseHolders(object $order): Collection
    {
        $customer = DB::table('customers')->where('id', $order->customer_id)->first();
        $this->fix("R-049 holder-less order {$order->booking_reference} → {$order->quantity} holder row(s)");

        // Deterministic ids (UUIDv5 of order + position) keep re-runs idempotent.
        /** @var list<array<string, mixed>> $rows */
        $rows = [];
        $quantity = (int) $order->quantity;
        for ($i = 1; $i <= $quantity; $i++) {
            $rows[] = [
                'id' => Uuid::uuid5('6f1d7c2a-3b4e-5f60-8a9b-0c1d2e3f4a5b', $order->id.'#'.$i)->toString(),
                'ticket_type' => (string) $order->ticket_type, 'name' => (string) ($customer->name ?? '—'),
                'phone' => (string) ($customer->phone ?? ''), 'country_code' => (string) ($customer->country_code ?? '+974'),
                'nationality' => (string) ($customer->nationality ?? ''), 'id_number' => $customer->id_number ?? null,
                'qr_code' => $quantity === 1 ? $order->qr_code : null, 'created_at' => $order->created_at,
            ];
        }

        return collect($rows);
    }

    /**
     * F-01 / F-05: an image URL in qr_code becomes the code (file name) + qr_image_url;
     * a missing code becomes <reference>-TKT<NN>.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function splitQr(?string $code, ?string $image, ?string $reference, int $position): array
    {
        $code = $code !== null ? trim($code) : null;
        if ($code !== null && preg_match('#^https?://#i', $code)) {
            $image ??= $code;
            $code = preg_replace('/\.png(\?.*)?$/i', '', preg_replace('#^.*/#', '', $code));
            $this->fix("F-01 QR image URL split into code {$code}");
        }
        if (($code === null || $code === '') && $reference) {
            $code = sprintf('%s-TKT%02d', $reference, $position);
            $this->fix("F-05 missing QR code → {$code}");
        }

        return [$code ?: null, $image];
    }

    /**
     * F-07 / F-14: NULL country code → +974; a typed prefix ("+971 55510002") is split.
     *
     * @return array{0: string, 1: string}
     */
    private function splitPhone(string $phone, ?string $cc): array
    {
        $phone = trim($phone);
        if (preg_match('/^\+(\d{1,4})[\s-]+(.+)$/', $phone, $m)) {
            if ($cc === null || $cc === '' || $cc === '+974') {
                $cc = '+'.$m[1];
            }
            $phone = $m[2];
            $this->fix('F-14 phone with typed prefix split');
        }
        if ($cc === null || $cc === '') {
            $cc = '+974';
        }

        return [$cc, preg_replace('/\s+/', '', $phone)];
    }

    private function assertTestDataGone(): void
    {
        foreach (self::TEST_EVENT_PREFIXES as $p) {
            if (DB::table('events')->where('id', 'like', $p.'%')->exists()) {
                $this->warn("F-12 legacy test event {$p}… is still present");
            }
        }
        if (DB::table('customers')->whereIn('email', self::TEST_EMAILS)->exists()) {
            $this->warn('F-12 legacy test customers are still present');
        }
    }

    private function ts(mixed $v): ?CarbonImmutable
    {
        if ($v === null || $v === '') {
            return null;
        }
        try {
            return CarbonImmutable::parse($v instanceof \DateTimeInterface ? $v->format(DATE_ATOM) : (string) $v)->utc();
        } catch (Throwable) {
            return null;
        }
    }

    private function time(mixed $v): ?string
    {
        return is_string($v) && preg_match('/^(\d{1,2}):(\d{2})/', $v, $m) ? sprintf('%02d:%02d:00', $m[1], $m[2]) : null;
    }

    private function json(mixed $v): ?array
    {
        if (is_array($v)) {
            return $v;
        }
        $d = is_string($v) ? json_decode($v, true) : null;

        return is_array($d) ? $d : null;
    }

    private function jsonString(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }

        return is_string($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE);
    }

    private function count(string $table, int $source = 0, int $imported = 0, int $skipped = 0): void
    {
        $this->counts[$table] ??= ['source' => 0, 'imported' => 0, 'skipped' => 0];
        $this->counts[$table]['source'] += $source;
        $this->counts[$table]['imported'] += $imported;
        $this->counts[$table]['skipped'] += $skipped;
    }

    private function skip(string $table): void
    {
        $this->count($table, skipped: 1);
    }

    private function fix(string $message): void
    {
        $this->fixes[] = $message;
    }

    private function warn(string $message): void
    {
        $this->warnings[] = $message;
        $this->say('  ! '.$message);
    }

    private function say(string $message): void
    {
        if ($this->log) {
            ($this->log)($message);
        }
    }

    /** Markdown reconciliation report (counts and confirmed revenue old vs new). */
    public function report(bool $dryRun): string
    {
        $legacyRevenue = $this->legacyRevenue ?? '?';
        $newRevenue = (string) DB::table('orders')->where('payment_status', 'confirmed')->sum('total_amount');
        $out = ['# Data migration report', '', '- Run: '.$this->now->setTimezone('Asia/Qatar')->format('Y-m-d H:i').' (Qatar)'.($dryRun ? ' — **dry run, rolled back**' : ''),
            "- Confirmed revenue: legacy {$legacyRevenue} QAR → new {$newRevenue} QAR".($dryRun ? ' (new = target after rollback)' : ''), '',
            '| Table | Source | Imported | Skipped |', '|---|---:|---:|---:|'];
        foreach ($this->counts as $t => $c) {
            $out[] = "| {$t} | {$c['source']} | {$c['imported']} | {$c['skipped']} |";
        }
        $out[] = '';
        $out[] = '## Fixes applied ('.count($this->fixes).')';
        foreach (array_slice(array_count_values(array_map(fn ($f) => strtok($f, ' '), $this->fixes)), 0) as $code => $n) {
            $out[] = "- {$code}: {$n}";
        }
        $out[] = '';
        $out[] = '## Warnings ('.count($this->warnings).')';
        foreach ($this->warnings as $w) {
            $out[] = '- '.$w;
        }

        return implode("\n", $out)."\n";
    }
}
