<?php

namespace App\Livewire\Staff;

use App\Domain\Booking\Actions\CreateOrderAction;
use App\Domain\Booking\Actions\ExpireHoldsAction;
use App\Domain\Booking\Data\OrderRequestValidator;
use App\Domain\Booking\Queries\AvailabilityQuery;
use App\Domain\Booking\Queries\CustomerLookupQuery;
use App\Domain\Booking\Services\PersonLimitGuard;
use App\Domain\Messaging\AutomationRelay;
use App\Domain\Shared\Actor;
use App\Domain\Shared\DomainError;
use App\Domain\Tickets\CheckInService;
use App\Domain\Tickets\QrService;
use App\Enums\ActivityType;
use App\Enums\TicketKind;
use App\Models\Customer;
use App\Models\Event;
use App\Models\Order;
use App\Models\PosUser;
use App\Support\Audit;
use App\Support\Countries;
use App\Support\Money;
use App\Support\PhoneNumber;
use App\Support\Text;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Session;
use Livewire\Component;
use Throwable;

/**
 * POS — venue cash desk (F-POS-001…046). Every rule is re-checked by
 * CreateOrderAction inside one locked transaction; the screen only guides.
 */
#[Layout('components.layouts.staff')]
class Pos extends Component
{
    #[Session(key: 'pos.event')]
    public ?string $eventId = null;

    #[Session(key: 'pos.cashier')]
    public ?string $posUserId = null;

    #[Session(key: 'pos.cc')]
    public string $lastCountryCode = '+974';

    /** @var list<array<string,mixed>> */
    #[Locked]
    public array $tickets = [];

    /** @var array<string,int> remembered capacities for the increase banner (F-POS-039) */
    #[Locked]
    public array $capacities = [];

    /** @var array<string,int> per-card quantity selector */
    public array $addQty = [];

    /** @var array<string,int> cart: ticket_type id => qty */
    public array $cart = [];

    public array $buyer = ['name' => '', 'phone' => '', 'country_code' => '+974', 'nationality' => 'قطر', 'id_number' => '', 'email' => ''];

    #[Locked]
    public ?string $buyerIdToken = null;

    #[Locked]
    public ?string $buyerIdMasked = null;

    /** @var list<array<string,mixed>> */
    public array $holders = [];

    public bool $moreNationalities = false;

    public string $lookup = '';

    /** @var list<array<string,mixed>> */
    #[Locked]
    public array $lookupResults = [];

    // Manual check-in dialog
    public string $checkinTerm = '';

    /** @var list<array<string,mixed>> */
    #[Locked]
    public array $checkinResults = [];

    #[Locked]
    public ?array $success = null;

    #[Locked]
    public ?string $lastReference = null;

    public function mount(): void
    {
        $this->buyer['country_code'] = $this->lastCountryCode ?: '+974';
        if ($this->posUserId && ! PosUser::query()->active()->whereKey($this->posUserId)->exists()) {
            $this->posUserId = null;
        }
        // Personal account linked to a cashier profile → preselect (F-POS-003 rebuild).
        if (! $this->posUserId && ($linked = auth()->user()?->pos_user_id)) {
            $this->posUserId = $linked;
        }
        $events = $this->events();
        if (! $this->eventId || ! $events->contains('id', $this->eventId)) {
            $this->eventId = $events->first()?->id;
        }
        $this->loadTickets(true);
    }

    // ---- Data -------------------------------------------------------------------------

    public function events()
    {
        return Event::query()->public()->orderBy('event_date')->orderBy('display_order')->get()
            ->filter(fn (Event $e) => $e->canPurchaseTickets())->values();
    }

