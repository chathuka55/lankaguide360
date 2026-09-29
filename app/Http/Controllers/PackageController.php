<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Services\PackageService;
use App\Support\TripDraft;
use App\Support\TripMapData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public package pages (SRS 3.3). "Book Now" and "Customize" both load the package into the
 * Trip Builder; Book Now jumps to the review step, Customize to the places step.
 */
class PackageController extends Controller
{
    public function index(): View
    {
        return view('packages.index', [
            'packages' => Package::with('media')->orderByDesc('is_featured')->orderBy('sort_order')->get(),
        ]);
    }

    public function show(Package $package): View
    {
        $package->load(['media', 'templateTrip.tripDays.stops.place.district', 'templateTrip.tripDays.stops.place.media']);
        $template = $package->templateTrip;

        return view('packages.show', [
            'package' => $package,
            'days' => $template?->tripDays ?? collect(),
            'mapDays' => $template ? TripMapData::fromTrip($template) : [],
        ]);
    }

    public function customize(Request $request, Package $package, PackageService $packages): RedirectResponse
    {
        $draft = $packages->toDraft($package, TripDraft::fromSession($request->session()));
        $draft->saveTo($request->session());

        $book = $request->input('mode') === 'book';

        return redirect()
            ->route('plan', $book ? ['step' => 5] : ['step' => 4])
            ->with('status', $book
                ? "“{$package->name}” is loaded. Pick your dates and travellers, then review and submit."
                : "“{$package->name}” is loaded. Add or remove places to make it yours.");
    }
}
