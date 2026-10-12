<?php

namespace App\Support;

/**
 * Input helpers (R-072, R-062, R-066, R-098, R-122).
 */
final class Text
{
    /** R-062 accepted booking reference for lookups. */
    public const REFERENCE_REGEX = '/^[A-Za-z0-9][A-Za-z0-9_-]{0,59}$/';

    /** R-059 new booking reference format. */
    public const NEW_REFERENCE_REGEX = '/^(QTR|POS)-[0-9]{1,2}-[0-9]{1,2}-[0-9]{4}-[0-9A-F]{12}$/';

    /** R-066 QR object name. */
    public const QR_NAME_REGEX = '/^[A-Za-z0-9][A-Za-z0-9_-]{0,79}$/';

    /** R-122 safe origin. */
    public const SAFE_ORIGIN_REGEX = '/^(https:\/\/[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+(:\d+)?|http:\/\/localhost(:\d+)?)$/i';

    /** R-072: collapse whitespace runs and trim. */
    public static function clean(?string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
    }

    /** R-072: control characters in the raw value. */
    public static function hasControlChars(?string $value): bool
    {
        return $value !== null && preg_match('/[\x{0000}-\x{001f}\x{007f}]/u', $value) === 1;
    }

    public static function isReference(mixed $value): bool
    {
        return is_string($value) && preg_match(self::REFERENCE_REGEX, $value) === 1;
    }

    /** Trim + validate a reference; null when unsafe. */
    public static function reference(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $v = trim($value);

        return self::isReference($v) ? $v : null;
    }

    public static function isSafeQrName(mixed $value): bool
    {
        return is_string($value) && preg_match(self::QR_NAME_REGEX, $value) === 1;
    }

    /** R-122 / F-PAY-007: normalised safe origin or null. */
    public static function safeOrigin(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $v = rtrim(trim($value), '/');
        if ($v === '' || preg_match(self::SAFE_ORIGIN_REGEX, $v) !== 1) {
            return null;
        }

        return $v;
    }

    /** First safe candidate (F-PAY-007 resolveReturnBase). */
    public static function resolveReturnBase(?string ...$candidates): ?string
    {
        foreach ($candidates as $c) {
            if (($safe = self::safeOrigin($c)) !== null) {
                return $safe;
            }
        }

        return null;
    }

    /** R-098: escape LIKE wildcards for a bound parameter. */
    public static function like(string $term): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
    }

    /** R-067 / T-118 scanned code normalisation. */
    public static function normalizeScannedCode(mixed $raw): string
    {
        if (! is_string($raw)) {
            return '';
        }
        $v = trim($raw);
        if ($v === '') {
            return '';
        }
        if (preg_match('/^https?:\/\//i', $v)) {
            $v = preg_replace('/[?#].*$/', '', $v) ?? $v;
            $v = rtrim($v, '/');
            $parts = explode('/', $v);
            $v = (string) end($parts);
            $v = preg_replace('/\.png$/i', '', $v) ?? $v;
            $v = rawurldecode($v);
        }

        return trim($v);
    }

    /** Limit a string to N characters (mb safe). */
    public static function limit(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_substr($value, 0, $max);
    }

    /** Extract the first e-mail-looking token (F-PUB-042). */
    public static function extractEmail(?string $value): string
    {
        $value = trim((string) $value);
        if (preg_match('/\S+@\S+/', $value, $m)) {
            return $m[0];
        }

        return $value;
    }
}
