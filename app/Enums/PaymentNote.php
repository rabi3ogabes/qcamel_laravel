<?php

namespace App\Enums;

/**
 * Known values of orders.payment_note (R-057, R-058, 3.12). Free staff
 * notes ("released by admin", "returned by admin") are stored verbatim.
 */
enum PaymentNote: string
{
    use Concerns;

    case PaymentFailed = 'payment_failed';
    case Expired = 'expired';
    case PaidAfterExpiryNoStock = 'paid_after_expiry_no_stock';
    case AdminCancelled = 'admin_cancelled';
    case CheckoutRequestFailed = 'checkout_request_failed';
    case PaidAfterStaffCancel = 'paid_after_staff_cancel';

    /** Notes that make a cancelled order "failed" (re-checkable, R-058, F-PAY-043). */
    public const FAILED_SET = ['payment_failed', 'expired', 'paid_after_expiry_no_stock'];

    public static function langKey(): string
    {
        return 'payment_note';
    }

    public static function isFailedNote(?string $note): bool
    {
        return in_array($note, self::FAILED_SET, true);
    }

    /** Localised label for any stored note (free text shown verbatim). */
    public static function labelFor(?string $note): ?string
    {
        if ($note === null || $note === '') {
            return null;
        }

        return self::tryFrom($note)?->label() ?? $note;
    }
}
