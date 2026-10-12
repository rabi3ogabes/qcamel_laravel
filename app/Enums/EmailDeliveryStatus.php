<?php

namespace App\Enums;

enum EmailDeliveryStatus: string
{
    use Concerns;

    case Queued = 'queued';
    case Retried = 'retried';
    case Sent = 'sent';
    case Failed = 'failed';
    case Suppressed = 'suppressed';
    case Skipped = 'skipped';
    case Bounced = 'bounced';
    case Complained = 'complained';
    case Unsubscribed = 'unsubscribed';

    public static function langKey(): string
    {
        return 'email_status';
    }

    public function color(): string
    {
        return match ($this) {
            self::Queued => 'warning',
            self::Retried => 'info',
            self::Sent => 'success',
            self::Failed, self::Bounced, self::Complained => 'danger',
            self::Suppressed => 'warning',
            self::Skipped, self::Unsubscribed => 'gray',
        };
    }
}
