<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\EmailDeliveryStatus;
use App\Events\EmailDeliveryRecorded;
use App\Http\Controllers\Controller;
use App\Models\EmailDeliveryEvent;
use App\Models\MailSend;
use App\Models\MailSuppression;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * F-NTF-020 provider delivery webhook (D-087): shared-secret verified,
 * matched by provider_message_id (real template), deduped by provider event
 * id, hard bounces/complaints added to the suppression list.
 * Accepts a normalised shape {type, message_id, event_id, recipient} used by
 * Resend/Postmark/Mailgun-style relays.
 */
class MailEventsController extends Controller
{
    public function __invoke(Request $request, string $provider): JsonResponse
    {
        $secret = (string) config('qcamelmc.notifications.mail_webhook_secret');
        $given = (string) ($request->header('X-Webhook-Secret') ?: $request->query('token') ?: $request->bearerToken());
        if ($secret === '' || ! hash_equals($secret, $given)) {
            return response()->json(['success' => false, 'error' => 'unauthorized'], 401);
        }

        $type = (string) ($request->input('type') ?? $request->input('RecordType') ?? $request->input('event'));
        $status = match (true) {
            str_contains(strtolower($type), 'bounce') => EmailDeliveryStatus::Bounced,
            str_contains(strtolower($type), 'complain') || str_contains(strtolower($type), 'spam') => EmailDeliveryStatus::Complained,
            str_contains(strtolower($type), 'unsubscrib') => EmailDeliveryStatus::Unsubscribed,
            default => null,
        };
        if (! $status) {
            return response()->json(['success' => true, 'ignored' => true]);
        }
        $messageId = (string) ($request->input('data.email_id') ?? $request->input('message_id') ?? $request->input('MessageID') ?? '');
        $eventId = (string) ($request->input('event_id') ?? $request->input('id') ?? hash('sha256', $request->getContent()));
        $recipient = trim((string) ($request->input('data.to.0') ?? $request->input('recipient') ?? $request->input('Recipient') ?? $request->input('Email') ?? ''));

        if (EmailDeliveryEvent::query()->where('provider_event_id', $provider.':'.$eventId)->exists()) {
            return response()->json(['success' => true, 'duplicate' => true]);
        }
        $send = $messageId !== '' ? MailSend::query()->where('provider_message_id', $messageId)->first() : null;
        $detail = match ($status) {
            EmailDeliveryStatus::Bounced => __('mail.bounced'),
            EmailDeliveryStatus::Complained => __('mail.complained'),
            default => __('mail.unsubscribed'),
        };
        $event = EmailDeliveryEvent::create([
            'order_id' => $send?->order_id,
            'booking_reference' => $send?->order_id ? \App\Models\Order::query()->withTrashed()->whereKey($send->order_id)->value('booking_reference') : null,
            'recipient' => $recipient !== '' ? $recipient : $send?->recipient,
            'template' => $send?->template ?? 'booking-invoice',
            'provider_message_id' => $messageId ?: null,
            'provider_event_id' => $provider.':'.$eventId,
            'status' => $status,
            'attempt' => 1,
            'detail' => $detail,
        ]);
        $email = mb_strtolower($recipient !== '' ? $recipient : (string) $send?->recipient);
        if ($email !== '' && in_array($status, [EmailDeliveryStatus::Bounced, EmailDeliveryStatus::Complained, EmailDeliveryStatus::Unsubscribed], true)) {
            MailSuppression::query()->firstOrCreate(['email' => $email], ['reason' => $status->value]);
        }
        if ($event->order_id) {
            event(new EmailDeliveryRecorded($event->order_id, $status->value));
        }

        return response()->json(['success' => true]);
    }
}
