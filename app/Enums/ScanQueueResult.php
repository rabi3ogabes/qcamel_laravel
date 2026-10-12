<?php

namespace App\Enums;

enum ScanQueueResult: string
{
    use Concerns;

    case Admitted = 'admitted';
    case AlreadyUsed = 'already_used';
    case Rejected = 'rejected';
    case Pending = 'pending';

    public static function langKey(): string
    {
        return 'scan_queue_result';
    }
}
