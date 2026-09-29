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
 * FR-19: confirmation with the reference number and the private tracking link.
 */
class TripSubmitted extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Trip $trip) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your Sri Lanka trip request {$this->trip->reference}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.trip-submitted', with: [
            'days' => $this->trip->tripDays()->with('stops.place')->get(),
        ]);
    }
}
