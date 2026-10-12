<?php

namespace App\Enums;

enum RefundStatus: string
{
    use Concerns;

    case Requested = 'requested';
    case Processing = 'processing';
    case Completed = 'completed';
    case Rejected = 'rejected';

    public static function langKey(): string
    {
        return 'refund_status';
    }
}
