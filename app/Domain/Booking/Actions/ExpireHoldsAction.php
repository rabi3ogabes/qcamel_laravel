<?php

namespace App\Domain\Booking\Actions;

use App\Domain\Booking\Services\StockLedger;
use App\Enums\PaymentNote;
use App\Enums\PaymentStatus;
use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Support\QatarClock;
use Illuminate\Support\Facades\DB;

/**
 * R-043/R-044 (F-ORD-034, F-PAY-050): pending SADAD orders past their hold
 * become cancelled/expired and release seats. Cash and confirmed orders
 * never expire here (cash only via cash_hold_policy, R-042). Idempotent.
 */
class ExpireHoldsAction
{
    public function __construct(private readonly StockLedger $stock) {}

    /** Run inside an existing transaction (lazy, per event) or standalone. */
    public function forEvent(string $eventId): int
    {
        $ids = Order::query()->expiredHolds()->where('event_id', $eventId)->orderBy('id')->pluck('id');
        if ($ids->isEmpty()) {
            return 0;
        }

        return $this->expire($ids->all(), $eventId);
    }

    /** All events (scheduled every minute, R-044). */
    public function all(): int
    {
        $count = 0;
        $byEvent = Order::query()->expiredHolds()->orderBy('event_id')->orderBy('id')->get(['id', 'event_id'])->groupBy('event_id');
        foreach ($byEvent as $eventId => $orders) {
            $count += DB::transaction(fn () => $this->expire($orders->pluck('id')->all(), $eventId), 3);
        }

        return $count;
    }

    /**
     * @param  list<string>  $orderIds
     */
    private function expire(array $orderIds, ?string $eventId = null): int
    {
        $count = 0;
        $runner = function () use ($orderIds, $eventId, &$count) {
            $now = QatarClock::nowUtc();
            $eventIds = $eventId ? [$eventId] : Order::query()->whereIn('id', $orderIds)->distinct()->orderBy('event_id')->pluck('event_id')->all();
            // Lock order (R-047): ticket types first, then orders — same as CreateOrderAction.
            $lockedByEvent = [];
            foreach ($eventIds as $eid) {
                $lockedByEvent[$eid] = $this->stock->lockEvent($eid);
            }
            $orders = Order::query()->whereIn('id', $orderIds)
                ->where('payment_status', PaymentStatus::Pending->value)
                ->where('payment_method', 'sadad')
                ->whereNotNull('payment_expires_at')
                ->where('payment_expires_at', '<', $now)
                ->orderBy('event_id')->orderBy('id')
                ->lockForUpdate()
                ->get();
            foreach ($orders as $order) {
                $lockedByEvent[$order->event_id] ??= $this->stock->lockEvent($order->event_id);
                $this->stock->releaseOrder($order, $lockedByEvent[$order->event_id]);
                $order->forceFill(['payment_status' => PaymentStatus::Cancelled, 'payment_note' => PaymentNote::Expired->value])->save();
                event(new OrderStatusChanged($order, PaymentStatus::Pending->value, PaymentStatus::Cancelled->value));
                $count++;
            }
        };
        DB::transactionLevel() > 0 ? $runner() : DB::transaction($runner, 3);

        return $count;
    }

    /** R-042 NEW: expire unpaid web cash bookings after the event cut-off. */
    public function cashAfterCutoff(): int
    {
        $count = 0;
        Order::query()
            ->where('payment_status', PaymentStatus::Pending->value)
            ->where('payment_method', 'cash_pos')
            ->where('source', 'web')
            ->with('event')
            ->chunkById(200, function ($orders) use (&$count) {
                foreach ($orders as $order) {
                    if ($order->event && $order->event->isExpired('sales')) {
                        DB::transaction(function () use ($order, &$count) {
                            $locked = $this->stock->lockEvent($order->event_id);
                            $o = Order::query()->whereKey($order->id)->lockForUpdate()->first();
                            if ($o && $o->payment_status === PaymentStatus::Pending) {
                                $this->stock->releaseOrder($o, $locked);
                                $o->forceFill(['payment_status' => PaymentStatus::Cancelled, 'payment_note' => PaymentNote::Expired->value])->save();
                                event(new OrderStatusChanged($o, 'pending', 'cancelled'));
                                $count++;
                            }
                        }, 3);
                    }
                }
            });

        return $count;
    }
}
