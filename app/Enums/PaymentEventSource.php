<?php

namespace App\Enums;

enum PaymentEventSource: string
{
    use Concerns;

    case Callback = 'callback';
    case Webhook = 'webhook';
    case Poll = 'poll';
    case Admin = 'admin';
    case Reconcile = 'reconcile';

    public static function langKey(): string
    {
        return 'payment_event_source';
    }
}
