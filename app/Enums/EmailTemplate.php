<?php

namespace App\Enums;

enum EmailTemplate: string
{
    use Concerns;

    case BookingInvoice = 'booking-invoice';
    case PaymentFailed = 'payment-failed';
    case AdminSaleAlert = 'admin-sale-alert';
    case LoginAlert = 'login-alert';

    public static function langKey(): string
    {
        return 'email_template';
    }
}
