<?php

namespace App\Support;

/**
 * Accessors over config/countries.php (shared by checkout, POS, boards).
 */
final class Countries
{
    /** @return array<string, array{ar:string,en:string,flag:string,code:string,iso:string}> */
    public static function all(): array
    {
        $out = [];
        foreach (config('countries.all') as $iso => $c) {
            $out[$iso] = $c + ['iso' => $iso];
        }

        return $out;
    }

    /** @return list<array{ar:string,en:string,flag:string,code:string,iso:string}> */
    public static function list(string $set): array
    {
        $all = self::all();

        return array_values(array_map(fn ($iso) => $all[$iso], config("countries.$set", [])));
    }

    public static function byArabicName(?string $name): ?array
    {
        if ($name === null || $name === '') {
            return null;
        }
        foreach (self::all() as $c) {
            if ($c['ar'] === $name || strcasecmp($c['en'], $name) === 0 || $c['iso'] === strtoupper($name)) {
                return $c;
            }
        }

        return null;
    }

    /** Flag emoji for a stored nationality (F-ADM-INV-018: unknown → 🇶🇦). */
    public static function flag(?string $nationality, string $fallback = '🇶🇦'): string
    {
        return self::byArabicName($nationality ?: 'قطر')['flag'] ?? $fallback;
    }

    /** Dialling code for a nationality (fallback +974). */
    public static function codeFor(?string $nationality): string
    {
        return self::byArabicName($nationality)['code'] ?? '+974';
    }

    /** Localised display name of a stored (Arabic) nationality. */
    public static function label(?string $nationality): string
    {
        if ($nationality === null || $nationality === '') {
            return '—';
        }
        $c = self::byArabicName($nationality);
        if (! $c) {
            return $nationality;
        }

        return app()->getLocale() === 'en' ? $c['en'] : $c['ar'];
    }

    /** Codes list for selects (unique, POS order). */
    public static function codes(): array
    {
        return self::list('pos_codes');
    }
}
