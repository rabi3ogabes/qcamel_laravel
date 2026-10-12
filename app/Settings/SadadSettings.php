<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Part 4.4 SadadSettings (F-PAY-001…007). Secrets are encrypted and
 * never re-displayed; .env SADAD_* values take precedence when set.
 */
class SadadSettings extends Settings
{
    public ?string $merchant_id;

    public ?string $website_domain;

    public string $environment;

    public ?string $site_url;

    public ?string $checkout_secret;

    public ?string $api_login_secret;

    public ?string $support_pin;

    public static function group(): string
    {
        return 'sadad';
    }

    public static function encrypted(): array
    {
        return ['checkout_secret', 'api_login_secret', 'support_pin'];
    }
}
