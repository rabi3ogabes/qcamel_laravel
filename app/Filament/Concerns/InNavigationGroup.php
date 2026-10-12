<?php

namespace App\Filament\Concerns;

/**
 * Translated navigation group resolved at render time (after SetLocale),
 * matched against the panel's NavigationGroup labels. Set $navGroup to a
 * key of lang/{locale}/admin.php → nav.
 */
trait InNavigationGroup
{
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.'.static::$navGroup);
    }
}
