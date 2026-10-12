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

/** F-NTF-015/016 admin new-sale alert. */
class AdminSaleAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
        $this->locale('ar');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.sale_subject', ['ref' => $this->order->booking_reference, 'total' => Formats::amount($this->order->total_amount)]));
    }

    public function content(): Content
    {
        $o = $this->order->loadMissing(['customer', 'event', 'holders', 'posUser']);
        $holders = $o->holders->map(fn ($h) => $h->name.' ('.$h->ticket_type->label().')')->implode('، ');

        return new Content(
            view: 'mail.admin-sale-alert',
            text: 'mail.admin-sale-alert-text',
            with: [
                'order' => $o,
                'logo' => app(BrandingSettings::class)->logo_url,
                'sourceLabel' => $o->payment_method->value === 'cash_pos' ? __('mail.sale_source_pos') : __('mail.sale_source_online'),
                'soldAt' => Formats::longDateTime($o->confirmed_at ?? $o->created_at),
                'eventDate' => Formats::longDateTime($o->event?->event_date),
                'holders' => $holders !== '' ? $holders : null,
                'invoiceUrl' => route('invoice.show', ['reference' => $o->booking_reference]),
                'preview' => __('mail.sale_preview', ['ref' => $o->booking_reference, 'total' => Formats::amount($o->total_amount)]),
            ],
        );
    }
}
