<?php

namespace App\Enums;

enum PublicPaymentStatus: string
{
    use Concerns;

    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Failed = 'failed';

    public static function langKey(): string
    {
        return 'public_payment_status';
    }
}