    private function loadTickets(bool $expire = false): void
    {
        $event = $this->eventId ? Event::query()->find($this->eventId) : null;
        if (! $event) {
            $this->tickets = [];

            return;
        }
        if ($expire) {
            rescue(fn () => app(ExpireHoldsAction::class)->forEvent($event->id), report: false);
        }
        $fresh = app(AvailabilityQuery::class)->forEvent($event);
        // F-POS-039: capacity increase banner + bell.
        foreach ($fresh as $t) {
            $before = $this->capacities[$t['id']] ?? null;
            if ($before !== null && $t['capacity'] > $before) {
                $label = TicketKind::from($t['type'])->label();
                $this->dispatch('capacity-increased', type: $t['type'], label: $label, added: $t['capacity'] - $before, total: $t['capacity'],
                    message: __('ops.pos.new_capacity_body', ['n' => $t['capacity'] - $before, 'type' => $label, 'total' => $t['capacity']]));
            }
            $this->capacities[$t['id']] = $t['capacity'];
            $this->addQty[$t['id']] ??= 1;
        }
        $this->tickets = $fresh;
    }

    public function refreshStock(): void
    {
        $this->loadTickets();
    }

    public function updatedEventId(): void
    {
        $this->cart = [];
        $this->holders = [];
        $this->capacities = [];
        $this->loadTickets(true);
    }

    public function updatedPosUserId(?string $id): void
    {
        $name = $id ? PosUser::query()->whereKey($id)->value('name') : null;
        if ($name) {
            $this->toast('success', __('ops.shell.cashier_selected', ['name' => $name]));
        }
    }

    private function ticket(string $id): ?array
    {
        return collect($this->tickets)->firstWhere('id', $id);
    }

    private function admissionInCart(array $cart = null): int
    {
        $cart ??= $this->cart;
        $n = 0;
        foreach ($cart as $id => $q) {
            $t = $this->ticket($id);
            if ($t && $t['type'] !== 'parking') {
                $n += $q;
            }
        }

        return $n;
    }

    private function maxAdmission(): int
    {
        return (int) config('qcamelmc.orders.pos_max_admission', 5);
    }

    // ---- Cart (F-POS-006…012) -----------------------------------------------------------

    public function stepAdd(string $id, int $delta): void
    {
        $t = $this->ticket($id);
        if (! $t) {
            return;
        }
        $this->addQty[$id] = max(1, min(max(1, $t['remaining']), (int) ($this->addQty[$id] ?? 1) + $delta));
    }

    public function addToCart(string $id): void
    {
        $t = $this->ticket($id);
        if (! $t || $t['sold_out']) {
            return;
        }
        $qty = max(1, (int) ($this->addQty[$id] ?? 1));
        $inCart = (int) ($this->cart[$id] ?? 0);
        if ($inCart + $qty > $t['remaining']) {
            $this->toast('error', __('ops.pos.cannot_add', ['n' => max(0, $t['remaining'] - $inCart)]));

            return;
        }
        $next = $this->cart;
        $next[$id] = $inCart + $qty;
        if ($t['type'] !== 'parking' && $this->admissionInCart($next) > $this->maxAdmission()) {
            $this->toast('error', __('ops.pos.cap_admission', ['max' => $this->maxAdmission()]));

            return;
        }
        $this->cart = $next;
        $this->addQty[$id] = 1;
        $this->reconcile();
        $this->toast('success', __('ops.pos.added', ['qty' => $qty, 'type' => TicketKind::from($t['type'])->label()]), duration: 1800);
    }

    public function removeUnit(string $id): void
    {
        if (! isset($this->cart[$id])) {
            return;
        }
        $this->cart[$id]--;
        if ($this->cart[$id] <= 0) {
            unset($this->cart[$id]);
        }
        $this->reconcile();
    }

    public function removeHolder(int $index): void
    {
        $h = $this->holders[$index] ?? null;
        if (! $h) {
            return;
        }
        unset($this->holders[$index]);
        $this->holders = array_values($this->holders);
        foreach ($this->orderedCart() as [$id, $t]) {
            if ($t['type'] === $h['type']) {
                $this->removeUnit($id);
                break;
            }
        }
        $this->toast('success', __('ops.pos.holder_deleted'), duration: 1800);
    }

