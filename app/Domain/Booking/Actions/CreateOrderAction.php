<?php

namespace App\Domain\Booking\Actions;

use App\Domain\Booking\Data\OrderRequestData;
use App\Domain\Booking\Services\PersonLimitGuard;
use App\Domain\Booking\Services\ReferenceGenerator;
use App\Domain\Booking\Services\StockLedger;
use App\Domain\Payments\Sadad\SadadConfig;
use App\Domain\Shared\Actor;
use App\Domain\Shared\DomainError;
use App\Enums\OrderSource;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Events\OrderCreated;
use App\Models\Customer;
use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosUser;
use App\Models\TicketHolder;
use App\Models\TicketType;
use App\Settings\EventSettings;
use App\Settings\FeatureFlagSettings;
use App\Support\Money;
use App\Support\PhoneNumber;
use App\Support\QatarClock;
use App\Support\Text;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The single transactional path through which every booking is born
 * (F-ORD-001…027, R-012…R-040, T-021…T-045). Server is the authority for
 * prices, totals, statuses, limits, stock and references (Part 6.3).
 */
class CreateOrderAction
{
    public function __construct(
        private readonly StockLedger $stock,
        private readonly PersonLimitGuard $limits,
        private readonly ReferenceGenerator $references,
        private readonly ExpireHoldsAction $expiry,
        private readonly SadadConfig $sadad,
    ) {}

