<?php

namespace App\Enums;

enum PaymentOutcome: string
{
    use Concerns;

    case Confirmed = 'confirmed';
    case AlreadyConfirmed = 'already_confirmed';
    case TransactionAlreadyUsed = 'transaction_already_used';
    case PaidAfterExpiryNoStock = 'paid_after_expiry_no_stock';
    case PaidAfterStaffCancel = 'paid_after_staff_cancel';
    case Failed = 'failed';
    case NotPending = 'not_pending';
    case Pending = 'pending';
    case Review = 'review';
    case Noop = 'noop';
    case OrderNotFound = 'order_not_found';

    public static function langKey(): string
    {
        return 'payment_outcome';
    }
}
