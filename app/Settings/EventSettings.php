<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/** Part 4.4 EventSettings: customer-facing current event + season start (R-178). */
class EventSettings extends Settings
{
    public ?string $current_event_id;

    public ?string $season_start_date;

    public static function group(): string
    {
        return 'events';
    }
}
