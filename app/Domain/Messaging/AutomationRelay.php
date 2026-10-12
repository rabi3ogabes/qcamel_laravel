<?php

namespace App\Domain\Messaging;

use App\Domain\Messaging\Drivers\WhatsAppChannel;
use App\Domain\Shared\Actor;
use App\Enums\AutomationStatus;
use App\Events\OrderAutomationUpdated;
use App\Models\AutomationDispatch;
use App\Models\Order;
use App\Models\TicketHolder;
use App\Settings\AutomationSettings;

/**
 * Server-side automation relay replacing `send-to-webhook` and every
 * browser→n8n post (D-014, F-NTF-028…036, R-157…R-160, T-097…T-100).
 * The browser only sends {action, order, holder?}; payloads are built here.
 */
class AutomationRelay
{
    public const ACTIONS = ['payment_confirmed', 'order_created', 'send_invoice', 'send_email', 'send_tickets', 'send_single_ticket', 'send_ticket', 'auto_invoice'];

    public function __construct(private readonly WhatsAppChannel $driver, private readonly AutomationSettings $settings) {}

    /**
     * @return array{success:bool, message?:string, error?:string, status?:int, solution?:string, details?:mixed, hint?:string, code?:string}
     */
    public function send(string $action, Order $order, ?TicketHolder $holder = null, ?Actor $actor = null, ?int $totalQuantity = null, bool $markSending = true): array
    {
        $isEmail = $action === 'send_email';
        $url = $isEmail ? $this->settings->emailUrl() : $this->settings->whatsappUrl();
        if (! $url) {
            return [
                'success' => false, 'code' => 'webhook_not_configured',
                'error' => $isEmail ? 'Email webhook URL not configured' : 'Webhook URL not configured',
                'hint' => $isEmail ? 'Please configure the email webhook URL in admin settings' : 'Please configure the webhook URL in admin settings',
            ];
        }

        $payload = match ($action) {
            'payment_confirmed' => N8nPayloads::paymentConfirmed($order),
            'order_created' => N8nPayloads::orderCreated($order),
            'send_invoice' => N8nPayloads::whatsappInvoice($order, $totalQuantity),
            'auto_invoice' => N8nPayloads::whatsappInvoice($order, $totalQuantity, withIds: false),
            'send_email' => N8nPayloads::emailInvoice($order),
            'send_tickets' => N8nPayloads::bookingTickets($order),
            'send_single_ticket' => N8nPayloads::singleTicket($holder ?? $order->holders()->firstOrFail()),
            'send_ticket' => N8nPayloads::sendTicket($order),
            default => throw new \InvalidArgumentException('Unknown automation action'),
        };

        $timeout = in_array($action, ['payment_confirmed', 'order_created'], true)
            ? (int) config('qcamelmc.notifications.webhook_timeout', 8)
            : (int) config('qcamelmc.notifications.relay_timeout', 10);
        $res = $this->driver->post($url, $payload, $timeout);

        $status = match (true) {
            $res['ok'] => AutomationStatus::Accepted,
            ($res['timeout'] ?? false) => AutomationStatus::Timeout,
            default => AutomationStatus::Failed,
        };
        AutomationDispatch::create([
            'order_id' => $order->id,
            'channel' => $isEmail ? 'email_webhook' : 'whatsapp',
            'action' => $action,
            'status' => $status,
            'attempt' => AutomationDispatch::query()->where('order_id', $order->id)->where('action', $action)->count() + 1,
            'http_status' => $res['status'] ?? null,
            'duration_ms' => $res['duration_ms'] ?? null,
            'target_host' => parse_url($url, PHP_URL_HOST) ?: null,
            'payload_hash' => hash('sha256', json_encode($payload) ?: ''),
            'response_excerpt' => isset($res['body']) ? mb_substr((string) $res['body'], 0, 500) : ($res['error'] ?? null),
            'dispatched_by' => $actor?->userId(),
        ]);

        if ($res['ok']) {
            $pending = $isEmail ? Order::SENDING_EMAIL : Order::SENDING_WHATSAPP;
            if ($markSending && in_array($action, ['send_invoice', 'send_email', 'send_tickets', 'send_single_ticket', 'send_ticket', 'payment_confirmed'], true)) {
                // R-157 / T-099: mark "sending…" only when the automation accepted it.
                Order::query()->whereKey($order->id)->update([
                    'n8n_response_message' => $pending,
                    'n8n_responded_at' => null,
                    'automation_status' => $isEmail ? 'sending_email' : 'sending_whatsapp',
                ]);
                event(new OrderAutomationUpdated($order));
            }

            return ['success' => true, 'message' => $pending, 'response' => $res['body'] ?? null, 'json' => $res['json'] ?? null];
        }

        // R-159 upstream outcomes (HTTP 200 for the admin screen).
        if (($res['status'] ?? null) === 404) {
            return ['success' => false, 'error' => 'Webhook not found or inactive', 'status' => 404, 'details' => $res['json'] ?? $res['body'] ?? null, 'solution' => 'Please ACTIVATE the workflow in n8n (not just test mode).'];
        }
        if (isset($res['status'])) {
            return ['success' => false, 'error' => 'Webhook returned error', 'status' => $res['status'], 'details' => $res['json'] ?? $res['body'] ?? null];
        }
        if ($res['timeout'] ?? false) {
            return ['success' => false, 'error' => 'Webhook request timed out', 'details' => 'The webhook did not respond in time'];
        }

        return ['success' => false, 'error' => 'Webhook request failed', 'details' => $res['error'] === 'invalid_webhook_url' ? 'invalid_webhook_url' : 'Could not reach the webhook'];
    }

    /** Admin toast for a relay result (F-NTF-036 / F-ADM-INV-011 mapping). */
    public static function adminMessage(array $result): string
    {
        if ($result['success'] ?? false) {
            return __('automation.sent_ok');
        }
        if (($result['code'] ?? null) === 'webhook_not_configured') {
            return __('automation.not_configured');
        }
        if (($result['status'] ?? null) === 404) {
            return __('automation.inactive_404');
        }
        $msg = $result['error'] ?? __('automation.send_failed');
        if (! empty($result['hint'])) {
            $msg .= ' — '.$result['hint'];
        } elseif (! empty($result['solution'])) {
            $msg .= ' — '.$result['solution'];
        }

        return __('automation.n8n_error', ['msg' => $msg]);
    }
}
