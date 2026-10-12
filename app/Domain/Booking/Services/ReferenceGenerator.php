<?php

namespace App\Domain\Booking\Services;

use App\Models\Event;
use App\Models\Order;
use App\Support\QatarClock;
use RuntimeException;

/**
 * R-059…R-064: booking reference "<QTR|POS>-<D>-<M>-<YYYY>-<12 HEX>" with
 * the EVENT date in Qatar (unpadded), and ticket codes "<ref>-TKT<NN>".
 */
class ReferenceGenerator
{
    public function make(string $prefix, Event $event): string
    {
        $d = QatarClock::local($event->event_date);
        $base = $prefix.'-'.$d->day.'-'.$d->month.'-'.$d->year.'-';
        for ($i = 0; $i < (int) config('qcamelmc.orders.reference_retries', 5); $i++) {
            $ref = $base.strtoupper(bin2hex(random_bytes(6)));
            if (! Order::withTrashed()->where('booking_reference', $ref)->exists()) {
                return $ref;
            }
        }

        throw new RuntimeException('Could not generate a unique booking reference');
    }

    /** R-064 without the original lpad truncation (D-088). */
    public static function ticketCode(string $reference, int $ordinal): string
    {
        return $reference.'-TKT'.str_pad((string) $ordinal, 2, '0', STR_PAD_LEFT);
    }
}
