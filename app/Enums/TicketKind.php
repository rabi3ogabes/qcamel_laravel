<?php

namespace App\Enums;

/**
 * Ticket type kinds (3.12 TicketType). Named TicketKind to avoid clashing
 * with the TicketType model (table ticket_types).
 */
enum TicketKind: string
{
    use Concerns;

    case Vip = 'vip';
    case Normal = 'normal';
    case Parking = 'parking';

    public static function langKey(): string
    {
        return 'ticket_kind';
    }

    /** R-029 public display order normal → vip → parking. */
    public static function displayOrder(): array
    {
        return [self::Normal, self::Vip, self::Parking];
    }

    public function sortWeight(): int
    {
        return match ($this) {
            self::Normal => 1,
            self::Vip => 2,
            self::Parking => 3,
        };
    }

    public function isAdmission(): bool
    {
        return $this !== self::Parking;
    }

    /** Long label "تذكرة دخول VIP" (F-PUB-029). */
    public function longLabel(): string
    {
        return __('enums.ticket_kind_long.'.$this->value);
    }

    /** API/client label "VIP / الدخول العام / المواقف" (insufficient_stock suffix). */
    public function apiLabel(): string
    {
        return __('enums.ticket_kind_api.'.$this->value);
    }

    /** Sadad product line names (R-101) — fixed Arabic regardless of locale. */
    public function sadadItemName(): string
    {
        return match ($this) {
            self::Vip => 'تذكرة VIP',
            self::Normal => 'تذكرة دخول عامة',
            self::Parking => 'تصريح مواقف',
        };
    }

    public function defaultDescription(): string
    {
        return __('enums.ticket_kind_desc.'.$this->value);
    }

    public function icon(): string
    {
        return match ($this) {
            self::Vip => 'crown',
            self::Normal => 'users',
            self::Parking => 'car',
        };
    }
}
