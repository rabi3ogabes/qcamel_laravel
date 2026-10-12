<?php

namespace App\Enums;

enum PaymentDecisionAction: string
{
    use Concerns;

    case Confirm = 'confirm';
    case Fail = 'fail';
    case Pending = 'pending';
    case Review = 'review';
    case Noop = 'noop';
    case None = 'none';

    public static function langKey(): string
    {
        return 'payment_decision';
    }
}