    /** @return list<array{0:string,1:array}> cart lines in display order */
    private function orderedCart(): array
    {
        $out = [];
        foreach ($this->tickets as $t) {
            if (($this->cart[$t['id']] ?? 0) > 0) {
                $out[] = [$t['id'], $t];
            }
        }

        return $out;
    }

    /** F-POS-021: one holder per extra seat; keep typed data per type. */
    private function reconcile(): void
    {
        $types = [];
        foreach ($this->orderedCart() as [$id, $t]) {
            for ($i = 0; $i < $this->cart[$id]; $i++) {
                $types[] = $t['type'];
            }
        }
        array_shift($types); // the buyer holds the first seat
        $pool = [];
        foreach ($this->holders as $h) {
            $pool[$h['type']][] = $h;
        }
        $next = [];
        foreach ($types as $type) {
            $next[] = ! empty($pool[$type]) ? array_shift($pool[$type]) : [
                'uid' => Str::random(8), 'type' => $type,
                'name' => '', 'name_auto' => true,
                'phone' => $this->buyer['phone'], 'country_code' => $this->buyer['country_code'],
                'nationality' => $this->buyer['nationality'] ?: 'قطر', 'id_number' => '', 'dirty' => [],
            ];
        }
        $this->holders = $next;
    }

    // ---- Buyer ↔ holders sync (F-POS-023 rebuild: never overwrite edited fields, never copy the ID) ----

    public function updated(string $name, mixed $value): void
    {
        if (str_starts_with($name, 'buyer.')) {
            $field = substr($name, 6);
            if ($field === 'phone') {
                $this->buyer['phone'] = PhoneNumber::normalizeNational(PhoneNumber::normalizeDigits((string) $value), $this->buyer['country_code']);
            }
            if ($field === 'id_number') {
                $this->buyer['id_number'] = PhoneNumber::normalizeDigits((string) $value);
                $this->buyerIdToken = null;
                $this->buyerIdMasked = null;
            }
            if ($field === 'country_code') {
                $this->lastCountryCode = (string) $value;
            }
            if ($field === 'email') {
                $this->buyer['email'] = Text::extractEmail((string) $value);
            }
            foreach ($this->holders as $i => $h) {
                if (in_array($field, ['phone', 'country_code', 'nationality'], true) && ! in_array($field, $h['dirty'] ?? [], true)) {
                    $this->holders[$i][$field] = $this->buyer[$field];
                }
            }
        }
        if (preg_match('/^holders\.(\d+)\.(\w+)$/', $name, $m) && isset($this->holders[(int) $m[1]])) {
            $i = (int) $m[1];
            $field = $m[2];
            $this->holders[$i]['dirty'] = array_values(array_unique([...($this->holders[$i]['dirty'] ?? []), $field]));
            if ($field === 'name') {
                $this->holders[$i]['name_auto'] = false;
            }
            if ($field === 'phone') {
                $this->holders[$i]['phone'] = PhoneNumber::normalizeNational(PhoneNumber::normalizeDigits((string) $value), $this->holders[$i]['country_code'] ?? '+974');
            }
            if ($field === 'id_number') {
                $this->holders[$i]['id_number'] = PhoneNumber::normalizeDigits((string) $value);
            }
        }
        if ($name === 'lookup') {
            $this->searchCustomers();
        }
        if ($name === 'checkinTerm') {
            $this->searchCheckin();
        }
    }

    public function setNationality(string $nat): void
    {
        $this->buyer['nationality'] = $nat;
        $this->updated('buyer.nationality', $nat);
    }

    public function sameAsBuyer(int $i): void
    {
        if (! isset($this->holders[$i])) {
            return;
        }
        foreach (['phone', 'country_code', 'nationality'] as $f) {
            $this->holders[$i][$f] = $this->buyer[$f];
        }
    }

