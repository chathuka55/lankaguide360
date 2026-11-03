<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\Place;
use App\Models\RoomRate;
use Illuminate\View\View;

/**
 * Public hotel details page (feedback round, Nov 2026): photos, rating, rooms and prices,
 * amenities, location with a Google Maps link, contact details and nearby places.
 */
class HotelPageController extends Controller
{
    public function show(Hotel $hotel): View
    {
        abort_unless($hotel->isPublished() && $hotel->is_active, 404);

        $hotel->load(['district.province', 'media' => fn ($q) => $q->where('status', 'published')->orderByDesc('is_cover')->orderBy('sort_order')]);
        $today = now()->toDateString();

        return view('hotels.show', [
            'hotel' => $hotel,
            'photos' => $hotel->media,
            // Until the hotel has its own photos: labelled sample photos of the same kind of stay.
            'samplePhotos' => $hotel->media->isEmpty() ? $hotel->samplePhotos() : collect(),
            'rates' => $hotel->roomRates()
                ->where(fn ($q) => $q->whereNull('season_to')->orWhereDate('season_to', '>=', $today))
                ->orderBy('room_type')->orderBy('price_per_night')->get()
                ->groupBy('room_type'),
            'fromRate' => $hotel->roomRates()->min('price_per_night'),
            'nearbyPlaces' => $hotel->lat !== null
                ? Place::published()->nearTo((float) $hotel->lat, (float) $hotel->lng, 15)->with('district:id,slug,name', 'cover')->orderBy('distance_km')->limit(6)->get()
                : collect(),
            'mealPlanNote' => fn (RoomRate $rate) => $rate->meal_plan->label(),
        ]);
    }
}
