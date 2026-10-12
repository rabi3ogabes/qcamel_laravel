<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Display formatting (R-009, F-PUB-020, F-UI-017): Arabic month/weekday
 * names with Latin digits in Asia/Qatar, 12-hour times with ص/م.
 */
final class Formats
{
    private const AR_MONTHS = [1 => 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];

    private const AR_DAYS = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];

    private static function isAr(): bool
    {
        return app()->getLocale() !== 'en';
    }

    private static function q(DateTimeInterface|string|null $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->setTimezone(QatarClock::tz());
        }

        return QatarClock::local($value);
    }

    /** "الأحد، 14 فبراير 2026" (EEEE، d MMMM yyyy). */
    public static function longDate(DateTimeInterface|string|null $value, bool $weekday = true): string
    {
        $d = self::q($value);
        if (! $d) {
            return '—';
        }
        if (! self::isAr()) {
            return $d->format($weekday ? 'l, j F Y' : 'j F Y');
        }
        $s = $d->day.' '.self::AR_MONTHS[$d->month].' '.$d->year;

        return $weekday ? self::AR_DAYS[$d->dayOfWeek].'، '.$s : $s;
    }

    /** "14 فبراير" */
    public static function dayMonth(DateTimeInterface|string|null $value): string
    {
        $d = self::q($value);
        if (! $d) {
            return '—';
        }

        return self::isAr() ? $d->day.' '.self::AR_MONTHS[$d->month] : $d->format('j M');
    }

    public static function weekday(DateTimeInterface|string|null $value): string
    {
        $d = self::q($value);

        return $d ? (self::isAr() ? self::AR_DAYS[$d->dayOfWeek] : $d->format('l')) : '';
    }

    /** "dd/MM/yyyy" */
    public static function date(DateTimeInterface|string|null $value): string
    {
        return self::q($value)?->format('d/m/Y') ?? '—';
    }

    /** "dd/MM/yyyy - hh:mm م" */
    public static function dateTime(DateTimeInterface|string|null $value, string $sep = ' - '): string
    {
        $d = self::q($value);

        return $d ? $d->format('d/m/Y').$sep.self::time12($d->format('H:i')) : '—';
    }

    /** Long date + time: "الأحد، 14 فبراير 2026، 07:30 م". */
    public static function longDateTime(DateTimeInterface|string|null $value): string
    {
        $d = self::q($value);

        return $d ? self::longDate($d).'، '.self::time12($d->format('H:i')) : '—';
    }

    /**
     * F-PUB-020: "HH:MM[:SS]" → "hh:MM م|ص"; 00 → 12 ص; 12 → 12 م.
     */
    public static function time12(?string $time): string
    {
        if ($time === null || ! preg_match('/^(\d{1,2}):(\d{2})/', $time, $m)) {
            return '';
        }
        $h = (int) $m[1];
        $pm = $h >= 12;
        $h12 = $h % 12 === 0 ? 12 : $h % 12;
        $suffix = self::isAr() ? ($pm ? 'م' : 'ص') : ($pm ? 'PM' : 'AM');

        return str_pad((string) $h12, 2, '0', STR_PAD_LEFT).':'.$m[2].' '.$suffix;
    }

    /** Qatar time of an instant, 12-hour. */
    public static function timeOf(DateTimeInterface|string|null $value): string
    {
        $d = self::q($value);

        return $d ? self::time12($d->format('H:i')) : '—';
    }

    /** Relative "منذ 3 دقائق". */
    public static function ago(DateTimeInterface|string|null $value): string
    {
        $d = self::q($value);

        return $d ? $d->locale(app()->getLocale())->diffForHumans() : '—';
    }

    public static function money(mixed $amount, bool $short = false): string
    {
        return Money::display($amount, $short);
    }

    /** "1,250.00" */
    public static function amount(mixed $amount): string
    {
        return (Money::tryOf($amount) ?? Money::zero())->format();
    }
}
