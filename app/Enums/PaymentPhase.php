<?php

namespace App\Enums;

enum PaymentPhase: string
{
    use Concerns;

    case Checking = 'checking';
    case Confirmed = 'confirmed';
    case Failed = 'failed';
    case Delayed = 'delayed';
    case NotFound = 'not_found';
    case Cash = 'cash';

    public static function langKey(): string
    {
        return 'payment_phase';
    }
}
