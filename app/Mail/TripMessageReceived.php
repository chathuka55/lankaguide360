<?php

namespace App\Mail;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * FR-22: email notification of a new message on a trip.
 */
class TripMessageReceived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $url  where the recipient can read and reply
     */
    public function __construct(public Message $tripMessage, public string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "New message about trip {$this->tripMessage->trip->reference}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.trip-message');
    }
}
