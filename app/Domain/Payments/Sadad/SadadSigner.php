<?php

namespace App\Domain\Payments\Sadad;

/**
 * F-PAY-013 / F-PAY-023 (R-102, R-106, T-075, T-078).
 *
 * signature = UPPER(hex(sha256(secret . concat(values sorted by key (byte order)))))
 * excluding `signature`, `checksumhash` and `productdetail*`. Values are
 * stringified the PHP way and never URL-encoded.
 *
 * SECURITY NOTE (keep): a valid callback checksum is NOT proof of payment.
 * Sadad hashes secret + values with no separators and our server signs
 * requests containing customer-chosen text, so a customer can re-split a
 * request signature into a forged "paid" callback that verifies. Payments are
 * only ever confirmed by the merchant API (PaymentDecisionEngine).
 */
final class SadadSigner
{
    public static function phpString(mixed $value): string
    {
        if ($value === null || $value === false) {
            return '';
        }
        if ($value === true) {
            return '1';
        }
        if (is_float($value)) {
            // JSON numbers as-is (150 not 150.0)
            return rtrim(rtrim(sprintf('%.14F', $value), '0'), '.') ?: '0';
        }
        if (is_array($value)) {
            return json_encode($value) ?: '';
        }

        return (string) $value;
    }

    /** @param array<string, mixed> $params */
    private static function valuesByKey(array $params, callable $skip): string
    {
        $keys = array_values(array_filter(array_map('strval', array_keys($params)), fn ($k) => ! $skip($k)));
        sort($keys, SORT_STRING); // byte order: uppercase before lowercase

        return implode('', array_map(fn ($k) => self::phpString($params[$k]), $keys));
    }

    /** @param array<string, mixed> $params */
    public static function sign(array $params, string $secret): string
    {
        $skip = fn (string $k) => $k === 'signature' || $k === 'checksumhash' || str_starts_with($k, 'productdetail');

        return strtoupper(hash('sha256', $secret.self::valuesByKey($params, $skip)));
    }

    /** @param array<string, mixed> $payload */
    public static function verifyChecksum(array $payload, ?string $secret): bool
    {
        if ($payload === [] || $secret === null || $secret === '') {
            return false;
        }
        $received = strtolower(trim(self::phpString($payload['checksumhash'] ?? null)));
        if ($received === '') {
            return false;
        }
        $expected = hash('sha256', $secret.self::valuesByKey($payload, fn (string $k) => $k === 'checksumhash'));

        return hash_equals($expected, $received);
    }
}
