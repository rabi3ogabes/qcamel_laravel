<?php

namespace App\Enums;

/** R-176 capacity alert levels. */
enum CapacityLevel: string
{
    use Concerns;

    case Critical = 'critical';
    case Warning = 'warning';
    case Nearing = 'nearing';
    case Normal = 'normal';

    public static function langKey(): string
    {
        return 'capacity_level';
    }

    public static function forPercent(int $pct): self
    {
        return match (true) {
            $pct >= 100 => self::Critical,
            $pct >= 90 => self::Warning,
            $pct >= 80 => self::Nearing,
            default => self::Normal,
        };
    }

    /** pct = round(confirmed / capacity × 100), 0 when capacity 0. */
    public static function percent(int $confirmed, int $capacity): int
    {
        return $capacity <= 0 ? 0 : (int) round($confirmed / $capacity * 100);
    }

    public function color(): string
    {
        return match ($this) {
            self::Critical => 'danger',
            self::Warning => 'orange',
            self::Nearing => 'warning',
            self::Normal => 'success',
        };
    }
}
