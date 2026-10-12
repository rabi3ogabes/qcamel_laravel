<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Business clock (R-001, R-002). Storage is UTC; every business date
 * ("today", day ranges, cut-offs, grouping) is computed in Asia/Qatar.
 * Fakeable in tests through Carbon::setTestNow() / $this->travelTo().
 */
final class QatarClock
{
    public static function tz(): string
    {
        return (string) config('qcamelmc.timezone', 'Asia/Qatar');
    }

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::tz());
    }

    public static function nowUtc(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC');
    }

    /** Qatar calendar date "Y-m-d" for today. */
    public static function today(): string
    {
        return self::now()->toDateString();
    }

    /** Convert any instant to Qatar local time. */
    public static function local(DateTimeInterface|string|null $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::parse($value, 'UTC')->setTimezone(self::tz());
    }

    /** Qatar calendar date of an instant. */
    public static function dateOf(DateTimeInterface|string|null $value): ?string
    {
        return self::local($value)?->toDateString();
    }

    /**
     * R-002: day D = [D 00:00+03:00, D+1 00:00+03:00) as UTC instants.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function dayRange(string $date): array
    {
        $start = CarbonImmutable::parse($date, self::tz())->startOfDay();

        return [$start->utc(), $start->addDay()->utc()];
    }

    /** Range of N Qatar days ending today (inclusive). */
    public static function lastDaysRange(int $days): array
    {
        $end = self::now()->startOfDay()->addDay();

        return [$end->subDays($days)->utc(), $end->utc()];
    }

    /** Build a UTC instant from a Qatar date + time ("Y-m-d", "H:i[:s]"). */
    public static function at(string $date, string $time): CarbonImmutable
    {
        return CarbonImmutable::parse($date.' '.$time, self::tz())->utc();
    }
}
