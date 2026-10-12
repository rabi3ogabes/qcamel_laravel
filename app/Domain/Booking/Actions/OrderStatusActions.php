<?php

namespace App\Domain\Booking\Actions;

use App\Domain\Booking\Services\StockLedger;
use App\Domain\Shared\Actor;
use App\Domain\Shared\DomainError;
use App\Enums\ActivityType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentNote;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Events\OrderConfirmed;
use App\Events\OrderDeleted;
use App\Events\OrderStatusChanged;
use App\Events\PaymentConfirmed;
use App\Models\Order;
use App\Models\Refund;
use App\Support\Audit;
use App\Support\QatarClock;
use Illuminate\Support\Facades\DB;

/**
 * Admin order state changes, each in ONE transaction with stock moves
 * (R-046, R-057, F-ADM-ORD-026…030/033, D-015, D-029, T-019, T-053, T-054, T-057).
 */
class OrderStatusActions
{
    public function __construct(private readonly StockLedger $stock) {}

    /**
     * F-ORD-040 / R-057: cancel ("إلغاء وإعادة للبيع") → cancelled | already_inactive | order_not_found.
     */
    public function cancel(Order $order, ?string $note, Actor $actor, bool $refundRequired = false): string
    {
        if (! $actor->can('orders.cancel')) {
            throw DomainError::forbidden();
        }

        return DB::transaction(function () use ($order, $note, $actor, $refundRequired) {
            $locked = $this->stock->lockEvent($order->event_id);
            $o = Order::query()->whereKey($order->id)->lockForUpdate()->first();
            if (! $o) {
                return 'order_not_found';
            }
            if (! $o->payment_status->isActive()) {
                return 'already_inactive';
            }
            $from = $o->payment_status->value;
            $this->stock->releaseOrder($o, $locked);
            $o->forceFill([
                'payment_status' => PaymentStatus::Cancelled,
                'payment_note' => trim((string) $note) !== '' ? mb_substr(trim($note), 0, 255) : PaymentNote::AdminCancelled->value,
            ])->save();
            if ($refundRequired && $o->payment_method === PaymentMethod::Sadad && $o->payment_id) {
                Refund::create(['order_id' => $o->id, 'amount' => (string) $o->total_amount, 'reason' => 'customer_return', 'status' => RefundStatus::Requested, 'gateway_reference' => $o->payment_id, 'requested_by' => $actor->userId()]);
            }
            event(new OrderStatusChanged($o, $from, 'cancelled'));
            Audit::log(ActivityType::OrderCancelled, __('audit.order_cancelled', ['ref' => $o->booking_reference]), $o, ['booking_reference' => $o->booking_reference, 'from' => $from, 'note' => $o->payment_note], $actor);

            return 'cancelled';
        }, 3);
    }

    /**
     * F-ADM-ORD-027/029 + D-015: manual confirmation in one transaction.
     * Re-activates (re-reserves seats) when the order was cancelled; requires
     * a transaction number or reason for Sadad orders; never touches
     * check-in fields.
     */
    public function manualConfirm(Order $order, ?string $reference, Actor $actor, bool $notifyAutomation = true): Order
    {
        if (! $actor->can('orders.confirm')) {
            throw DomainError::forbidden();
        }
        if ($order->payment_method === PaymentMethod::Sadad && trim((string) $reference) === '') {
            throw DomainError::validation([__('orders.manual_reference_required')]);
        }

        return DB::transaction(function () use ($order, $reference, $actor, $notifyAutomation) {
            $locked = $this->stock->lockEvent($order->event_id);
            $o = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($o->payment_status === PaymentStatus::Confirmed) {
                return $o;
            }
            $from = $o->payment_status->value;
            if (! $o->payment_status->isActive()) {
                try {
                    $this->stock->reserveOrder($o, $locked); // T-053 reinstating may fail
                } catch (DomainError $e) {
                    throw new DomainError('insufficient_stock', 409, __('errors.insufficient_stock_reactivate'));
                }
            }
            $now = QatarClock::nowUtc();
            $o->forceFill([
                'payment_status' => PaymentStatus::Confirmed,
                'confirmed_at' => $now,
                'paid_at' => $o->paid_at ?? $now,
                'confirmed_by' => $actor->userId(),
                'payment_note' => null,
                'payment_error_reason' => null,
                'manual_payment_reference' => trim((string) $reference) !== '' ? mb_substr(trim($reference), 0, 120) : null,
            ])->save();
            event(new OrderStatusChanged($o, $from, 'confirmed'));
            event(new PaymentConfirmed($o, manual: true, notifyAutomation: $notifyAutomation));
            event(new OrderConfirmed($o));
            Audit::log(ActivityType::OrderConfirmed, __('audit.order_confirmed', ['ref' => $o->booking_reference]), $o, ['from' => $from, 'reference' => $o->manual_payment_reference], $actor);

            return $o;
        }, 3);
    }

