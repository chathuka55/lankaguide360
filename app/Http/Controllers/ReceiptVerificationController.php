<?php

namespace App\Http\Controllers;

use App\Enums\TripStatus;
use App\Models\TripReceipt;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public check behind the e-receipt QR code: is this a genuine, still-valid booking? Shows only
 * what a driver or hotel needs (reference, dates, initials), never contact details or prices.
 */
class ReceiptVerificationController extends Controller
{
    public function __invoke(Request $request, string $number): View
    {
        $receipt = TripReceipt::with('trip.leadTraveller')->where('number', $number)->first();
        $genuine = $receipt !== null && hash_equals($receipt->verification_code, (string) $request->query('code', ''));
        $trip = $genuine ? $receipt->trip : null;

        return view('receipts.verify', [
            'number' => $number,
            'genuine' => $genuine,
            'valid' => $genuine && ! in_array($trip->status, [TripStatus::Cancelled, TripStatus::Rejected], true),
            'receipt' => $genuine ? $receipt : null,
            'trip' => $trip,
            'initials' => $trip ? $this->initials($trip->contactName()) : null,
        ]);
    }

    /**
     * "Nimal Perera" → "N. Perera": enough to match a passport, not enough to identify online.
     */
    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        if (count($parts) < 2) {
            return mb_substr($name, 0, 1).'.';
        }
        $last = array_pop($parts);

        return implode(' ', array_map(fn ($p) => mb_substr($p, 0, 1).'.', $parts)).' '.$last;
    }
}