    // ---- Customer recall (F-POS-017…019) ----------------------------------------------

    public function searchCustomers(): void
    {
        $term = trim($this->lookup);
        if (mb_strlen($term) < 3) {
            $this->lookupResults = [];

            return;
        }
        try {
            $this->lookupResults = app(CustomerLookupQuery::class)->search($term, Actor::current(), $this->eventId);
        } catch (Throwable) {
            $this->lookupResults = [];
            $this->toast('error', __('ops.pos.recall_failed'));
        }
    }

    public function pickCustomer(int $index): void
    {
        $c = $this->lookupResults[$index] ?? null;
        if (! $c) {
            return;
        }
        $this->buyer = [
            'name' => (string) $c['name'], 'phone' => (string) $c['phone'], 'country_code' => (string) ($c['country_code'] ?: '+974'),
            'nationality' => (string) ($c['nationality'] ?: 'قطر'), 'id_number' => (string) ($c['id_number'] ?? ''), 'email' => (string) ($c['email'] ?? ''),
        ];
        // Kiosk staff never receive raw IDs: keep an encrypted token and show the mask.
        $this->buyerIdToken = $c['id_number'] === null ? $c['prefill_token'] : null;
        $this->buyerIdMasked = $c['id_number'] === null ? $c['id_number_masked'] : null;
        foreach ($this->holders as $i => $h) {
            foreach (['phone', 'country_code', 'nationality'] as $f) {
                if (! in_array($f, $h['dirty'] ?? [], true)) {
                    $this->holders[$i][$f] = $this->buyer[$f];
                }
            }
        }
        $this->lookup = '';
        $this->lookupResults = [];
        $this->toast('success', __('ops.pos.recall_filled', ['name' => $c['name']]));
    }

    private function buyerIdNumber(): string
    {
        if ($this->buyerIdToken && $this->buyer['id_number'] === '') {
            $customerId = CustomerLookupQuery::idFromToken($this->buyerIdToken);
            $id = $customerId ? Customer::query()->whereKey($customerId)->value('id_number') : null;

            return (string) $id;
        }

        return (string) $this->buyer['id_number'];
    }

    // ---- Sale (F-POS-024…037) -----------------------------------------------------------

