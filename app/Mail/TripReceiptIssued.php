<?php

namespace App\Mail;

use App\Models\TripReceipt;
use App\Services\TripPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * To the traveller: the booking is final; the e-receipt is attached as PDF and can be printed
 * from the trip page.
 */
class TripReceiptIssued extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public TripReceipt $receipt) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your e-receipt {$this->receipt->number} for trip {$this->receipt->trip->reference}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.trip-receipt', with: ['trip' => $this->receipt->trip]);
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! app(TripPdf::class)->available()) {
            return [];
        }

        return [
            Attachment::fromData(fn () => app(TripPdf::class)->receipt($this->receipt)->output(), "LankaGuide360-e-receipt-{$this->receipt->number}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
