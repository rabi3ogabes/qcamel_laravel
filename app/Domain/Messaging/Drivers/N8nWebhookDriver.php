<?php

namespace App\Domain\Messaging\Drivers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Default n8n driver: POST JSON to the configured webhook (F-NTF-024,
 * R-159, T-108). Refuses non-http(s) URLs; never throws.
 */
class N8nWebhookDriver implements WhatsAppChannel
{
    public function post(string $url, array $payload, int $timeoutSeconds = 8): array
    {
        if (preg_match('#^https?://#i', $url) !== 1) {
            return ['ok' => false, 'error' => 'invalid_webhook_url'];
        }
        $started = microtime(true);
        try {
            $res = Http::timeout($timeoutSeconds)->acceptJson()->asJson()->post($url, $payload);
            $out = ['ok' => $res->successful(), 'status' => $res->status(), 'body' => mb_substr((string) $res->body(), 0, 2000), 'json' => rescue(fn () => $res->json(), null, false)];
        } catch (ConnectionException $e) {
            $timeout = str_contains(strtolower($e->getMessage()), 'timed out') || str_contains(strtolower($e->getMessage()), 'timeout');
            $out = ['ok' => false, 'error' => $timeout ? 'timeout' : 'request_failed', 'timeout' => $timeout];
        } catch (Throwable $e) {
            $out = ['ok' => false, 'error' => 'request_failed'];
        }
        $out['duration_ms'] = (int) round((microtime(true) - $started) * 1000);

        return $out;
    }
}
