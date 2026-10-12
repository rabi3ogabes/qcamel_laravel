<?php

namespace App\Livewire\Public;

use App\Domain\Booking\Actions\CreateOrderAction;
use App\Domain\Booking\Data\OrderRequestValidator;
use App\Domain\Booking\Services\PersonLimitGuard;
use App\Domain\Payments\Actions\FailPaymentAction;
use App\Domain\Payments\CheckoutErrorLogger;
use App\Domain\Payments\Sadad\SadadCheckoutBuilder;
use App\Domain\Shared\Actor;
use App\Domain\Shared\DomainError;
use App\Enums\TicketKind;
use App\Livewire\Public\Concerns\ManagesCart;
use App\Models\Event;
use App\Settings\BusinessSettings;
use App\Support\Countries;
use App\Support\Money;
use App\Support\PhoneNumber;
use App\Support\PublicSession;
use App\Support\PublicSettings;
use App\Support\Text;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * F-PUB-034…062: checkout. Holder 1 is the customer; extra holder slots
 * keep typed data when quantities change (D: original wiped them). All
 * rules are re-checked by CreateOrderAction inside one transaction.
 */
#[Layout('components.layouts.public', ['beforeFooter' => true])]
class Checkout extends Component
{
    use ManagesCart;

    /** @var array{name:string,email:string,phone:string,country_code:string,nationality:string,id_number:string} */
    public array $customer = ['name' => '', 'email' => '', 'phone' => '', 'country_code' => '+974', 'nationality' => 'قطر', 'id_number' => ''];

    /** @var list<array{uid:string,type:string,name:string,phone:string,country_code:string,nationality:string,id_number:string}> */
    public array $holders = [];

    public string $method = 'sadad';

    public bool $terms = false;

    #[Locked]
    public string $eventTitle = '';

    #[Locked]
    public string $eventDate = '';

    #[Locked]
    public string $priceSignature = '';

    #[Locked]
    public string $idempotencyKey = '';

    public function mount()
    {
        // F-PUB-034: no remembered selection → back to the event list.
        if (! PublicSession::hasCart()) {
            return $this->redirectRoute('home');
        }
        $cart = PublicSession::cart();
        $event = Event::query()->find($cart['event_id']);
        if (! $event || ! $event->isPublicSellable()) {
            PublicSession::clearCart();
            session()->flash('toast', ['type' => 'error', 'message' => __('public.select.not_bookable')]);

            return $this->redirectRoute('home');
        }
        $this->eventId = $event->id;
        $this->eventTitle = $event->title;
        $this->eventDate = \App\Support\Formats::longDate($event->event_date);
        $this->qty = $cart['lines'];
        $this->loadAvailability($event);
        $this->priceSignature = $this->signature();
        $this->idempotencyKey = (string) Str::uuid();
        if ($this->count() === 0) {
            PublicSession::clearCart();

            return $this->redirectRoute('tickets.select', $event->id);
        }
        $this->syncHolders();
    }

    // ---- Cart editing (F-PUB-037/038) ---------------------------------------------

    public function inc(string $id): void
    {
        if ($this->increase($id)) {
            $this->afterCartChange();
            $this->toast('success', __('public.checkout.increased'), duration: 1800);
        }
    }

    public function dec(string $id): void
    {
        if (($this->qty[$id] ?? 0) <= 1) {
            $this->dispatch('open-dialog', 'remove-'.$id);

            return;
        }
        $this->decrease($id);
        $this->afterCartChange();
        $this->toast('success', __('public.checkout.decreased'), duration: 1800);
    }

    public function removeLine(string $id)
    {
        unset($this->qty[$id]);
        $this->dispatch('close-dialog');
        if ($this->count() === 0) {
            PublicSession::clearCart();
            session()->flash('toast', ['type' => 'info', 'message' => __('public.checkout.all_removed')]);

            return $this->redirectRoute('tickets.select', $this->eventId);
        }
        $this->afterCartChange();
        $this->toast('success', __('public.checkout.removed'), duration: 1800);

        return null;
    }

    private function afterCartChange(): void
    {
        PublicSession::putCart($this->eventId, $this->qty);
        $this->syncHolders();
    }

    /** Expanded seat types in display order (normal → vip → parking). */
    private function seatTypes(): array
    {
        $types = [];
        foreach ($this->tickets as $t) {
            for ($i = 0; $i < (int) ($this->qty[$t['id']] ?? 0); $i++) {
                $types[] = $t['type'];
            }
        }

        return $types;
    }

    public function customerType(): ?string
    {
        return $this->seatTypes()[0] ?? null;
    }

