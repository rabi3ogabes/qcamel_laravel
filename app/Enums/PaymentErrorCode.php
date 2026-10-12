<?php

namespace App\Enums;

enum PaymentErrorCode: string
{
    use Concerns;

    case PaymentFailed = 'PAYMENT_FAILED';
    case CheckoutFailed = 'CHECKOUT_FAILED';
    case TicketsUnavailable = 'TICKETS_UNAVAILABLE';
    case TicketLimit = 'TICKET_LIMIT';

    public static function langKey(): string
    {
        return 'payment_error_code';
    }
}
