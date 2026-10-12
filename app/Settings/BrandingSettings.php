<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/** Part 4.4 BrandingSettings + R-167 brand strings + F-PUB-002 meta. */
class BrandingSettings extends Settings
{
    public ?string $logo_url;

    public ?string $header_bg_color;

    public ?string $header_bg_image_url;

    public ?string $hero_image_url;

    public ?string $hero_text;

    public ?string $before_footer_image_url;

    public ?string $copyright_text;

    public ?string $festival_name;

    public ?string $season_label;

    public ?string $og_image_url;

    public ?string $site_title_ar;

    public ?string $site_title_en;

    public ?string $site_description_ar;

    public ?string $site_description_en;

    public static function group(): string
    {
        return 'branding';
    }
}
