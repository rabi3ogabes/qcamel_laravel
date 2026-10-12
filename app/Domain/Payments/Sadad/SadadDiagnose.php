<?php

namespace App\Domain\Payments\Sadad;

use App\Settings\AutomationSettings;

/**
 * F-PAY-051 / F-ADM-SDD-001…009 readiness diagnostics (T-084, D-074):
 * Arabic messages (English in the en locale), never reveals the secret.
 */
class SadadDiagnose
{
    public function __construct(private readonly SadadConfig $config, private readonly SadadApiClient $api) {}

    /** @return array{success:bool, ready:bool, environment:?string, callbackUrl:string, webhookUrl:string, checks:list<array{id:string,status:string,message:string}>} */
    public function run(): array
    {
        $checks = [];
        $merchant = $this->config->merchantId();
        $secret = $this->config->checkoutSecret();
        $website = $this->config->websiteDomain();

        $checks[] = $merchant ? ['id' => 'merchant_id', 'status' => 'ok', 'message' => __('sadad.chk_merchant_ok', ['id' => $merchant])]
            : ['id' => 'merchant_id', 'status' => 'error', 'message' => __('sadad.chk_merchant_missing')];
        $checks[] = $secret ? ['id' => 'secret', 'status' => 'ok', 'message' => __('sadad.chk_secret_ok', ['n' => mb_strlen($secret)])]
            : ['id' => 'secret', 'status' => 'error', 'message' => __('sadad.chk_secret_missing')];
        if (! $website) {
            $checks[] = ['id' => 'website', 'status' => 'error', 'message' => __('sadad.chk_website_missing')];
        } elseif (preg_match('#[/:\s]#', $website)) {
            $checks[] = ['id' => 'website', 'status' => 'warn', 'message' => __('sadad.chk_website_warn', ['domain' => $website])];
        } else {
            $checks[] = ['id' => 'website', 'status' => 'ok', 'message' => __('sadad.chk_website_ok', ['domain' => $website])];
        }

        $environment = null;
        if ($merchant && $secret && $website) {
            $det = $this->api->detectEnvironment();
            if ($det['ok']) {
                $environment = $det['environment'];
                $checks[] = ['id' => 'api_login', 'status' => 'ok', 'message' => $environment === 'sandbox' ? __('sadad.chk_login_sandbox') : __('sadad.chk_login_live')];
                if (! $this->config->apiLoginSecretIsDedicated()) {
                    $checks[] = ['id' => 'api_login_secret', 'status' => 'warn', 'message' => __('sadad.chk_login_fallback')];
                }
                $lookup = $this->api->lookupTransaction('DIAGNOSTIC-PING', null, $environment);
                $checks[] = $lookup->kind === 'unavailable'
                    ? ['id' => 'api_lookup', 'status' => 'error', 'message' => __('sadad.chk_lookup_failed', ['error' => (string) $lookup->error])]
                    : ['id' => 'api_lookup', 'status' => 'ok', 'message' => __('sadad.chk_lookup_ok')];
            } else {
                $checks[] = ['id' => 'api_login', 'status' => 'error', 'message' => __('sadad.chk_login_failed', ['errors' => implode(', ', $det['errors'] ?? [])])];
            }
        } else {
            $checks[] = ['id' => 'api_login', 'status' => 'error', 'message' => __('sadad.chk_skipped')];
        }

        $callback = $this->config->callbackUrl();
        $webhook = $this->config->webhookUrl();
        $checks[] = ['id' => 'callback_url', 'status' => str_starts_with($callback, 'https://') ? 'ok' : 'warn', 'message' => __('sadad.chk_callback', ['url' => $callback])];
        $checks[] = ['id' => 'webhook_registration', 'status' => 'warn', 'message' => __('sadad.chk_webhook', ['url' => $webhook])];
        $checks[] = app(AutomationSettings::class)->whatsappUrl()
            ? ['id' => 'automation', 'status' => 'ok', 'message' => __('sadad.chk_automation_ok')]
            : ['id' => 'automation', 'status' => 'warn', 'message' => __('sadad.chk_automation_missing')];
        $site = $this->config->siteUrl();
        $checks[] = $site ? ['id' => 'site_url', 'status' => 'ok', 'message' => __('sadad.chk_site_ok', ['url' => $site])]
            : ['id' => 'site_url', 'status' => 'warn', 'message' => __('sadad.chk_site_missing')];

        return [
            'success' => true,
            'ready' => collect($checks)->doesntContain('status', 'error'),
            'environment' => $environment,
            'callbackUrl' => $callback,
            'webhookUrl' => $webhook,
            'checks' => $checks,
        ];
    }
}
