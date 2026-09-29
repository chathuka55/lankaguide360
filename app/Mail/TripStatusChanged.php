<?php

namespace App\Mail;

use App\Enums\TripStatus;
use App\Models\Trip;
use App\Services\TripPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * FR-21: the traveller hears about every status change. Approval emails attach the plan as PDF;
 * rejections include the agent's reason.
 */
class TripStatusChanged extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Trip $trip, public TripStatus $status, public ?string $note = null) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->status) {
            TripStatus::Approved => 'Your trip is approved',
            TripStatus::Rejected => 'About your trip request',
            TripStatus::UnderReview => 'An agent is reviewing your trip',
            TripStatus::Confirmed => 'Your trip is confirmed',
            TripStatus::Cancelled => 'Your trip has been cancelled',
            TripStatus::Completed => 'Thank you for travelling with us',
            default => 'Your trip status: '.$this->status->label(),
        };

        return new Envelope(subject: "{$subject} ({$this->trip->reference})");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.trip-status-changed');
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if ($this->status !== TripStatus::Approved || ! app(TripPdf::class)->available()) {
            return [];
        }

        return [
            Attachment::fromData(fn () => app(TripPdf::class)->output($this->trip), "LankaGuide360-{$this->trip->reference}.pdf")->withMime('application/pdf'),
        ];
    }
}
