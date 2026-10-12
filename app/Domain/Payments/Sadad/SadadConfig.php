<?php

namespace App\Domain\Payments\Sadad;

use App\Settings\SadadSettings;
use App\Support\Text;
use Throwable;

/**
 * F-PAY-001…010: the single source of Sadad configuration. Secrets come from
 * .env (SADAD_*) or the encrypted settings group and never reach a browser.
 */
class SadadConfig
{
    public const CHECKOUT_URL = 'https://sadadqa.com/webpurchase';

    public const ALLOWED_HOSTS = ['sadadqa.com', 'www.sadadqa.com', 'secure.sadadqa.com'];

    public function __construct(private readonly ?SadadSettings $settings = null) {}

    private function settings(): ?SadadSettings
    {
        try {
            return $this->settings ?? app(SadadSettings::class);
        } catch (Throwable) {
            return null; // settings table missing (T-109: missing settings → nulls + auto)
        }
    }

    private static function clean(?string $v): ?string
    {
        $v = $v === null ? null : trim($v);

        return $v === '' ? null : $v;
    }

    public function merchantId(): ?string
    {
        return self::clean($this->settings()?->merchant_id) ?? self::clean(env('SADAD_MERCHANT_ID'));
    }

    public function checkoutSecret(): ?string
    {
        return self::clean(env('SADAD_CHECKOUT_SECRET')) ?? self::clean($this->settings()?->checkout_secret);
    }

    /** F-PAY-004: dedicated API login secret, falling back to the checkout secret. */
    public function apiLoginSecret(): ?string
    {
        return self::clean(env('SADAD_API_LOGIN_SECRET')) ?? self::clean($this->settings()?->api_login_secret) ?? $this->checkoutSecret();
    }

    public function apiLoginSecretIsDedicated(): bool
    {
        return (self::clean(env('SADAD_API_LOGIN_SECRET')) ?? self::clean($this->settings()?->api_login_secret)) !== null;
    }

    public function websiteDomain(): ?string
    {
        return self::clean($this->settings()?->website_domain) ?? self::clean(env('SADAD_WEBSITE_DOMAIN'));
    }

    /** F-PAY-006: auto | sandbox | live (unknown → auto, T-109). */
    public function environment(): string
    {
        $env = strtolower((string) (self::clean($this->settings()?->environment) ?? env('SADAD_ENV', 'auto')));

        return in_array($env, ['auto', 'sandbox', 'live'], true) ? $env : 'auto';
    }

    public function siteUrl(): ?string
    {
        return self::clean($this->settings()?->site_url) ?? self::clean(env('FRONTEND_URL'));
    }

    /** F-PAY-009 / T-083: merchant id + checkout secret + website all set. */
    public function isConfigured(): bool
    {
        return $this->merchantId() !== null && $this->checkoutSecret() !== null && $this->websiteDomain() !== null;
    }

    public function callbackUrl(): string
    {
        return rtrim(route('payments.sadad.callback'), '/');
    }

    public function webhookUrl(): string
    {
        return rtrim(route('payments.sadad.webhook'), '/');
    }

    /** F-PAY-007 return base: site_url then the order's safe origin. */
    public function returnBase(?string $returnOrigin): ?string
    {
        return Text::resolveReturnBase($this->siteUrl(), $returnOrigin);
    }

    public function apiBase(string $env): string
    {
        return $env === 'sandbox'
            ? rtrim((string) env('SADAD_API_SANDBOX_URL', 'https://api-sandbox.sadad.qa'), '/')
            : rtrim((string) env('SADAD_API_LIVE_URL', 'https://api-s.sadad.qa'), '/');
    }

    public function timeout(): int
    {
        return (int) env('SADAD_HTTP_TIMEOUT', 8);
    }

    /** T-114: only https on Sadad's hosts (look-alikes refused). */
    public static function isAllowedCheckoutUrl(string $url): bool
    {
        $parts = parse_url($url);

        return ($parts['scheme'] ?? '') === 'https'
            && in_array(strtolower($parts['host'] ?? ''), self::ALLOWED_HOSTS, true)
            && ! isset($parts['user'], $parts['pass']);
    }
}
