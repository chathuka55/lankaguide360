<?php

namespace App\Http\Controllers;

use App\Models\Place;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TripBuilderController extends Controller
{
    /** Session key holding place ids picked on destination pages before the builder opens. */
    public const PICKED_PLACES = 'trip_draft.place_ids';

    public function start(): View
    {
        return view('builder.start');
    }

    /**
     * "Add to my trip" on a place page: remember the place for builder step 4.
     */
    public function addPlace(Request $request, Place $place): RedirectResponse|JsonResponse
    {
        abort_unless($place->isPublished(), 404);

        $ids = collect((array) $request->session()->get(self::PICKED_PLACES, []));
        $ids = $ids->contains($place->id) ? $ids->reject(fn ($id) => $id === $place->id) : $ids->push($place->id);
        $request->session()->put(self::PICKED_PLACES, $ids->values()->all());

        $added = $ids->contains($place->id);
        $message = $added ? "{$place->name} added to your trip." : "{$place->name} removed from your trip.";

        return $request->expectsJson()
            ? response()->json(['added' => $added, 'count' => $ids->count(), 'message' => $message])
            : back()->with('status', $message);
    }
}
