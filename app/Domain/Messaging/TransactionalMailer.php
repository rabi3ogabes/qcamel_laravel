<?php

namespace App\Domain\Messaging;

use App\Enums\EmailDeliveryStatus;
use App\Enums\EmailTemplate;
use App\Enums\PaymentErrorSource;
use App\Enums\PaymentStatus;
use App\Events\EmailDeliveryRecorded;
use App\Mail\AdminSaleAlertMail;
use App\Mail\BookingInvoiceMail;
use App\Mail\LoginAlertMail;
use App\Mail\PaymentFailedMail;
use App\Models\EmailDeliveryEvent;
use App\Models\MailSend;
use App\Models\MailSuppression;
use App\Models\Order;
use App\Settings\AutomationSettings;
use App\Settings\ContactSettings;
use App\Support\QatarClock;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Transactional e-mail with exactly-once semantics (F-NTF-001…018,
 * R-162…R-164, T-067, T-068): idempotency keys stored in mail_sends,
 * suppression list checked before every send, every outcome logged to
 * email_delivery_events with the REAL template (D-087), admin copy deduped
 * (D-091).
 */
class TransactionalMailer
{
    private const EMAIL_RE = '/^[^@\s]+@[^@\s]+\.[^@\s]+$/';

    public static function validEmail(?string $email): ?string
    {
        $e = trim((string) $email);

        return preg_match(self::EMAIL_RE, $e) === 1 ? $e : null;
    }

    /**
     * F-NTF-003…005 booking invoice.
     *
     * @return array{sent:bool, reason?:string, invoice_url:string, attempt:int}
     */
    public function sendInvoice(Order $order, bool $force = false): array
    {
        $order->loadMissing(['customer', 'event', 'holders']);
        $invoiceUrl = route('invoice.show', ['reference' => $order->booking_reference]);
        if ($order->payment_status !== PaymentStatus::Confirmed) {
            return ['sent' => false, 'reason' => 'not_confirmed', 'invoice_url' => $invoiceUrl, 'attempt' => 0];
        }
        $email = self::validEmail($order->customer?->email);
        if (! $email) {
            $this->event($order, null, EmailTemplate::BookingInvoice, EmailDeliveryStatus::Failed, 1, __('mail.no_valid_email'));

            return ['sent' => false, 'reason' => 'no_email', 'invoice_url' => $invoiceUrl, 'attempt' => 0];
        }
        if ($order->invoice_email_sent_at && ! $force) {
            return ['sent' => false, 'reason' => 'already_sent', 'invoice_url' => $invoiceUrl, 'attempt' => 0];
        }
        $attempt = EmailDeliveryEvent::query()->where('order_id', $order->id)->where('template', EmailTemplate::BookingInvoice->value)
            ->whereIn('status', ['queued', 'retried'])->count() + 1;
        $this->event($order, $email, EmailTemplate::BookingInvoice, $attempt > 1 ? EmailDeliveryStatus::Retried : EmailDeliveryStatus::Queued, $attempt,
            $attempt > 1 ? __('mail.retried', ['n' => $attempt]) : __('mail.queued'));

        $result = $this->deliver(EmailTemplate::BookingInvoice, $email, new BookingInvoiceMail($order), "booking-invoice-{$order->id}-{$attempt}", $order, $attempt);
        if ($result === 'sent') {
            Order::query()->whereKey($order->id)->update(['invoice_email_sent_at' => now()]);
        }

        return ['sent' => $result === 'sent', 'reason' => $result === 'sent' ? null : $result, 'invoice_url' => $invoiceUrl, 'attempt' => $attempt];
    }

    /**
     * F-NTF-009…012 payment-failed notice (customer if enabled + admin copy deduped).
     *
     * @return array{sent:bool, reason?:string, admin_sent:bool}
     */
    public function sendPaymentFailed(Order $order, ?string $errorMessage = null, bool $force = false): array
    {
        $order->loadMissing(['customer', 'event']);
        if ($order->payment_status === PaymentStatus::Confirmed) {
            return ['sent' => false, 'reason' => 'order_confirmed', 'admin_sent' => false];
        }
        $reason = $order->payment_error_reason ?: ($errorMessage ?: '');
        $mailable = fn () => new PaymentFailedMail($order, $reason);

        $customerResult = ['sent' => false, 'reason' => null];
        $email = self::validEmail($order->customer?->email);
        if (! app(AutomationSettings::class)->payment_failed_email_enabled) {
            $this->event($order, $email, EmailTemplate::PaymentFailed, EmailDeliveryStatus::Skipped, 1, __('mail.failed_disabled'));
            $customerResult['reason'] = 'disabled';
        } elseif (! $email) {
            $this->event($order, null, EmailTemplate::PaymentFailed, EmailDeliveryStatus::Failed, 1, __('mail.no_valid_email'));
            $customerResult['reason'] = 'no_email';
        } elseif (! $force && EmailDeliveryEvent::query()->where('order_id', $order->id)->where('template', 'payment-failed')->where('status', 'sent')->where('recipient_normalized', mb_strtolower($email))->exists()) {
            $customerResult['reason'] = 'already_sent';
        } else {
            $this->event($order, $email, EmailTemplate::PaymentFailed, EmailDeliveryStatus::Queued, 1, __('mail.failed_queued'));
            $r = $this->deliver(EmailTemplate::PaymentFailed, $email, $mailable(), "payment-failed-{$order->id}".($force ? '-'.now()->timestamp : ''), $order, 1);
            $customerResult = ['sent' => $r === 'sent', 'reason' => $r === 'sent' ? null : $r];
        }

        $adminSent = false;
        foreach (app(ContactSettings::class)->validAdminEmails() as $admin) {
            $r = $this->deliver(EmailTemplate::PaymentFailed, $admin, $mailable(), "payment-failed-admin-{$order->id}-".md5($admin), $order, 1, adminCopy: true);
            $adminSent = $adminSent || $r === 'sent';
        }

        return ['sent' => $customerResult['sent'], 'reason' => $customerResult['reason'], 'admin_sent' => $adminSent];
    }

