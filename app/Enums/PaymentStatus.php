<?php

namespace App\Enums;

enum PaymentStatus: string
{
    use Concerns;

    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    public static function langKey(): string
    {
        return 'payment_status';
    }

    /** Statuses that hold seats (R-045). */
    public function isActive(): bool
    {
        return $this !== self::Cancelled;
    }

    public function color(): string
    {
        return match ($this) {
            self::Confirmed => 'success',
            self::Pending => 'warning',
            self::Cancelled => 'danger',
        };
    }
}
