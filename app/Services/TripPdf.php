<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\TripReceipt;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;

/**
 * Printable trip plan (FR-25): day list with times and a route table instead of the map.
 */
class TripPdf
{
    /**
     * False until barryvdh/laravel-dompdf is installed (composer install).
     */
    public function available(): bool
    {
        return class_exists(\Dompdf\Dompdf::class) && class_exists(Pdf::class);
    }

    public function make(Trip $trip): DomPdf
    {
        $trip->loadMissing([
            'tripDays.stops.place.district', 'tripDays.hotel', 'tripDays.roomRate',
            'travellers', 'leadTraveller', 'user', 'agent', 'priceItems', 'vehicle', 'guide', 'cuisines',
        ]);

        return Pdf::loadView('trips.pdf', ['trip' => $trip])->setPaper('a4');
    }

    /**
     * The e-receipt as PDF (same template as the printable page).
     */
    public function receipt(TripReceipt $receipt): DomPdf
    {
        return Pdf::loadView('trips.receipt', ['receipt' => $receipt, 'trip' => $receipt->trip, 'forPdf' => true])->setPaper('a4');
    }

    public function output(Trip $trip): string
    {
        return $this->make($trip)->output();
    }

    public function filename(Trip $trip): string
    {
        return "LankaGuide360-{$trip->reference}.pdf";
    }
}