    /**
     * T-053: generic status change used by admin tools — active→inactive
     * releases, inactive→active re-reserves or fails insufficient_stock (409).
     */
    public function setStatus(Order $order, PaymentStatus $to, Actor $actor, ?string $note = null): Order
    {
        if (! $actor->can('orders.cancel')) {
            throw DomainError::forbidden();
        }

        return DB::transaction(function () use ($order, $to, $note, $actor) {
            $locked = $this->stock->lockEvent($order->event_id);
            $o = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $from = $o->payment_status;
            if ($from === $to) {
                return $o;
            }
            if ($from->isActive() && ! $to->isActive()) {
                $this->stock->releaseOrder($o, $locked);
            } elseif (! $from->isActive() && $to->isActive()) {
                $this->stock->reserveOrder($o, $locked);
            }
            $o->forceFill(['payment_status' => $to, 'payment_note' => $to === PaymentStatus::Cancelled ? ($note ?: PaymentNote::AdminCancelled->value) : null])->save();
            event(new OrderStatusChanged($o, $from->value, $to->value));
            Audit::log($to === PaymentStatus::Cancelled ? ActivityType::OrderCancelled : ActivityType::OrderConfirmed, __('audit.order_status', ['ref' => $o->booking_reference, 'from' => $from->label(), 'to' => $to->label()]), $o, ['from' => $from->value, 'to' => $to->value], $actor);

            return $o;
        }, 3);
    }

    /**
     * F-ADM-ORD-033 / D-029 / T-126: soft delete; an active order releases its
     * seats once (status → cancelled first so a later purge never releases again).
     */
    public function delete(Order $order, Actor $actor): void
    {
        if (! $actor->can('orders.delete')) {
            throw DomainError::forbidden();
        }
        DB::transaction(function () use ($order, $actor) {
            $locked = $this->stock->lockEvent($order->event_id);
            $o = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($o->payment_status->isActive()) {
                $this->stock->releaseOrder($o, $locked);
                $o->forceFill(['payment_status' => PaymentStatus::Cancelled, 'payment_note' => PaymentNote::AdminCancelled->value])->save();
            }
            $o->forceFill(['deleted_by' => $actor->userId()])->save();
            $o->delete();
            event(new OrderDeleted($o->id, $o->event_id, $o->booking_reference));
            Audit::log(ActivityType::OrderDeleted, __('audit.order_deleted', ['ref' => $o->booking_reference]), $o, ['booking_reference' => $o->booking_reference, 'total' => (string) $o->total_amount], $actor);
        }, 3);
    }

    /** F-ADM-ORD-031: internal reconciliation marker. */
    public function toggleVerified(Order $order, Actor $actor): Order
    {
        if (! $actor->can('orders.verify')) {
            throw DomainError::forbidden();
        }
        $on = ! $order->sadad_manually_verified;
        $order->forceFill([
            'sadad_manually_verified' => $on,
            'sadad_verified_by' => $on ? $actor->userId() : null,
            'sadad_verified_at' => $on ? now() : null,
        ])->save();

        return $order;
    }

    /** Tier A5: refund handled flag. */
    public function markRefundHandled(Order $order, Actor $actor): Order
    {
        if (! $actor->can('orders.verify')) {
            throw DomainError::forbidden();
        }
        $order->forceFill(['refund_handled_at' => now(), 'refund_handled_by' => $actor->userId(), 'review_required_at' => null])->save();
        Refund::query()->where('order_id', $order->id)->whereIn('status', ['requested', 'processing'])
            ->update(['status' => RefundStatus::Completed->value, 'processed_by' => $actor->userId(), 'processed_at' => now()]);
        Audit::log(ActivityType::PaymentReview, __('audit.refund_handled', ['ref' => $order->booking_reference]), $order, [], $actor);

        return $order;
    }
}
