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
 * Alert to agents and admins that a new trip request is waiting (SRS 8.4).
 */
class NewTripRequest extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Trip $trip) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "New trip request {$this->trip->reference} ({$this->trip->days} days, {$this->trip->tier->label()})");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.new-trip-request');
    }
}
