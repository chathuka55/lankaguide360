<?php

namespace App\Http\Controllers;

use App\Enums\TripStatus;
use App\Models\Category;
use App\Models\Hotel;
use App\Models\Package;
use App\Models\Place;
use App\Models\Review;
use App\Models\Trip;
use Illuminate\View\View;

class HomeController extends Controller
{
    /** Hero slideshow: landmark photos that work full-width; others fill in if one is missing. */
    private const HERO_SLUGS = ['sigiriya-rock-fortress', 'mirissa-beach', 'nine-arch-bridge', 'little-adams-peak', 'pigeon-island-national-park'];

    public function index(): View
    {
        $withPublishedCover = fn ($query) => $query->published()
            ->whereHas('cover', fn ($q) => $q->where('status', 'published'))
            ->with(['district', 'cover', 'categories']);

        $topPlaces = $withPublishedCover(Place::query())
            ->orderByRaw("FIELD(crowd_level, 'high', 'medium', 'low')")
            ->orderBy('name')
            ->limit(10)
            ->get();

        $heroPlaces = $withPublishedCover(Place::query())->whereIn('slug', self::HERO_SLUGS)->get()
            ->sortBy(fn (Place $place) => array_search($place->slug, self::HERO_SLUGS, true))
            ->concat($topPlaces)->unique('id')->take(count(self::HERO_SLUGS))->values();

        return view('home', [
            'heroPlaces' => $heroPlaces,
            'topPlaces' => $topPlaces,
            'hiddenGems' => $withPublishedCover(Place::query())->hiddenGems()->inRandomOrder()->limit(8)->get(),
            'categoryTiles' => Category::orderBy('id')->get()->map(fn (Category $category) => [
                'category' => $category,
                'count' => $category->places()->published()->count(),
                'cover' => $withPublishedCover($category->places()->getQuery())->first()?->cover,
            ]),
            'packages' => Package::featured()->with('templateTrip')->limit(6)->get(),
            'reviews' => Review::approved()->latest('id')->limit(9)->get(),
            'placeNames' => Place::published()->orderByRaw("FIELD(crowd_level, 'high', 'medium', 'low')")->orderBy('name')->limit(24)->pluck('name'),
            'stats' => [
                'places' => Place::published()->count(),
                'districts' => 25,
                'hotels' => Hotel::published()->count(),
                'trips' => Trip::whereNot('status', TripStatus::Draft)->count(),
            ],
        ]);
    }
}
