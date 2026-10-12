<?php

namespace App\Mail;

use App\Domain\Messaging\TransactionalMailer;
use App\Models\Order;
use App\Settings\BrandingSettings;
use App\Support\Formats;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** F-NTF-012/013 payment-failed notice; retry → the event's ticket page. */
class PaymentFailedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public ?string $reason = null)
    {
        $this->locale($order->locale ?: 'ar');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.failed_subject', ['ref' => $this->order->booking_reference], $this->order->locale ?: 'ar'));
    }

    public function content(): Content
    {
        $o = $this->order->loadMissing(['customer', 'event']);

        return new Content(
            view: 'mail.payment-failed',
            text: 'mail.payment-failed-text',
            with: [
                'order' => $o,
                'logo' => app(BrandingSettings::class)->logo_url,
                'reason' => $this->reason,
                'sourceLabel' => $this->reason ? TransactionalMailer::failureSourceLabel($this->reason) : null,
                'retryUrl' => $o->event_id ? route('tickets.select', ['event' => $o->event_id]) : route('home'),
                'invoiceUrl' => route('invoice.show', ['reference' => $o->booking_reference]),
                'eventDate' => Formats::longDate($o->event?->event_date, false),
                'preview' => __('mail.failed_preview', ['ref' => $o->booking_reference]),
            ],
        );
    }
}
