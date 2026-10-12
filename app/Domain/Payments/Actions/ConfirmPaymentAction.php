<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Booking\Services\StockLedger;
use App\Domain\Shared\DomainError;
use App\Enums\PaymentNote;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Events\OrderConfirmed;
use App\Events\OrderStatusChanged;
use App\Events\PaymentConfirmed;
use App\Models\Order;
use App\Models\Refund;
use App\Support\QatarClock;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * F-PAY-041 confirm_order_payment (R-056, R-115, T-046…T-052, D-012).
 * Returns: confirmed | already_confirmed | transaction_already_used |
 * paid_after_expiry_no_stock | paid_after_staff_cancel | order_not_found.
 * Only the call that flips the state returns "confirmed" (exactly-once side effects).
 */
class ConfirmPaymentAction
{
    public function __construct(private readonly StockLedger $stock) {}

    public function execute(string $reference, string $transactionNumber, ?string $actorUserId = null): string
    {
        if (trim($transactionNumber) === '') {
            throw new DomainError('invalid_request', 400, 'invalid_request');
        }
        $transactionNumber = trim($transactionNumber);

        try {
            return DB::transaction(function () use ($reference, $transactionNumber, $actorUserId) {
                $peek = Order::query()->where('booking_reference', $reference)->first(['id', 'event_id']);
                if (! $peek) {
                    return 'order_not_found';
                }
                // R-047 lock order: ticket types of the event first, then the order row.
                $locked = $this->stock->lockEvent($peek->event_id);
                $order = Order::query()->whereKey($peek->id)->lockForUpdate()->first();

                if ($order->payment_status === PaymentStatus::Confirmed) {
                    return 'already_confirmed'; // never overwrite payment_id (T-047)
                }
                if (Order::query()->where('payment_id', $transactionNumber)->whereKeyNot($order->id)->exists()) {
                    return 'transaction_already_used'; // T-048
                }

                $now = QatarClock::nowUtc();
                $previous = $order->payment_status->value;

                if ($order->payment_status === PaymentStatus::Cancelled) {
                    // D-012 / R-058: a final staff cancellation is never auto-confirmed.
                    if ($order->isFinalCancellation()) {
                        $order->forceFill([
                            'payment_id' => $transactionNumber, 'paid_at' => $now,
                            'payment_note' => PaymentNote::PaidAfterStaffCancel->value, 'review_required_at' => $now,
                        ])->save();
                        $this->refundTask($order, 'paid_after_staff_cancel', $actorUserId);

                        return 'paid_after_staff_cancel';
                    }
                    // R-056 late payment: re-reserve seats or keep cancelled + refund.
                    try {
                        $this->stock->reserveOrder($order, $locked);
                    } catch (DomainError $e) {
                        if ($e->errorCode !== 'insufficient_stock') {
                            throw $e;
                        }
                        $order->forceFill([
                            'payment_id' => $transactionNumber, 'paid_at' => $now,
                            'payment_note' => PaymentNote::PaidAfterExpiryNoStock->value, 'review_required_at' => $now,
                        ])->save();
                        $this->refundTask($order, 'paid_after_expiry_no_stock', $actorUserId);

                        return 'paid_after_expiry_no_stock';
                    }
                }

                $order->forceFill([
                    'payment_status' => PaymentStatus::Confirmed,
                    'payment_id' => $transactionNumber,
                    'paid_at' => $now,
                    'confirmed_at' => $now,
                    'payment_note' => null,
                    'payment_error_reason' => null,
                ])->save();

                event(new OrderStatusChanged($order, $previous, 'confirmed'));
                event(new PaymentConfirmed($order));
                event(new OrderConfirmed($order));

                return 'confirmed';
            }, 3);
        } catch (UniqueConstraintViolationException) {
            return 'transaction_already_used';
        }
    }

    private function refundTask(Order $order, string $reason, ?string $actorUserId): void
    {
        Refund::create([
            'order_id' => $order->id,
            'amount' => (string) $order->total_amount,
            'reason' => $reason,
            'status' => RefundStatus::Requested,
            'gateway' => 'sadad',
            'gateway_reference' => $order->payment_id,
            'requested_by' => $actorUserId,
        ]);
    }
}
