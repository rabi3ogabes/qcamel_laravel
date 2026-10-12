<?php

namespace App\Enums;

enum InvoiceDispatchState: string
{
    use Concerns;

    case Pending = 'pending';
    case Sent = 'sent';
    case EventDone = 'event_done';
    case OnHold = 'on_hold';

    public static function langKey(): string
    {
        return 'invoice_dispatch_state';
    }
}
