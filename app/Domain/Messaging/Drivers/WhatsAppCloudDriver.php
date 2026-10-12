<?php

namespace App\Domain\Messaging\Drivers;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Tier C7 (NEW): Meta WhatsApp Cloud API driver. Sends a template message
 * with the invoice link; enabled with WHATSAPP_DRIVER=cloud_api.
 * The n8n driver stays the default (identical payloads for existing flows).
 */
class WhatsAppCloudDriver implements WhatsAppChannel
{
    public function post(string $url, array $payload, int $timeoutSeconds = 8): array
    {
        $token = config('services.whatsapp.token');
        $phoneId = config('services.whatsapp.phone_number_id');
        $to = $payload['customer_phone_whatsapp'] ?? ($payload['holder']['phone'] ?? null);
        if (! $token || ! $phoneId || ! $to) {
            return ['ok' => false, 'error' => 'cloud_api_not_configured'];
        }
        $started = microtime(true);
        try {
            $res = Http::timeout($timeoutSeconds)->withToken($token)->post("https://graph.facebook.com/v20.0/{$phoneId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'template',
                'template' => [
                    'name' => config('services.whatsapp.template', 'booking_invoice'),
                    'language' => ['code' => 'ar'],
                    'components' => [[
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => (string) ($payload['customer_name'] ?? $payload['holder']['name'] ?? '')],
                            ['type' => 'text', 'text' => (string) ($payload['booking_reference'] ?? '')],
                        ],
                    ]],
                ],
            ]);

            return ['ok' => $res->successful(), 'status' => $res->status(), 'body' => mb_substr($res->body(), 0, 2000), 'json' => rescue(fn () => $res->json(), null, false), 'duration_ms' => (int) round((microtime(true) - $started) * 1000)];
        } catch (Throwable) {
            return ['ok' => false, 'error' => 'request_failed'];
        }
    }
}