    /**
     * @param  array{return_origin?:?string, idempotency_key?:?string, locale?:?string, terms_version?:?string}  $context
     */
    public function execute(OrderRequestData $data, Actor $actor, array $context = []): Order
    {
        // Idempotency-Key (Tier A2): replay returns the original order.
        if (! empty($context['idempotency_key'])) {
            $existing = Order::query()->where('idempotency_key', $context['idempotency_key'])->first();
            if ($existing) {
                return $existing;
            }
        }

        $isPos = $data->source === OrderSource::Pos;

        // F-ORD-005 / T-045: POS requires a staff identity and cash only.
        if ($isPos) {
            if (! $actor->isStaff() || ! $actor->can('pos.sell')) {
                throw DomainError::unauthorized();
            }
            if ($data->paymentMethod !== PaymentMethod::CashPos) {
                throw DomainError::validation([__('orders.v.pos_cash_only')], 'Point-of-sale orders are cash orders');
            }
            if ($data->posUserId && ! PosUser::query()->whereKey($data->posUserId)->exists()) {
                throw DomainError::validation([__('orders.v.cashier_invalid')]);
            }
            if (! $data->posUserId && app(FeatureFlagSettings::class)->pos_cashier_required) {
                throw DomainError::validation([__('orders.v.cashier_required')]);
            }
        } elseif ($data->posUserId) {
            // Web orders never carry a cashier (T-045).
            throw DomainError::validation([__('orders.v.cashier_invalid')]);
        }

        // F-PUB-050/052 rebuild: the terms gate is enforced by the server, not the button.
        if (! $isPos && ! $data->termsAccepted && config('qcamelmc.orders.require_terms', true)) {
            throw new DomainError('terms_required', 422, 'terms_required');
        }

        // T-039 / R-099: gateway readiness BEFORE anything is reserved.
        if ($data->paymentMethod === PaymentMethod::Sadad && ! $this->sadad->isConfigured()) {
            throw new DomainError('payment_unavailable', 503, 'Online payment is not available right now');
        }

        $customerE164 = PhoneNumber::e164($data->customer['phone'], $data->customer['country_code']);
        $returnOrigin = Text::safeOrigin($context['return_origin'] ?? null); // R-122 / T-041

        return DB::transaction(function () use ($data, $actor, $context, $isPos, $customerE164, $returnOrigin) {
            // F-ORD-009: resolve tickets → exactly one event.
            $types = TicketType::query()->whereIn('id', $data->ticketIds())->get(['id', 'event_id']);
            if ($types->count() !== count($data->items) || $types->pluck('event_id')->unique()->count() !== 1) {
                throw new DomainError('invalid_tickets', 422, 'invalid_tickets');
            }
            $eventId = $types->first()->event_id;

            // R-047: lock all ticket rows of the event ORDER BY id FOR UPDATE.
            $locked = $this->stock->lockEvent($eventId);
            $byId = $locked->keyBy('id');

            // T-032 / R-044: lazy expiry scoped to this event.
            $this->expiry->forEvent($eventId);
            // expiry may have released seats on the locked rows: refresh counters.
            foreach ($locked as $row) {
                $row->sold_quantity = (int) DB::table('ticket_types')->where('id', $row->id)->value('sold_quantity');
            }

            $event = Event::query()->withTrashed()->findOrFail($eventId);

            // F-ORD-010 / R-027 / R-004: web sales only on open events.
            if (! $isPos) {
                $current = app(EventSettings::class)->current_event_id;
                if (! $event->is_active || $event->is_archived || $event->trashed() || ! $event->canPurchaseTickets()
                    || ($current && $current !== $event->id)) {
                    throw new DomainError('event_not_available', 409, 'event_not_available');
                }
            } elseif ($event->trashed()) {
                throw new DomainError('event_not_available', 409, 'event_not_available');
            }

            // Lines in ticket-id order with prices from the DB (R-030).
            $lines = collect($data->items)
                ->map(fn ($it) => ['row' => $byId[$it['ticket_id']], 'quantity' => $it['quantity']])
                ->sortBy(fn ($l) => $l['row']->id)
                ->values();

            // R-012/R-013: web per-order caps.
            if (! $isPos) {
                $admission = $lines->filter(fn ($l) => $l['row']->type->isAdmission())->sum('quantity');
                $parking = $lines->filter(fn ($l) => ! $l['row']->type->isAdmission())->sum('quantity');
                if ($admission > (int) $event->limit('admission') || $parking > (int) $event->limit('parking')) {
                    throw new DomainError('quantity_limit_exceeded', 422, 'quantity_limit_exceeded');
                }
            } else {
                // F-POS-009 rebuild: POS admission cap enforced server-side too.
                $admission = $lines->filter(fn ($l) => $l['row']->type->isAdmission())->sum('quantity');
                if ($admission > (int) config('qcamelmc.orders.pos_max_admission')) {
                    throw new DomainError('quantity_limit_exceeded', 422, 'quantity_limit_exceeded');
                }
            }

            // F-ORD-011: stock per line → insufficient_stock:<type> 409.
            foreach ($lines as $l) {
                if ($l['row']->available_quantity - $l['row']->sold_quantity < $l['quantity']) {
                    throw DomainError::insufficientStock($l['row']->type->value);
                }
            }

            // R-030…R-034 totals.
            $total = Money::zero();
            foreach ($lines as $l) {
                $total = $total->add(Money::of((string) $l['row']->price)->multiply($l['quantity']));
            }
            if ($data->paymentMethod === PaymentMethod::Sadad && $total->lessThan(Money::of((string) $event->limit('min_online')))) {
                throw new DomainError('below_minimum_amount', 422, 'below_minimum_amount');
            }

            // R-026 / D-019: ≤ 4 open unpaid orders per normalised phone (web only).
            if (! $isPos && $customerE164) {
                $open = Order::query()
                    ->where('payment_status', PaymentStatus::Pending->value)
                    ->where(fn ($q) => $q->whereNull('payment_expires_at')->orWhere('payment_expires_at', '>', QatarClock::nowUtc()))
                    ->whereHas('customer', fn ($q) => $q->where('phone_e164', $customerE164))
                    ->count();
                if ($open >= (int) config('qcamelmc.orders.max_open_unpaid_orders')) {
                    throw new DomainError('too_many_pending_orders', 429, 'Please complete or cancel your existing bookings first');
                }
            }

            // R-019: holders must match items (defence in depth after validation).
            $holderCountByTicket = collect($data->holders)->countBy('ticket_id');
            foreach ($lines as $l) {
                if (($holderCountByTicket[$l['row']->id] ?? 0) !== $l['quantity']) {
                    throw new DomainError('holders_mismatch', 422, 'holders_mismatch');
                }
            }

            // R-020…R-025 per-person cap with in-request counting (D-031).
            $limitHolders = array_map(fn ($h) => [
                'name' => $h['name'], 'id_number' => $h['id_number'], 'phone' => $h['phone'],
                'type' => $byId[$h['ticket_id']]->type->value,
            ], $data->holders);
            $this->limits->assert($eventId, $limitHolders, (int) $event->limit('person'));

            // R-038 customer snapshot row.
            $customer = Customer::create([
                'name' => $data->customer['name'],
                'email' => $data->customer['email'] ?? '',
                'phone' => $data->customer['phone'],
                'country_code' => $data->customer['country_code'] ?: '+974',
                'nationality' => $data->customer['nationality'] ?: null,
                'id_number' => $data->customer['id_number'] ?: null,
            ]);

            // R-031 label = most expensive line (price desc, id).
            $label = $lines->sortBy([fn ($a, $b) => Money::of((string) $b['row']->price)->cents <=> Money::of((string) $a['row']->price)->cents, fn ($a, $b) => strcmp($a['row']->id, $b['row']->id)])->first()['row']->type;

            $now = QatarClock::nowUtc();
            $attributes = [
                'customer_id' => $customer->id,
                'event_id' => $eventId,
                'source' => $data->source,
                'ticket_type' => $label,
                'quantity' => count($data->holders),
                'total_amount' => $total->toDecimal(),
                'payment_method' => $data->paymentMethod,
                'payment_status' => $isPos ? PaymentStatus::Confirmed : PaymentStatus::Pending,
                'return_origin' => $isPos ? null : $returnOrigin,
                'locale' => in_array($context['locale'] ?? 'ar', ['ar', 'en'], true) ? ($context['locale'] ?? 'ar') : 'ar',
                'idempotency_key' => $context['idempotency_key'] ?? null,
                'terms_accepted_at' => $data->termsAccepted ? $now : null,
                'terms_version' => $data->termsAccepted ? ($context['terms_version'] ?? null) : null,
            ];
            if ($isPos) {
                // R-037 / T-026
                $attributes += [
                    'paid_at' => $now, 'confirmed_at' => $now, 'confirmed_by' => $actor->userId(),
                    'pos_user_id' => $data->posUserId, 'kiosk_device_id' => $actor->deviceId(),
                    'n8n_response_message' => Order::POS_MARKER, 'n8n_responded_at' => $now,
                    'automation_status' => 'pos',
                ];
            } elseif ($data->paymentMethod === PaymentMethod::Sadad) {
                $attributes['payment_expires_at'] = $now->addMinutes((int) $event->limit('hold')); // R-041
            }

            $order = $this->insertOrder($attributes, $isPos ? 'POS' : 'QTR', $event);

            // order_items snapshot (Tier A2 / D-033).
            foreach ($lines as $l) {
                $unit = Money::of((string) $l['row']->price);
                OrderItem::create([
                    'order_id' => $order->id, 'ticket_type_id' => $l['row']->id, 'type' => $l['row']->type,
                    'unit_price' => $unit->toDecimal(), 'quantity' => $l['quantity'],
                    'line_total' => $unit->multiply($l['quantity'])->toDecimal(), 'price_source' => 'order',
                ]);
            }

            // Holders in input order with codes <ref>-TKTnn (R-064) and R-039 country codes.
            foreach ($data->holders as $i => $h) {
                $row = $byId[$h['ticket_id']];
                $cc = $h['country_code'] ?: (PhoneNumber::parsePrefix($h['phone']) ?? ($customer->country_code ?: '+974'));
                TicketHolder::create([
                    'order_id' => $order->id,
                    'ticket_type_id' => $row->id,
                    'ticket_type' => $row->type,
                    'unit_price' => (string) $row->price,
                    'name' => $h['name'],
                    'phone' => $h['phone'],
                    'country_code' => $cc,
                    'nationality' => $h['nationality'] ?? '',
                    'id_number' => $h['id_number'] ?: null,
                    'position' => $i + 1,
                    'qr_code' => ReferenceGenerator::ticketCode($order->booking_reference, $i + 1),
                ]);
            }

            // Reserve seats (R-046: +1 per holder on an active order).
            $this->stock->reserve($eventId, $lines->mapWithKeys(fn ($l) => [$l['row']->type->value => $l['quantity']])->all(), $locked, withType: true);

            event(new OrderCreated($order->fresh(), $actor->userId(), $actor->deviceId()));

            return $order;
        }, (int) config('qcamelmc.orders.deadlock_retries', 3));
    }

    /** R-060: unique reference with retries on collision. */
    private function insertOrder(array $attributes, string $prefix, Event $event): Order
    {
        $attempts = 0;
        while (true) {
            $attempts++;
            try {
                return Order::create($attributes + ['booking_reference' => $this->references->make($prefix, $event)]);
            } catch (QueryException $e) {
                if ($attempts >= (int) config('qcamelmc.orders.reference_retries', 5) || ! str_contains(strtolower($e->getMessage()), 'booking_reference')) {
                    throw $e;
                }
            }
        }
    }
}
