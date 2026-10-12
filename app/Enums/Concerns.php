<?php

namespace App\Enums;

/**
 * Shared enum helpers: localized labels from lang/{ar,en}/enums.php
 * (Arabic is the source of truth, Part 3.12) and option arrays.
 */
trait Concerns
{
    abstract public static function langKey(): string;

    public function label(?string $locale = null): string
    {
        return __('enums.'.static::langKey().'.'.$this->value, [], $locale);
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn ($c) => $c->value, self::cases());
    }

    public static function sqlIn(): string
    {
        return "'".implode("','", self::values())."'";
    }
}
