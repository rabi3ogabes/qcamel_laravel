<?php

namespace App\Mail;

use App\Models\Order;
use App\Settings\BrandingSettings;
use App\Support\Formats;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** F-NTF-006…008 booking invoice (subject "فاتورة حجزك <ref>"). */
class BookingInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
        $this->locale($order->locale ?: 'ar');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: trim(__('mail.invoice_subject', ['ref' => $this->order->booking_reference], $this->order->locale ?: 'ar')));
    }

    public function content(): Content
    {
        $o = $this->order->loadMissing(['customer', 'event']);

        return new Content(
            view: 'mail.booking-invoice',
            text: 'mail.booking-invoice-text',
            with: [
                'order' => $o,
                'logo' => app(BrandingSettings::class)->logo_url,
                'festival' => app(BrandingSettings::class)->festival_name,
                'invoiceUrl' => route('invoice.show', ['reference' => $o->booking_reference]),
                'eventDate' => Formats::longDate($o->event?->event_date, false),
                'paidAt' => $o->confirmed_at ? Formats::longDateTime($o->confirmed_at) : null,
                'preview' => __('mail.invoice_preview', ['ref' => $o->booking_reference]),
            ],
        );
    }
}
