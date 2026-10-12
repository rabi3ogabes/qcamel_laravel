<?php

namespace App\Mail;

use App\Support\Formats;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** F-NTF-017/018 + U-AUTH-04 login-block security alert. */
class LoginAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $identifier, public string $kind, public int $attempts, public ?string $ip, public ?string $userAgent)
    {
        $this->locale('ar');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.login_subject', ['identifier' => $this->identifier]));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.login-alert',
            text: 'mail.login-alert-text',
            with: [
                'kindLabel' => $this->kind === 'passcode' ? __('mail.kind_passcode') : __('mail.kind_account'),
                'blockedMinutes' => (int) config('qcamelmc.auth.login_block', 30),
                'occurredAt' => Formats::longDateTime(now()),
                'preview' => __('mail.login_preview', ['identifier' => $this->identifier]),
            ],
        );
    }
}