    /** Re-shape extra holder slots, keeping typed data per type and order. */
    private function syncHolders(): void
    {
        $wanted = array_slice($this->seatTypes(), 1);
        $pool = [];
        foreach ($this->holders as $h) {
            $pool[$h['type']][] = $h;
        }
        $next = [];
        foreach ($wanted as $type) {
            $next[] = ! empty($pool[$type]) ? array_shift($pool[$type]) : [
                'uid' => Str::random(8), 'type' => $type, 'name' => '', 'phone' => '',
                'country_code' => '+974', 'nationality' => 'قطر', 'id_number' => '',
            ];
        }
        $this->holders = $next;
    }

    // ---- Field helpers --------------------------------------------------------------

    public function updatedCustomerNationality(string $value): void
    {
        // F-PUB-043: nationality resets the customer's dialling code.
        $this->customer['country_code'] = Countries::codeFor($value);
    }

    public function updated(string $name, mixed $value): void
    {
        if (preg_match('/^holders\.(\d+)\.nationality$/', $name, $m) && isset($this->holders[(int) $m[1]])) {
            // F-PUB-049 rebuild: default the code from nationality; it stays editable.
            $this->holders[(int) $m[1]]['country_code'] = Countries::codeFor((string) $value);
        }
        if (preg_match('/^(customer|holders\.\d+)\.phone$/', $name)) {
            $path = $name;
            $cc = data_get($this, str_replace('.phone', '.country_code', $path), '+974');
            data_set($this, $path, PhoneNumber::normalizeNational(PhoneNumber::normalizeDigits((string) $value), (string) $cc));
        }
        if (preg_match('/^(customer|holders\.\d+)\.id_number$/', $name)) {
            data_set($this, $name, PhoneNumber::normalizeDigits((string) $value));
        }
        if ($name === 'customer.email') {
            $this->customer['email'] = Text::extractEmail((string) $value);
        }
        if ($name === 'method' && ! in_array($value, $this->methods(), true)) {
            $this->method = 'sadad';
        }
    }

    public function copyMainTo(int $index): void
    {
        if (! isset($this->holders[$index])) {
            return;
        }
        $this->holders[$index]['phone'] = $this->customer['phone'];
        $this->holders[$index]['country_code'] = $this->customer['country_code'];
    }

    /** @return list<string> */
    public function methods(): array
    {
        return PublicSettings::get('public_cash_at_venue_enabled') ? ['sadad', 'cash_pos'] : ['sadad'];
    }

    public function toggleTerms(): void
    {
        $this->terms = ! $this->terms;
    }

    // ---- Validation -------------------------------------------------------------------

    protected function rules(): array
    {
        $nat = array_column(Countries::list('checkout'), 'ar');
        $person = fn (string $p) => [
            "$p.name" => ['required', 'string', 'min:2', 'max:100', 'not_regex:/[\x00-\x1F\x7F]/'],
            "$p.nationality" => ['required', 'string', 'in:'.implode(',', $nat)],
            "$p.country_code" => ['required', 'regex:/^\+\d{1,4}$/'],
            "$p.phone" => ['required', 'string', function ($attr, $value, $fail) use ($p) {
                $cc = (string) data_get($this, "$p.country_code", '+974');
                if (! PhoneNumber::isValidFor((string) $value, $cc)) {
                    $fail(__('validation.phone_country'));
                }
            }],
            "$p.id_number" => ['required', 'string', 'regex:/^[\p{L}\p{N}\-_\/ ]{3,30}$/u'],
        ];
        $rules = $person('customer') + ['customer.email' => ['nullable', 'string', 'max:254', 'regex:/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/']];
        foreach (array_keys($this->holders) as $i) {
            $rules += $person("holders.$i");
        }

        return $rules;
    }

    protected function validationAttributes(): array
    {
        $attrs = [
            'customer.name' => __('public.checkout.name'), 'customer.email' => __('public.checkout.email'),
            'customer.nationality' => __('public.checkout.nationality'), 'customer.phone' => __('public.checkout.phone_short'),
            'customer.country_code' => __('public.checkout.code'), 'customer.id_number' => __('public.checkout.id_number'),
        ];
        $map = ['name' => 'name', 'nationality' => 'nationality', 'phone' => 'phone_short', 'country_code' => 'code', 'id_number' => 'id_number'];
        foreach (array_keys($this->holders) as $i) {
            $n = __('public.checkout.holder_label', ['n' => $i + 2]);
            foreach ($map as $field => $label) {
                $attrs["holders.$i.$field"] = $n.' — '.__('public.checkout.'.$label);
            }
        }

        return $attrs;
    }

