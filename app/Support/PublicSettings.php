<?php

namespace App\Support;

use App\Settings\AnalyticsSettings;
use App\Settings\BrandingSettings;
use App\Settings\ContactSettings;
use App\Settings\EventSettings;
use App\Settings\FeatureFlagSettings;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Public settings read model (F-PUB-007, F-ADM-SET-042, R-185, T-012, T-013,
 * D-084): explicit allow-list, cached 300 s, flushed on every settings save.
 * Never contains secrets, webhook URLs, site_url or merchant data.
 */
final class PublicSettings
{
    public const CACHE_KEY = 'public.settings';

    public const ALLOWED = [
        'logo_url', 'header_bg_color', 'header_bg_image_url', 'hero_image_url', 'hero_text', 'before_footer_image_url',
        'copyright_text', 'festival_name', 'season_label', 'og_image_url', 'site_title_ar', 'site_title_en',
        'site_description_ar', 'site_description_en', 'admin_phone', 'support_whatsapp', 'current_event_id',
        'clarity_enabled', 'clarity_project_id', 'clarity_require_consent', 'public_cash_at_venue_enabled',
    ];

    /** @return array<string, mixed> */
    public static function current(): array
    {
        return Cache::remember(self::CACHE_KEY, (int) config('qcamelmc.cache.public_settings_seconds', 300), function () {
            try {
                $b = app(BrandingSettings::class);
                $c = app(ContactSettings::class);
                $e = app(EventSettings::class);
                $a = app(AnalyticsSettings::class);
                $f = app(FeatureFlagSettings::class);
            } catch (Throwable) {
                return self::defaults();
            }

            return array_intersect_key([
                'logo_url' => $b->logo_url,
                'header_bg_color' => self::color($b->header_bg_color),
                'header_bg_image_url' => $b->header_bg_image_url,
                'hero_image_url' => $b->hero_image_url,
                'hero_text' => (string) $b->hero_text,
                'before_footer_image_url' => $b->before_footer_image_url,
                'copyright_text' => $b->copyright_text ?: 'جميع الحقوق محفوظة',
                'festival_name' => $b->festival_name,
                'season_label' => $b->season_label,
                'og_image_url' => $b->og_image_url,
                'site_title_ar' => $b->site_title_ar,
                'site_title_en' => $b->site_title_en,
                'site_description_ar' => $b->site_description_ar,
                'site_description_en' => $b->site_description_en,
                'admin_phone' => $c->publish_admin_phone ? $c->admin_phone : null,
                'support_whatsapp' => $c->support_whatsapp ?: ($c->publish_admin_phone ? $c->admin_phone : null),
                'current_event_id' => $e->current_event_id,
                'clarity_enabled' => $a->clarityActive(),
                'clarity_project_id' => $a->clarityActive() ? $a->clarity_project_id : null,
                'clarity_require_consent' => $a->require_consent,
                'public_cash_at_venue_enabled' => $f->public_cash_at_venue_enabled,
            ], array_flip(self::ALLOWED));
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::current()[$key] ?? $default;
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget('public.events');
    }

    /** F-PUB-008 default sand; only hex colours are honoured (F-ADM-SET-009). */
    public static function color(?string $c): string
    {
        $c = trim((string) $c);

        return preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $c) ? $c : '#D4B78A';
    }

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return array_fill_keys(self::ALLOWED, null) + ['header_bg_color' => '#D4B78A', 'copyright_text' => 'جميع الحقوق محفوظة', 'hero_text' => ''];
    }
}
