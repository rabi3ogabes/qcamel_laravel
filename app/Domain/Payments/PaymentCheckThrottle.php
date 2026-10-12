<?php

namespace App\Domain\Payments;

use App\Enums\PaymentNote;
use App\Support\QatarClock;
use Illuminate\Support\Facades\DB;

/**
 * F-PAY-045 claim_payment_check (R-117, T-059): one atomic conditional
 * UPDATE; allowed only for sadad, pending or failed-cancelled, created
 * within 3 days, ≥ 10 s since the last check.
 */
class PaymentCheckThrottle
{
    public function claim(string $reference): bool
    {
        $now = QatarClock::nowUtc();
        $affected = DB::table('orders')
            ->where('booking_reference', $reference)
            ->whereNull('deleted_at')
            ->where('payment_method', 'sadad')
            ->where(fn ($q) => $q->where('payment_status', 'pending')
                ->orWhere(fn ($c) => $c->where('payment_status', 'cancelled')->whereIn('payment_note', PaymentNote::FAILED_SET)))
            ->where('created_at', '>', $now->subDays((int) config('qcamelmc.payments.verify_window_days', 3)))
            ->where(fn ($q) => $q->whereNull('last_payment_check_at')
                ->orWhere('last_payment_check_at', '<', $now->subSeconds((int) config('qcamelmc.payments.verify_throttle_seconds', 10))))
            ->update(['last_payment_check_at' => $now]);

        return $affected === 1;
    }
}
