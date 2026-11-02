<?php

namespace App\Http\Controllers;

use App\Http\Requests\VehicleHireRequest;
use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Models\Vehicle;
use App\Support\TripPoints;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * Public vehicle hire (feedback round, Nov 2026): every rentable vehicle with its services,
 * provider and rates, and a request form. Requests arrive in Admin → Contact messages and by
 * email; an agent confirms availability and the price.
 */
class VehicleHireController extends Controller
{
    public function index(): View
    {
        return view('vehicles.index', [
            'vehicles' => Vehicle::where('is_rentable', true)->with('cover')
                ->orderByRaw("FIELD(tier, 'budget', 'premium', 'luxury')")->orderBy('max_pax')->get(),
            'points' => TripPoints::grouped(),
        ]);
    }

    public function request(VehicleHireRequest $request, Vehicle $vehicle): RedirectResponse
    {
        abort_unless($vehicle->is_rentable, 404);
        $selfDrive = $request->isSelfDrive();
        if ($selfDrive && ! $vehicle->offersSelfDrive()) {
            return back()->withInput()->withErrors(['mode' => "The {$vehicle->type} is only hired with a driver."]);
        }

        if (! $request->isSpam()) {
            $data = $request->validated();
            $message = ContactMessage::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'subject' => "Vehicle hire request: {$vehicle->type}".($selfDrive ? ' (self-drive)' : ''),
                'body' => implode("\n", array_filter([
                    "Vehicle: {$vehicle->type} ({$vehicle->tier->label()}, up to {$vehicle->max_pax} travellers)",
                    $selfDrive
                        ? 'SELF-DRIVE. Driver: '.$data['driver_name'].', Sri Lankan licence '.strtoupper($data['licence_no']).', valid until '.$data['licence_expiry'].'. Check the licence and NIC at handover.'
                        : 'With driver',
                    $vehicle->provider_name ? "Provider: {$vehicle->provider_name}" : null,
                    "From {$data['start_date']} to {$data['end_date']}",
                    "Pick-up: {$data['pickup']}",
                    "Travellers: {$data['travellers']}",
                    filled($data['message'] ?? null) ? "\n".$data['message'] : null,
                ])),
            ]);
            Mail::to(config('lankaguide.contact.admin_email'))->queue(new ContactMessageReceived($message));
        }

        return redirect()->to(route('vehicles.index').'#vehicle-'.$vehicle->id)
            ->with('status', "Thank you! We received your request for the {$vehicle->type}. We'll confirm availability and the price by email or phone.");
    }
}
