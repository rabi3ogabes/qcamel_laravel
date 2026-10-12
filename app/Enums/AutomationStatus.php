<?php

namespace App\Enums;

enum AutomationStatus: string
{
    use Concerns;

    case Queued = 'queued';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Failed = 'failed';
    case Timeout = 'timeout';

    public static function langKey(): string
    {
        return 'automation_status';
    }
}
