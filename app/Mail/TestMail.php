<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent (not queued) by `php artisan lg:mail-test` and Admin → Settings → Send test email.
 */
class TestMail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: config('app.name').' test email');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.test', with: [
            'mailer' => config('mail.default'),
            'host' => config('mail.mailers.'.config('mail.default').'.host'),
            'from' => config('mail.from.address'),
        ]);
    }
}
