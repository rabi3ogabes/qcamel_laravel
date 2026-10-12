<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/** Part 4.4 AnalyticsSettings (F-ADM-SET-030/031, D-082 IP masking). */
class AnalyticsSettings extends Settings
{
    public bool $clarity_enabled;

    public ?string $clarity_project_id;

    public bool $mask_ip;

    public bool $require_consent;

    public static function group(): string
    {
        return 'analytics';
    }

    public function clarityActive(): bool
    {
        return $this->clarity_enabled && $this->clarity_project_id !== null
            && preg_match('/^[a-z0-9]{4,20}$/i', $this->clarity_project_id) === 1;
    }
}