    public function submit(CreateOrderAction $create, PersonLimitGuard $limits): void
    {
        $actor = Actor::current();
        if (! $actor->can('pos.sell')) {
            $this->toast('error', __('errors.forbidden'));

            return;
        }
        $event = $this->eventId ? Event::query()->find($this->eventId) : null;
        if (! $event) {
            $this->toast('error', __('ops.pos.no_event'));

            return;
        }
        $lines = $this->orderedCart();
        if ($lines === []) {
            $this->toast('error', __('ops.pos.empty_cart'));

            return;
        }
        $this->buyer['name'] = Text::clean($this->buyer['name']);
        foreach ($this->holders as $i => $h) {
            $this->holders[$i]['name'] = Text::clean($h['name']);
        }
        $buyerId = $this->buyerIdNumber();
        if ($this->buyer['name'] === '' || $this->buyer['phone'] === '' || $this->buyer['nationality'] === '' || $buyerId === '') {
            $this->toast('error', __('ops.pos.fill_required'));

            return;
        }
        foreach ($this->holders as $h) {
            if ($h['name'] === '' || $h['nationality'] === '' || $h['id_number'] === '') {
                $this->toast('error', __('ops.pos.fill_holders'));

                return;
            }
        }

        // Advisory pre-checks with live stock (F-POS-025/026); the transaction stays authoritative.
        $this->loadTickets(true);
        foreach ($lines as [$id, $t]) {
            $fresh = $this->ticket($id);
            if (! $fresh || $this->cart[$id] > $fresh['remaining']) {
                $msg = ! $fresh || $fresh['remaining'] <= 0 ? __('orders.sold_out_now') : __('orders.not_enough', ['n' => $fresh['remaining']]);
                $this->toast('error', TicketKind::from($t['type'])->label().': '.$msg, __('ops.pos.unavailable_title'), 6000);

                return;
            }
        }
        $people = [['type' => $lines[0][1]['type'], 'name' => $this->buyer['name'], 'phone' => $this->buyer['country_code'].$this->buyer['phone'], 'id_number' => $buyerId]];
        foreach ($this->holders as $h) {
            $people[] = ['type' => $h['type'], 'name' => $h['name'], 'phone' => $h['country_code'].$h['phone'], 'id_number' => $h['id_number']];
        }
        $violations = rescue(fn () => $limits->violations($event->id, $people, (int) $event->limit('person')), [], false);
        if ($violations !== []) {
            $this->toast('error', implode(' — ', array_column($violations, 'message')), __('ops.pos.limit_title'), 8000);

            return;
        }

        $idByType = [];
        foreach ($lines as [$id, $t]) {
            $idByType[$t['type']] ??= $id;
        }
        $holders = [[
            'ticket_id' => $lines[0][0], 'name' => $this->buyer['name'], 'phone' => $this->buyer['phone'],
            'country_code' => $this->buyer['country_code'], 'nationality' => $this->buyer['nationality'], 'id_number' => $buyerId,
        ]];
        foreach ($this->holders as $h) {
            $holders[] = [
                'ticket_id' => $idByType[$h['type']], 'name' => $h['name'], 'phone' => $h['phone'] ?: $this->buyer['phone'],
                'country_code' => $h['country_code'] ?: $this->buyer['country_code'], 'nationality' => $h['nationality'], 'id_number' => $h['id_number'],
            ];
        }
        $body = [
            'source' => 'pos', 'payment_method' => 'cash_pos', 'pos_user_id' => $this->posUserId,
            'customer' => ['name' => $this->buyer['name'], 'email' => $this->buyer['email'], 'phone' => $this->buyer['phone'], 'country_code' => $this->buyer['country_code'], 'nationality' => $this->buyer['nationality'], 'id_number' => $buyerId],
            'items' => array_map(fn ($l) => ['ticket_id' => $l[0], 'quantity' => $this->cart[$l[0]]], $lines),
            'holders' => $holders,
        ];

        try {
            $data = app(OrderRequestValidator::class)->validate($body);
            $order = $create->execute($data, $actor, ['locale' => app()->getLocale(), 'idempotency_key' => 'pos-'.Str::uuid()]);
        } catch (DomainError $e) {
            $title = $e->errorCode === 'ticket_limit_exceeded' ? __('ops.pos.limit_title') : __('common.error');
            $msg = $e->errorCode === 'validation_failed' && ! empty($e->extra['errors']) ? implode("\n", array_slice($e->extra['errors'], 0, 4)) : $e->userMessage();
            $this->toast('error', $msg, $title, 7000);
            $this->dispatch('sfx', name: 'error');
            if (in_array($e->errorCode, ['insufficient_stock', 'invalid_tickets'], true)) {
                $this->loadTickets();
            }

            return;
        } catch (Throwable $e) {
            report($e);
            $this->toast('error', __('errors.default'));

            return;
        }

        $order->load('holders');
        Audit::log(ActivityType::PosForm, __('audit.pos_sale', ['ref' => $order->booking_reference]), $order, [
            'booking_reference' => $order->booking_reference, 'total_amount' => (string) $order->total_amount,
            'ticket_count' => $order->quantity, 'cashier' => $this->posUserId,
            'cart_items' => array_map(fn ($l) => ['ticket_type' => $l[1]['type'], 'quantity' => $this->cart[$l[0]], 'price' => $l[1]['price']], $lines),
        ], $actor);

        $first = $order->holders->sortBy('position')->first();
        $this->success = [
            'reference' => $order->booking_reference,
            'total' => (string) $order->total_amount,
            'count' => $order->quantity,
            'types' => collect($lines)->map(fn ($l) => ['qty' => $this->cart[$l[0]], 'label' => TicketKind::from($l[1]['type'])->longLabel()])->all(),
            'holders' => $order->holders->sortBy('position')->map(fn ($h) => ['name' => $h->name, 'type' => $h->ticket_type->label()])->values()->all(),
            'qr' => $first?->qr_code ? app(QrService::class)->svg(CheckInService::signedPayload($first->qr_code), 220) : null,
        ];
        $this->lastReference = $order->booking_reference;

        // Reset (country code kept, F-POS-034).
        $cc = $this->buyer['country_code'];
        $this->cart = [];
        $this->holders = [];
        $this->buyer = ['name' => '', 'phone' => '', 'country_code' => $cc, 'nationality' => 'قطر', 'id_number' => '', 'email' => ''];
        $this->buyerIdToken = null;
        $this->buyerIdMasked = null;
        $this->moreNationalities = false;
        $this->loadTickets();

        $this->dispatch('sfx', name: 'cash');
        $this->dispatch('fireworks');
        $this->dispatch('open-dialog', 'pos-success');
    }

