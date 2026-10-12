<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Booking\Services\StockLedger;
use App\Enums\PaymentNote;
use App\Enums\PaymentStatus;
use App\Events\OrderStatusChanged;
use App\Events\PaymentFailed;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * F-PAY-042 mark_order_payment_failed (T-049): only pending → cancelled
 * (note payment_failed), seats released once; else not_pending.
 */
class FailPaymentAction
{
    public function __construct(private readonly StockLedger $stock) {}

    public function execute(string $reference, ?string $reason = null): string
    {
        return DB::transaction(function () use ($reference, $reason) {
            $peek = Order::query()->where('booking_reference', $reference)->first(['id', 'event_id']);
            if (! $peek) {
                return 'order_not_found';
            }
            $locked = $this->stock->lockEvent($peek->event_id);
            $order = Order::query()->whereKey($peek->id)->lockForUpdate()->first();
            if ($order->payment_status !== PaymentStatus::Pending) {
                return 'not_pending';
            }
            $this->stock->releaseOrder($order, $locked);
            $order->forceFill([
                'payment_status' => PaymentStatus::Cancelled,
                'payment_note' => PaymentNote::PaymentFailed->value,
                'payment_error_reason' => trim((string) $reason) !== '' ? mb_substr(trim((string) $reason), 0, 500) : 'payment_failed',
            ])->save();
            event(new OrderStatusChanged($order, 'pending', 'cancelled'));
            event(new PaymentFailed($order, $order->payment_error_reason));

            return 'failed';
        }, 3);
    }
}