    protected function messages(): array
    {
        return [
            'customer.name.min' => __('public.checkout.name_short'),
            'holders.*.name.min' => __('public.checkout.name_short'),
        ];
    }

    private function signature(): string
    {
        return md5(json_encode(array_map(fn ($t) => [$t['id'], $t['price']], $this->tickets)));
    }

    // ---- Submit (F-PUB-053…061) -------------------------------------------------------

    public function submit(CreateOrderAction $create, SadadCheckoutBuilder $builder, FailPaymentAction $fail, PersonLimitGuard $limits)
    {
        $event = Event::query()->find($this->eventId);
        if (! $event) {
            $this->toast('error', __('public.checkout.no_event'));

            return $this->redirectRoute('home');
        }
        if (! in_array($this->method, $this->methods(), true)) {
            $this->method = 'sadad';
        }

        // Live stock & prices before anything else.
        $this->loadAvailability($event);
        if ($this->signature() !== $this->priceSignature) {
            $this->priceSignature = $this->signature();
            $this->toast('info', __('public.checkout.prices_updated'), duration: 7000);

            return null;
        }
        foreach ($this->tickets as $t) {
            $q = (int) ($this->qty[$t['id']] ?? 0);
            if ($q > 0 && $q > $t['remaining']) {
                $label = TicketKind::from($t['type'])->longLabel();
                $msg = $t['remaining'] <= 0 ? __('orders.sold_out_now') : __('orders.not_enough', ['n' => $t['remaining']]);
                $this->toast('error', __('public.checkout.unavailable_line', ['type' => $label, 'message' => $msg])."\n".__('public.checkout.unavailable_hint'), __('public.checkout.unavailable_title'), 6000);
                $this->logError('TICKETS_UNAVAILABLE', __('public.checkout.unavailable_title').' ('.$t['type'].'): '.$msg);

                return null;
            }
        }

        $total = $this->total();
        if ($this->method === 'sadad' && $total->lessThan(Money::of((string) $event->limit('min_online')))) {
            $this->toast('error', __('public.checkout.min_amount'));

            return null;
        }

        $this->customer['name'] = Text::clean($this->customer['name']);
        foreach ($this->holders as $i => $h) {
            $this->holders[$i]['name'] = Text::clean($h['name']);
        }
        try {
            $this->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->toast('error', __('public.checkout.fill_required'));
            $this->dispatch('scroll-to-error');

            throw $e;
        }

        if (! $this->terms) {
            $this->toast('error', __('public.checkout.terms_required'));
            $this->dispatch('scroll-to-terms');

            return null;
        }

        // F-PUB-056: per-person cap pre-check (the DB guard stays authoritative).
        $people = $this->peopleForLimit();
        $violations = rescue(fn () => $limits->violations($event->id, $people, (int) $event->limit('person')), [], false);
        if ($violations !== []) {
            foreach ($violations as $v) {
                $this->toast('error', $v['message'], duration: 7000);
            }
            $this->logError('TICKET_LIMIT', implode(' | ', array_column($violations, 'message')));

            return null;
        }

        $body = $this->payload();
        try {
            $data = app(OrderRequestValidator::class)->validate($body, strictPhone: true);
            $order = $create->execute($data, Actor::current(), [
                'idempotency_key' => $this->idempotencyKey,
                'locale' => app()->getLocale(),
                'terms_version' => rescue(fn () => app(BusinessSettings::class)->terms_version, null, false),
            ]);
        } catch (DomainError $e) {
            $this->idempotencyKey = (string) Str::uuid();

            return $this->fail($e);
        } catch (Throwable $e) {
            report($e);
            $this->idempotencyKey = (string) Str::uuid();
            $this->toast('error', __('errors.default'));
            $this->logError('CHECKOUT_FAILED', __('errors.default').' — '.Str::limit($e->getMessage(), 300));

            return null;
        }

        PublicSession::remember($order->booking_reference);
        session(['last_booking_reference' => $order->booking_reference]);
        PublicSession::clearCart();

        if ($order->isSadad()) {
            try {
                $payment = $builder->forOrder($order, 'create', request()->ip());
            } catch (Throwable $e) {
                Log::error('sadad checkout build failed', ['ref' => $order->booking_reference, 'error' => $e->getMessage()]);
                rescue(fn () => $fail->execute($order->booking_reference, 'checkout_request_failed'), report: false);
                $this->logError('CHECKOUT_FAILED', $this->unavailableMessage().' — '.Str::limit($e->getMessage(), 300), 'sadad gateway');
                session()->flash('toast', ['type' => 'error', 'message' => $this->unavailableMessage()]);

                return $this->redirectRoute('payment.result', ['ref' => $order->booking_reference]);
            }
            session()->put('sadad_payment.'.$order->booking_reference, $payment);
            $this->toast('info', __('public.checkout.redirecting'));

            return $this->redirectRoute('payment.redirect', ['reference' => $order->booking_reference]);
        }

        session()->flash('toast', ['type' => 'success', 'message' => __('public.checkout.created')]);

        return $this->redirectRoute('confirmation', ['reference' => $order->booking_reference]);
    }