    public function sendWhatsApp(AutomationRelay $relay): void
    {
        $order = $this->lastReference ? Order::query()->where('booking_reference', $this->lastReference)->first() : null;
        if (! $order) {
            return;
        }
        $res = rescue(fn () => $relay->send('send_tickets', $order, null, Actor::current()), ['success' => false], false);
        ($res['success'] ?? false)
            ? $this->toast('success', __('ops.pos.sent_whatsapp'))
            : $this->toast('error', $res['message'] ?? __('errors.default'));
    }

    // ---- Manual check-in (F-POS-042…044) ------------------------------------------------

    public function searchCheckin(): void
    {
        $term = trim($this->checkinTerm);
        if (mb_strlen($term) < 2) {
            $this->checkinResults = [];

            return;
        }
        try {
            $this->checkinResults = array_slice(app(CheckInService::class)->search($term, Actor::current())['results'] ?? [], 0, 40);
        } catch (Throwable) {
            $this->toast('error', __('ops.pos.checkin_failed_search'));
        }
    }

    public function admit(string $holderId, CheckInService $checkin): void
    {
        $label = $this->posUserId ? PosUser::query()->whereKey($this->posUserId)->value('name') : null;
        try {
            $res = $checkin->admitHolder($holderId, Actor::current(), $label, 'pos_dialog');
        } catch (DomainError $e) {
            $this->toast('error', $e->userMessage());

            return;
        }
        if ($res['success'] ?? false) {
            $this->toast('success', $res['message'] ?? __('ops.pos.checkin_done'));
            $this->dispatch('sfx', name: 'success');
        } else {
            $this->toast('error', $res['message'] ?? __('ops.pos.checkin_failed'));
            $this->dispatch('sfx', name: 'error');
        }
        $this->searchCheckin();
    }

    protected function toast(string $type, string $message, ?string $title = null, int $duration = 4500): void
    {
        $this->dispatch('toast', type: $type, message: $message, title: $title, duration: $duration);
    }

    public function render()
    {
        $total = Money::zero();
        foreach ($this->orderedCart() as [$id, $t]) {
            $total = $total->add(Money::of($t['price'])->multiply($this->cart[$id]));
        }

        return view('livewire.staff.pos', [
            'events' => $this->events(),
            'cashiers' => PosUser::query()->active()->orderBy('name')->get(['id', 'name', 'icon']),
            'lines' => $this->orderedCart(),
            'total' => $total,
            'count' => array_sum($this->cart),
            'admission' => $this->admissionInCart(),
            'maxAdmission' => $this->maxAdmission(),
            'gulf' => Countries::list('gulf'),
            'more' => Countries::list('pos_more'),
            'codes' => Countries::codes(),
        ])->title(__('ops.pos.title'));
    }
}