    /**
     * F-NTF-014…016 admin new-sale alert (online + POS, once per order).
     *
     * @return array{sent:bool, reason?:string}
     */
    public function sendAdminSale(Order $order, bool $force = false): array
    {
        $order->loadMissing(['customer', 'event', 'holders', 'posUser']);
        if ($order->payment_status !== PaymentStatus::Confirmed) {
            return ['sent' => false, 'reason' => 'not_confirmed'];
        }
        $admins = app(ContactSettings::class)->validAdminEmails();
        if ($admins === []) {
            return ['sent' => false, 'reason' => 'no_admin_email'];
        }
        if (! $force && EmailDeliveryEvent::query()->where('order_id', $order->id)->where('template', 'admin-sale-alert')->where('status', 'sent')->exists()) {
            return ['sent' => false, 'reason' => 'already_sent'];
        }
        $any = false;
        foreach ($admins as $admin) {
            $this->event($order, $admin, EmailTemplate::AdminSaleAlert, EmailDeliveryStatus::Queued, 1, __('mail.sale_queued'));
            $key = "admin-sale-{$order->id}-".md5($admin).($force ? '-'.now()->timestamp : '');
            $any = $this->deliver(EmailTemplate::AdminSaleAlert, $admin, new AdminSaleAlertMail($order), $key, $order, 1) === 'sent' || $any;
        }

        return ['sent' => $any];
    }

    /** F-NTF-017/018 + U-AUTH-04: one alert per identifier per 30-min bucket. */
    public function sendLoginAlert(string $identifier, string $kind, int $attempts, ?string $ip, ?string $ua): void
    {
        $bucket = (int) floor(QatarClock::nowUtc()->getTimestamp() / 1800);
        $key = "login-block-{$identifier}-{$bucket}";
        if (! Cache::add('mail-lock:'.$key, 1, 1800)) {
            return;
        }
        foreach (app(ContactSettings::class)->validAdminEmails() as $admin) {
            $this->deliver(EmailTemplate::LoginAlert, $admin, new LoginAlertMail($identifier, $kind, $attempts, $ip, $ua), $key.'-'.md5($admin), null, 1);
        }
    }

    /**
     * Send one message with DB idempotency and suppression.
     *
     * @return string sent | already_sent | suppressed | failed
     */
    public function deliver(EmailTemplate $template, string $recipient, Mailable $mailable, string $key, ?Order $order, int $attempt, bool $adminCopy = false): string
    {
        $existing = MailSend::query()->where('idempotency_key', $key)->first();
        if ($existing && $existing->status === 'sent') {
            return 'already_sent';
        }
        if (MailSuppression::isSuppressed($recipient)) {
            MailSend::query()->updateOrCreate(['idempotency_key' => $key], ['template' => $template->value, 'order_id' => $order?->id, 'recipient' => $recipient, 'status' => 'suppressed']);
            $this->event($order, $recipient, $template, EmailDeliveryStatus::Suppressed, $attempt, __('mail.suppressed'));

            return 'suppressed';
        }
        try {
            $mailable->withSymfonyMessage(fn ($m) => $m->getHeaders()->addTextHeader('X-Idempotency-Key', $key));
            $sent = Mail::to($recipient)->send($mailable);
            $messageId = $sent?->getMessageId();
            MailSend::query()->updateOrCreate(['idempotency_key' => $key], ['template' => $template->value, 'order_id' => $order?->id, 'recipient' => $recipient, 'status' => 'sent', 'provider_message_id' => $messageId]);
            $this->event($order, $recipient, $template, EmailDeliveryStatus::Sent, $attempt,
                $adminCopy ? __('mail.admin_copy', ['email' => $recipient]) : __('mail.sent_to', ['email' => $recipient]), $messageId);

            return 'sent';
        } catch (Throwable $e) {
            MailSend::query()->updateOrCreate(['idempotency_key' => $key], ['template' => $template->value, 'order_id' => $order?->id, 'recipient' => $recipient, 'status' => 'failed']);
            $this->event($order, $recipient, $template, EmailDeliveryStatus::Failed, $attempt, $adminCopy ? __('mail.admin_copy_failed', ['reason' => $e->getMessage()]) : mb_substr($e->getMessage(), 0, 480));
            Log::warning('mail send failed', ['template' => $template->value, 'error' => $e->getMessage()]);

            return 'failed';
        }
    }

    private function event(?Order $order, ?string $recipient, EmailTemplate $template, EmailDeliveryStatus $status, int $attempt, ?string $detail, ?string $messageId = null): void
    {
        try {
            EmailDeliveryEvent::create([
                'order_id' => $order?->id, 'booking_reference' => $order?->booking_reference, 'recipient' => $recipient,
                'template' => $template->value, 'status' => $status, 'attempt' => $attempt, 'detail' => $detail ? mb_substr($detail, 0, 500) : null,
                'provider_message_id' => $messageId,
            ]);
            if ($order) {
                event(new EmailDeliveryRecorded($order->id, $status->value));
            }
        } catch (Throwable $e) {
            Log::warning('email delivery event failed', ['error' => $e->getMessage()]);
        }
    }

    /** F-NTF-012 source label. */
    public static function failureSourceLabel(?string $reason): string
    {
        return PaymentErrorSource::classify($reason) === PaymentErrorSource::Bank ? __('mail.source_bank') : __('mail.source_sadad');
    }
}