    private function unavailableMessage(): string
    {
        return in_array('cash_pos', $this->methods(), true) ? __('errors.payment_unavailable_cash') : __('errors.payment_unavailable');
    }

    private function fail(DomainError $e)
    {
        $msg = $e->errorCode === 'payment_unavailable' ? $this->unavailableMessage() : $e->userMessage();
        if ($e->errorCode === 'validation_failed' && ! empty($e->extra['errors'])) {
            $msg = implode("\n", array_slice($e->extra['errors'], 0, 4));
        }
        if ($e->errorCode === 'event_not_available') {
            PublicSession::clearCart();
            session()->flash('toast', ['type' => 'error', 'message' => $msg]);

            return $this->redirectRoute('home');
        }
        $this->toast('error', $msg, duration: 7000);
        $this->logError($e->errorCode === 'ticket_limit_exceeded' ? 'TICKET_LIMIT' : 'CHECKOUT_FAILED', $msg.' — '.$e->errorCode, $e->errorCode);
        if (in_array($e->errorCode, ['insufficient_stock', 'invalid_tickets'], true)) {
            $event = Event::query()->find($this->eventId);
            if ($event) {
                $this->loadAvailability($event, expire: false);
            }
        }

        return null;
    }

    /** @return list<array{type:string,name:string,phone:string,id_number:string}> */
    private function peopleForLimit(): array
    {
        $types = $this->seatTypes();
        $out = [['type' => $types[0] ?? 'normal', 'name' => $this->customer['name'], 'phone' => $this->customer['country_code'].$this->customer['phone'], 'id_number' => $this->customer['id_number']]];
        foreach ($this->holders as $h) {
            $out[] = ['type' => $h['type'], 'name' => $h['name'], 'phone' => $h['country_code'].$h['phone'], 'id_number' => $h['id_number']];
        }

        return $out;
    }

    /** F-PUB-058 rebuild: the payload is built server-side from typed fields. */
    private function payload(): array
    {
        $idByType = [];
        foreach ($this->tickets as $t) {
            if (($this->qty[$t['id']] ?? 0) > 0) {
                $idByType[$t['type']] ??= $t['id'];
            }
        }
        $items = [];
        foreach ($this->tickets as $t) {
            if (($q = (int) ($this->qty[$t['id']] ?? 0)) > 0) {
                $items[] = ['ticket_id' => $t['id'], 'quantity' => $q];
            }
        }
        $c = $this->customer;
        $holders = [[
            'ticket_id' => $idByType[$this->customerType()] ?? null,
            'name' => $c['name'], 'phone' => $c['phone'], 'country_code' => $c['country_code'],
            'nationality' => $c['nationality'], 'id_number' => $c['id_number'],
        ]];
        foreach ($this->holders as $h) {
            $holders[] = [
                'ticket_id' => $idByType[$h['type']] ?? null,
                'name' => $h['name'], 'phone' => $h['phone'], 'country_code' => $h['country_code'],
                'nationality' => $h['nationality'], 'id_number' => $h['id_number'],
            ];
        }

        return [
            'customer' => $c,
            'items' => $items,
            'holders' => $holders,
            'payment_method' => $this->method,
            'source' => 'web',
            'terms_accepted' => $this->terms,
        ];
    }

    private function logError(string $code, string $message, ?string $classify = null): void
    {
        CheckoutErrorLogger::log(
            $code, $message, $this->eventId, $this->customer['name'] ?: null,
            trim($this->customer['country_code'].$this->customer['phone']) ?: null,
            $this->count(), $this->total()->toDecimal(), ['paymentMethod' => $this->method], $classify ? $message.' '.$classify : null,
        );
    }

    public function render()
    {
        $terms = rescue(fn () => app(BusinessSettings::class)->{app()->getLocale() === 'en' ? 'terms_en' : 'terms_ar'}, [], false) ?: __('public.terms_default');

        return view('livewire.public.checkout', [
            'total' => $this->total(),
            'countries' => Countries::list('checkout'),
            'codes' => Countries::list('checkout'),
            'termsLines' => is_array($terms) ? $terms : [],
            'customerType' => $this->customerType(),
        ])->title(__('public.checkout.title'));
    }
}
