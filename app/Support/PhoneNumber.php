<?php

namespace App\Support;

/**
 * Phone helpers (R-075, R-085…R-088, R-104, R-161, F-PUB-045, T-082, T-117).
 */
final class PhoneNumber
{
    /** R-086 per-country rules: national digits length + allowed first digits. */
    public const COUNTRY_RULES = [
        '+974' => ['length' => 8, 'starts' => ['3', '5', '6', '7']],
        '+966' => ['length' => 9, 'starts' => ['5']],
        '+971' => ['length' => 9, 'starts' => ['5']],
        '+965' => ['length' => 8, 'starts' => ['5', '6', '9']],
        '+973' => ['length' => 8, 'starts' => ['3']],
    ];

    /** R-085 UI maxLength by code. */
    public const MAX_LENGTH = ['+974' => 8, '+966' => 9, '+971' => 9, '+965' => 8, '+973' => 8];

    /** R-087: Eastern-Arabic and Persian digits → Latin. */
    public static function normalizeDigits(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return strtr($value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
    }

    public static function digits(?string $value): string
    {
        return preg_replace('/\D+/', '', self::normalizeDigits($value)) ?? '';
    }

    /**
     * R-104 / F-PAY-015 toSadadMobile; also the WhatsApp/n8n international form.
     * Returns digits with country code and no "+".
     */
    public static function international(?string $phone, ?string $countryCode = '+974'): string
    {
        $cc = self::digits($countryCode);
        if ($cc === '') {
            $cc = '974';
        }
        $phone = self::normalizeDigits($phone ?? '');
        $digits = self::digits($phone);
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (str_starts_with(ltrim($phone), '+')) {
            return $digits;
        }
        if (str_starts_with($digits, $cc) && strlen($digits) - strlen($cc) >= 7) {
            return $digits;
        }

        return $cc.ltrim($digits, '0');
    }

    /** T-117 toWhatsAppNumber: empty → null. */
    public static function toWhatsApp(?string $phone, ?string $countryCode = '+974'): ?string
    {
        if (self::digits($phone) === '') {
            return null;
        }

        return self::international($phone, $countryCode);
    }

    /** R-088 E.164 "+97455512345" (null when empty). */
    public static function e164(?string $phone, ?string $countryCode = '+974'): ?string
    {
        $intl = self::toWhatsApp($phone, $countryCode);

        return $intl === null ? null : '+'.$intl;
    }

    /**
     * F-PUB-045 input normalisation for the national-number field:
     * digits only, drop a typed country prefix (and leading 0 for KSA/UAE).
     */
    public static function normalizeNational(?string $phone, string $countryCode): string
    {
        $d = self::digits($phone);
        $cc = self::digits($countryCode);
        if (str_starts_with($d, '00'.$cc)) {
            $d = substr($d, 2 + strlen($cc));
        } elseif ($cc !== '' && str_starts_with($d, $cc) && strlen($d) > (self::COUNTRY_RULES[$countryCode]['length'] ?? 15)) {
            $d = substr($d, strlen($cc));
        }
        if (in_array($countryCode, ['+966', '+971'], true)) {
            $d = ltrim($d, '0');
        }

        return $d;
    }

    /** R-075: only [+\d\s\-()] and 6–15 digits. */
    public static function isValidGeneric(?string $phone): bool
    {
        $phone = self::normalizeDigits($phone ?? '');
        if (! preg_match('/^[+\d\s\-()]+$/', $phone)) {
            return false;
        }
        $n = strlen(self::digits($phone));

        return $n >= 6 && $n <= 15;
    }

    /** R-086 per-country validation of a national number (others → R-075). */
    public static function isValidFor(?string $national, string $countryCode): bool
    {
        $rule = self::COUNTRY_RULES[$countryCode] ?? null;
        if ($rule === null) {
            return self::isValidGeneric($national);
        }
        $d = self::digits($national);

        return strlen($d) === $rule['length'] && in_array($d[0] ?? '', $rule['starts'], true);
    }

    /** R-076 country code format. */
    public static function isValidCountryCode(?string $code): bool
    {
        return is_string($code) && preg_match('/^\+\d{1,4}$/', $code) === 1;
    }

    /** R-039 helper: "+code " prefix typed in front of a phone. */
    public static function parsePrefix(?string $phone): ?string
    {
        if ($phone !== null && preg_match('/^\s*(\+\d{1,4})\s/', $phone, $m)) {
            return $m[1];
        }

        return null;
    }

    /** Remove a typed "+code " prefix from a stored phone (F-QR-022). */
    public static function stripPrefix(?string $phone): string
    {
        return trim(preg_replace('/^\s*\+\d{1,4}\s+/', '', (string) $phone) ?? '');
    }

    /** Mask for public display "+974 •••• 2345" (F-ORD-038 rebuild). */
    public static function mask(?string $phone, ?string $countryCode = null): string
    {
        $d = self::digits($phone);
        if ($d === '') {
            return '—';
        }
        $tail = substr($d, -4);

        return trim(($countryCode ?? '').' •••• '.$tail);
    }

    /** R-021 person key: id:<digits> (≥6) else ph:<last 8> (≥8) else null. */
    public static function personKey(?string $idNumber, ?string $phone): ?string
    {
        $id = self::digits($idNumber);
        if (strlen($id) >= 6) {
            return 'id:'.$id;
        }
        $ph = self::digits($phone);
        if (strlen($ph) >= 8) {
            return 'ph:'.substr($ph, -8);
        }

        return null;
    }
}
