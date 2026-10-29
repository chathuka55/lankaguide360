<?php

namespace App\Services\Mail;

use App\Mail\TestMail;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends a test email right away (not through the queue) and explains common SMTP problems.
 */
class MailTester
{
    /**
     * @return array{ok: bool, message: string}
     */
    public function send(string $to): array
    {
        if (config('mail.default') === 'log') {
            Mail::to($to)->send(new TestMail);

            return ['ok' => false, 'message' => 'MAIL_MAILER is "log": the email was written to storage/logs/laravel.log instead of being sent. Add your SMTP details to .env (see docs/EMAIL.md).'];
        }

        try {
            Mail::to($to)->send(new TestMail);
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Sending failed: '.$this->explain($e->getMessage())];
        }

        return ['ok' => true, 'message' => "Test email sent to {$to} with ".config('mail.default').'. Check the inbox (and the spam folder).'];
    }

    private function explain(string $error): string
    {
        $hint = match (true) {
            str_contains($error, '535') || str_contains(strtolower($error), 'authentication') => ' — the SMTP username or password is wrong (Gmail needs an App Password, not your normal password).',
            str_contains($error, 'Connection could not be established') || str_contains($error, 'timed out') => ' — the SMTP host or port is wrong, or the host blocks outgoing SMTP. Try port 587 with MAIL_SCHEME=null, or 465 with MAIL_SCHEME=smtps.',
            str_contains($error, 'sender') || str_contains($error, '550') => ' — the provider refused the sender. MAIL_FROM_ADDRESS must be an address you verified with the provider.',
            default => '',
        };

        return mb_strimwidth($error, 0, 300, '…').$hint;
    }
}
