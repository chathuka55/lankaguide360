<?php

namespace App\Mail;

use App\Models\Trip;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * To the agent: the traveller accepted the approved plan and price. Next: finalize the timings
 * and issue the e-receipt.
 */
class TripAccepted extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Trip $trip) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Booking accepted: {$this->trip->reference} — finalize timings and issue the e-receipt");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.trip-accepted');
    }
}
