<?php

namespace App\Http\Controllers;

use App\Enums\Tier;
use App\Http\Requests\DestinationFilterRequest;
use App\Models\Category;
use App\Models\District;
use App\Models\Hotel;
use App\Models\Place;
use App\Services\Site\SiteCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

/**
 * Public destination pages (SRS 3.3, FR-02). Only published, active places are shown.
 */
class DestinationController extends Controller
{
    public function index(DestinationFilterRequest $request, SiteCatalog $catalog): View
    {
        $filters = $request->filters();

        $query = Place::published()
            ->with(['district', 'categories', 'cover' => fn ($q) => $q->where('status', 'published')])
            ->when($filters['q'], fn (Builder $q, $search) => $q->where(fn ($w) => $w->where('places.name', 'like', "%{$search}%")->orWhere('places.short_description', 'like', "%{$search}%")))
            ->when($filters['category'], fn (Builder $q, $slug) => $q->whereHas('categories', fn ($c) => $c->where('slug', $slug)))
            ->when($filters['district'], fn (Builder $q, $slug) => $q->whereHas('district', fn ($d) => $d->where('slug', $slug)))
            ->when($filters['province'], fn (Builder $q, $slug) => $q->whereHas('district.province', fn ($p) => $p->where('slug', $slug)))
            ->when($filters['gems'], fn (Builder $q) => $q->hiddenGems())
            ->orderBy('places.name');

        $mapMode = $filters['view'] === 'map';

        return view('destinations.index', [
            'filters' => $filters,
            'places' => $mapMode ? null : (clone $query)->paginate(12)->withQueryString(),
            'mapPlaces' => $mapMode ? $this->mapData((clone $query)->whereNotNull('places.lat')->limit(1000)->get()) : [],
            'total' => (clone $query)->count(),
            'categories' => $catalog->categories(),
            'provinces' => $catalog->provinces(),
        ]);
    }

    public function show(District $district, Place $place): View
    {
        abort_unless($place->isPublished(), 404);

        $place->load(['district.province', 'categories', 'publishedMedia']);

        $nearbyPlaces = $place->hasCoordinates()
            ? Place::published()->nearTo((float) $place->lat, (float) $place->lng, 30)
                ->whereKeyNot($place->id)
                ->with(['district', 'cover' => fn ($q) => $q->where('status', 'published')])
                ->limit(6)->get()
            : collect();

        $nearbyHotels = $place->hasCoordinates()
            ? Hotel::published()->nearTo((float) $place->lat, (float) $place->lng, 20)
                ->limit(30)->get()
                ->groupBy(fn (Hotel $hotel) => $hotel->tier->value)
                ->map(fn ($hotels) => $hotels->take(3))
                ->sortBy(fn ($hotels, $tier) => array_search($tier, array_column(Tier::cases(), 'value'), true))
            : collect();

        return view('destinations.show', [
            'place' => $place,
            'photos' => $place->publishedMedia,
            'nearbyPlaces' => $nearbyPlaces,
            'nearbyHotels' => $nearbyHotels,
            'inTrip' => in_array($place->id, (array) session('trip_draft.place_ids', []), true),
        ]);
    }

    public function district(District $district): View
    {
        $district->load('province', 'categories');

        $places = $district->places()->published()
            ->with(['categories', 'cover' => fn ($q) => $q->where('status', 'published')])
            ->orderBy('name')
            ->get();

        return view('destinations.district', [
            'district' => $district,
            'placesByCategory' => Category::orderBy('id')->get()
                ->map(fn (Category $category) => [
                    'category' => $category,
                    'places' => $places->filter(fn (Place $place) => $place->categories->contains($category)),
                ])
                ->filter(fn ($group) => $group['places']->isNotEmpty()),
            'places' => $places,
            'mapPlaces' => $this->mapData($places->whereNotNull('lat')->each(fn ($p) => $p->setRelation('district', $district))),
            'hotels' => $district->hotels()->published()->orderByRaw("FIELD(tier, 'luxury', 'premium', 'budget')")->orderBy('name')->limit(12)->get(),
        ]);
    }

    /**
     * Compact marker data for Leaflet (map view, district map).
     *
     * @return array<int, array<string, mixed>>
     */
    private function mapData($places): array
    {
        return $places->map(fn (Place $place) => [
            'name' => $place->name,
            'lat' => (float) $place->lat,
            'lng' => (float) $place->lng,
            'url' => $place->url(),
            'district' => $place->district->name,
            'image' => $place->cover?->url(400),
            'gem' => $place->is_hidden_gem,
        ])->values()->all();
    }
}
