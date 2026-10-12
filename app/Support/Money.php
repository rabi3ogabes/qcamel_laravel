<?php

namespace App\Support;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Money in integer dirham-cents (R-035, Part 6.3 rule 5). Never floats.
 */
final class Money implements JsonSerializable, Stringable
{
    private function __construct(public readonly int $cents) {}

    public static function zero(): self
    {
        return new self(0);
    }

    public static function ofCents(int $cents): self
    {
        return new self($cents);
    }

    /**
     * Parse a decimal string/number ("150", "150.5", 150.004, "0.30").
     * Uses round-half-up on the third decimal (toCents of F-PAY-014).
     */
    public static function of(string|int|float|null $amount): self
    {
        $cents = self::toCents($amount);
        if ($cents === null) {
            throw new InvalidArgumentException('Invalid amount');
        }

        return new self($cents);
    }

    public static function tryOf(mixed $amount): ?self
    {
        $cents = self::toCents($amount);

        return $cents === null ? null : new self($cents);
    }

    public static function toCents(mixed $amount): ?int
    {
        if ($amount === null || is_bool($amount) || is_array($amount) || is_object($amount)) {
            return null;
        }
        if (is_string($amount)) {
            $amount = trim($amount);
            if ($amount === '' || ! is_numeric($amount)) {
                return null;
            }
        }
        if (is_int($amount)) {
            return $amount * 100;
        }

        $str = is_float($amount) ? self::floatToString($amount) : (string) $amount;
        if ($str === null || ! preg_match('/^(-?)(\d*)(?:\.(\d*))?(?:e[+-]?\d+)?$/i', $str)) {
            if (is_float($amount) && is_finite($amount)) {
                return (int) round($amount * 100);
            }

            return null;
        }
        if (stripos($str, 'e') !== false) {
            $f = (float) $str;

            return is_finite($f) ? (int) round($f * 100) : null;
        }

        preg_match('/^(-?)(\d*)(?:\.(\d*))?$/', $str, $m);
        $neg = $m[1] === '-';
        $int = $m[2] === '' ? '0' : $m[2];
        $frac = str_pad($m[3] ?? '', 3, '0');
        $cents = ((int) $int) * 100 + (int) substr($frac, 0, 2);
        if ((int) $frac[2] >= 5) {
            $cents += 1;
        }

        return $neg ? -$cents : $cents;
    }

    private static function floatToString(float $f): ?string
    {
        if (! is_finite($f)) {
            return null;
        }

        // 0.1 + 0.2 => "0.30000000000000004" -> 30 cents.
        return rtrim(rtrim(sprintf('%.10F', $f), '0'), '.') ?: '0';
    }

    public function add(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function subtract(self $other): self
    {
        return new self($this->cents - $other->cents);
    }

    public function multiply(int $qty): self
    {
        return new self($this->cents * $qty);
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function isPositive(): bool
    {
        return $this->cents > 0;
    }

    public function lessThan(self $other): bool
    {
        return $this->cents < $other->cents;
    }

    public function greaterThan(self $other): bool
    {
        return $this->cents > $other->cents;
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents;
    }

    /**
     * R-111: amounts match when equal in integer cents (±0.005).
     */
    public static function amountsMatch(mixed $a, mixed $b): bool
    {
        $ca = self::toCents($a);
        $cb = self::toCents($b);

        return $ca !== null && $cb !== null && $ca === $cb;
    }

    /** "nnn.nn" with a dot and no thousands separator (Sadad TXN_AMOUNT, DB decimal). */
    public function toDecimal(): string
    {
        $sign = $this->cents < 0 ? '-' : '';
        $abs = abs($this->cents);

        return $sign.intdiv($abs, 100).'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Display with thousands separator: "1,250.00". */
    public function format(): string
    {
        return number_format($this->cents / 100, 2, '.', ',');
    }

    public function toFloat(): float
    {
        return $this->cents / 100;
    }

    public function jsonSerialize(): string
    {
        return $this->toDecimal();
    }

    public function __toString(): string
    {
        return $this->toDecimal();
    }

    /** Format for UI: "150.00 ريال قطري" / "150.00 ر.ق". */
    public static function display(mixed $amount, bool $short = false): string
    {
        $m = self::tryOf($amount) ?? self::zero();

        return $m->format().' '.($short ? __('common.qar_short') : __('common.qar'));
    }
}
