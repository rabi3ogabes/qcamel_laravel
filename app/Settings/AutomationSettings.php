<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Part 4.4 AutomationSettings (R-152…R-156, F-NTF-022, F-NTF-039).
 * Webhook URLs are secret-ish: stored encrypted, write-only in the UI
 * (D-002, D-014); .env values (N8N_WEBHOOK_URL…) act as defaults.
 */
class AutomationSettings extends Settings
{
    public ?string $webhook_url;

    public ?string $email_webhook_url;

    public bool $webhook_enabled;

    public bool $email_webhook_enabled;

    public bool $payment_failed_email_enabled;

    public int $auto_invoice_interval_seconds;

    public ?string $last_invoice_sent_at;

    public int $invoice_batch_min;

    public int $invoice_batch_max;

    public int $invoice_send_delay_min;

    public int $invoice_send_delay_max;

    /** @var list<string> Y-m-d (R-155, F-ADM-INV-023) */
    public array $invoice_excluded_dates;

    /** F-NTF-044 rebuild: server-side auto-send switch. */
    public bool $invoice_auto_send_enabled;

    public static function group(): string
    {
        return 'automation';
    }

    public static function encrypted(): array
    {
        return ['webhook_url', 'email_webhook_url'];
    }

    /** R-158: enabled WhatsApp URL or null. */
    public function whatsappUrl(): ?string
    {
        if (! $this->webhook_enabled) {
            return null;
        }
        $url = trim((string) ($this->webhook_url ?: config('qcamelmc.notifications.whatsapp_url')));

        return $url === '' ? null : $url;
    }

    /** R-158: enabled e-mail URL (falls back to the WhatsApp URL) or null. */
    public function emailUrl(): ?string
    {
        if (! $this->email_webhook_enabled) {
            return null;
        }
        $url = trim((string) ($this->email_webhook_url ?: config('qcamelmc.notifications.email_url')));
        if ($url === '') {
            $main = trim((string) ($this->webhook_url ?: config('qcamelmc.notifications.whatsapp_url')));

            return $main === '' ? null : $main;
        }

        return $url;
    }

    public function rawWhatsappUrlSet(): bool
    {
        return trim((string) ($this->webhook_url ?: config('qcamelmc.notifications.whatsapp_url'))) !== '';
    }
}
