<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email carrying the 6-digit login OTP.
 * The plaintext code exists only in this message (never in API responses).
 */
class LoginOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public int $ttlMinutes,
        public string $appUrl = ''
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('travelo.otp.subject', [
                'app' => config('mail.from.name', 'Travelo'),
            ])
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.otp',
            text: 'emails.otp-text',
            with: [
                'code' => $this->code,
                'ttlMinutes' => $this->ttlMinutes,
                'appUrl' => $this->appUrl,
            ]
        );
    }
}
