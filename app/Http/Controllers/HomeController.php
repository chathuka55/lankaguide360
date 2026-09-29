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
    public function index(): View
    {
        $withPublishedCover = fn ($query) => $query->published()
            ->whereHas('cover', fn ($q) => $q->where('status', 'published'))
            ->with(['district', 'cover', 'categories']);

        $topPlaces = $withPublishedCover(Place::query())
            ->orderByRaw("FIELD(crowd_level, 'high', 'medium', 'low')")
            ->orderBy('name')
            ->limit(8)
            ->get();

        return view('home', [
            // Hero photo: a well-known published place, rotated daily.
            'hero' => $topPlaces->isNotEmpty() ? $topPlaces[now()->dayOfYear % $topPlaces->count()] : null,
            'topPlaces' => $topPlaces,
            'hiddenGems' => $withPublishedCover(Place::query())->hiddenGems()->inRandomOrder()->limit(8)->get(),
            'categoryTiles' => Category::orderBy('id')->get()->map(fn (Category $category) => [
                'category' => $category,
                'count' => $category->places()->published()->count(),
                'cover' => $withPublishedCover($category->places()->getQuery())->first()?->cover,
            ]),
            'packages' => Package::featured()->with('templateTrip')->limit(6)->get(),
            'reviews' => Review::approved()->latest('id')->limit(9)->get(),
            'stats' => [
                'places' => Place::published()->count(),
                'districts' => 25,
                'hotels' => Hotel::published()->count(),
                'trips' => Trip::whereNot('status', TripStatus::Draft)->count(),
            ],
        ]);
    }
}
