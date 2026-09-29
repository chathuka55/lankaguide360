<?php

namespace App\Services;

use App\Enums\Tier;
use App\Enums\TripStatus;
use App\Models\Package;
use App\Models\Place;
use App\Models\Trip;
use App\Support\TripDraft;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Packages are template trips (SRS 3.3): "Save as package" copies a trip's days into a new
 * template, and "Customize" / "Book Now" load a template into the traveller's builder draft.
 */
class PackageService
{
    /**
     * A new draft template trip with the given days.
     *
     * @param  array<int, array{title: string, place_ids: array<int, int>, overnight_town: ?string}>  $days
     */
    public function createTemplate(Tier $tier, array $days, ?string $referenceSuffix = null): Trip
    {
        return DB::transaction(function () use ($tier, $days, $referenceSuffix) {
            $trip = Trip::create([
                'reference' => 'PKG-'.strtoupper($referenceSuffix ?? Str::random(8)),
                'tier' => $tier,
                'start_date' => now()->toDateString(),
                'days' => count($days),
                'adults' => 2,
                'status' => TripStatus::Draft,
                'access_token' => Str::random(40),
            ]);

            foreach (array_values($days) as $i => $day) {
                $tripDay = $trip->tripDays()->create([
                    'day_number' => $i + 1,
                    'date' => now()->addDays($i)->toDateString(),
                    'title' => Str::limit($day['title'], 150, ''),
                    'overnight_town' => $day['overnight_town'] ?? null,
                ]);
                foreach (array_values($day['place_ids']) as $sequence => $placeId) {
                    $tripDay->stops()->create(['place_id' => $placeId, 'sequence' => $sequence + 1]);
                }
            }

            return $trip;
        });
    }

    /**
     * "Save as package" from a reviewed trip: copies the plan (never the travellers).
     */
    public function fromTrip(Trip $trip, string $name): Package
    {
        $trip->loadMissing('tripDays.stops');

        $days = $trip->tripDays->map(fn ($day) => [
            'title' => $day->title ?? "Day {$day->day_number}",
            'place_ids' => $day->stops->pluck('place_id')->all(),
            'overnight_town' => $day->overnight_town,
        ])->all();

        $template = $this->createTemplate($trip->tier, $days);
        $perPerson = $trip->displayTotal() && $trip->travellerCount()
            ? bcdiv((string) $trip->displayTotal(), (string) max($trip->adults, 1), 2)
            : null;

        $slug = Str::slug($name);
        $base = $slug;
        for ($n = 2; Package::where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return Package::create([
            'template_trip_id' => $template->id,
            'name' => $name,
            'slug' => $slug,
            'tier' => $trip->tier,
            'days' => $trip->days,
            'from_price' => $perPerson,
            'summary' => null,
            'is_featured' => false,
            'sort_order' => (int) Package::max('sort_order') + 1,
        ]);
    }

    /**
     * Load a package into a fresh builder draft. Only published places with coordinates are kept.
     */
    public function toDraft(Package $package, TripDraft $current): TripDraft
    {
        $template = $package->templateTrip()->with('tripDays.stops.place')->first();

        $placeIds = $template?->tripDays
            ->flatMap(fn ($day) => $day->stops)
            ->map(fn ($stop) => $stop->place)
            ->filter(fn (?Place $place) => $place && $place->isPublished() && $place->lat !== null)
            ->pluck('id')->unique()->values()->all() ?? [];

        $draft = new TripDraft;
        $draft->tier = ($package->tier ?? Tier::Premium)->value;
        $draft->days = $package->days ?? $template?->days;
        $draft->placeIds = $placeIds;
        $draft->districtIds = Place::whereIn('id', $placeIds)->distinct()->pluck('district_id')->map(fn ($id) => (int) $id)->all();
        $draft->categoryIds = DB::table('category_place')->whereIn('place_id', $placeIds)->distinct()->pluck('category_id')->map(fn ($id) => (int) $id)->all();
        $draft->packageId = $package->id;
        // Keep who is travelling and when, if the traveller already filled that in.
        $draft->startDate = $current->startDate;
        $draft->adults = $current->adults;
        $draft->childrenAges = $current->childrenAges;
        $draft->infants = $current->infants;
        $draft->completedStep = 4;

        return $draft;
    }
}
