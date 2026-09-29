<?php

namespace App\Services;

use App\Enums\BestTimeSlot;
use App\Enums\CrowdLevel;
use App\Enums\Tier;
use App\Models\District;
use App\Models\Hotel;
use App\Models\Place;
use App\Models\Setting;
use App\Services\Itinerary\Itinerary;
use App\Support\Geo;
use App\Support\TripDraft;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Rule-based itinerary generation from our own database (SRS 5.3). Never invents places.
 *
 *  1. load the chosen published places with coordinates
 *  2. order districts as a nearest-neighbour loop from the arrival point, then places inside
 *     each district by nearest neighbour
 *  3. fill days under the tier's daily hour and stop limits, respecting opening hours;
 *     sunrise places open a day, sunset places close it; lunch 12:30–13:30
 *  4. overnight in the nearest town with a published hotel of the tier
 *  5. the last day ends at the departure point
 *  6. balance to the requested number of days: add leisure days, or drop the lowest-priority
 *     places and say which
 */
class ItineraryService
{
    public function __construct(private RoutingService $routing) {}

    public function generate(TripDraft $draft): Itinerary
    {
        $dropped = [];
        $places = Place::published()->whereKey($draft->placeIds)->with('district')->orderBy('id')->get();

        foreach ($places->filter(fn (Place $p) => ! $p->hasCoordinates()) as $place) {
            $dropped[] = ['place_id' => $place->id, 'name' => $place->name, 'reason' => 'Its map position is not known yet, so it can\'t be routed.'];
        }

        $places = $places->filter(fn (Place $p) => $p->hasCoordinates())->values();

        if ($places->isEmpty()) {
            return new Itinerary([], $dropped, $dropped ? ['None of the chosen places can be routed yet.'] : []);
        }

        $itinerary = $this->plan($draft, $places);

        // Too few days: drop the lowest-priority place and plan again until it fits.
        while ($draft->days && $itinerary->dayCount() > $draft->days && $places->count() > 1) {
            $victim = $this->lowestPriority($places);
            $places = $places->reject(fn (Place $p) => $p->is($victim))->values();
            $dropped[] = ['place_id' => $victim->id, 'name' => $victim->name, 'reason' => "Doesn't fit in {$draft->days} days."];
            $itinerary = $this->plan($draft, $places);
        }

        // More days than needed: add relaxed days where the trip lingers longest (or at the beach).
        // (A separate departure day can merge into a final leisure day, so re-check the count.)
        $leisure = 0;
        while ($draft->days && $itinerary->dayCount() < $draft->days && $leisure < $draft->days) {
            $leisure += $draft->days - $itinerary->dayCount();
            $itinerary = $this->plan($draft, $places, $leisure);
        }

        $itinerary->dropped = $dropped;
        $itinerary->warnings = [...$this->warnings($itinerary, $draft), ...($dropped ? [count($dropped).' place(s) could not be included; see the list below.'] : [])];

        return $itinerary;
    }

    /**
     * Re-time days after the traveller reorders stops or moves one to another day (FR-17).
     *
     * @param  array<int, array<int, int>>  $dayPlaceIds  place ids per day, in order (empty = leisure day)
     */
    public function retime(TripDraft $draft, array $dayPlaceIds): Itinerary
    {
        $places = Place::published()->whereKey(array_merge(...array_values($dayPlaceIds) ?: [[]]))->with('district')->get()->keyBy('id');

        $groups = array_values(array_map(
            fn (array $ids) => collect($ids)->map(fn ($id) => $places->get((int) $id))->filter(fn ($p) => $p?->hasCoordinates())->values(),
            $dayPlaceIds,
        ));

        $itinerary = $this->build($draft, $groups);
        $itinerary->warnings = $this->warnings($itinerary, $draft);

        return $itinerary;
    }

    /**
     * Quick estimate for builder step 4: "12 places ≈ 6 days recommended".
     *
     * @param  Collection<int, Place>  $places
     */
    public function recommendedDays(Collection $places, Tier $tier): int
    {
        if ($places->isEmpty()) {
            return 0;
        }

        $located = $places->filter(fn (Place $p) => $p->hasCoordinates())->values();
        $driveMinutes = 0;
        $prev = config('lankaguide.arrival_points.BIA Katunayake');
        foreach ($this->nearestNeighbour($located, (float) $prev['lat'], (float) $prev['lng']) as $place) {
            $driveMinutes += Geo::distanceKm($prev['lat'], $prev['lng'], (float) $place->lat, (float) $place->lng) * 1.35 / 40 * 60;
            $prev = ['lat' => (float) $place->lat, 'lng' => (float) $place->lng];
        }

        $minutes = $places->sum('visit_minutes') + $driveMinutes;

        return max(2, min(21, (int) ceil($minutes / ($this->dayLimitHours($tier) * 60 * 0.85)) + 1));
    }

    /**
     * Order, split into days, optionally add leisure days, then time everything.
     *
     * @param  Collection<int, Place>  $places
     */
    private function plan(TripDraft $draft, Collection $places, int $leisureDays = 0): Itinerary
    {
        $arrival = $this->point($draft->arrivalPoint);
        $groups = $this->split($draft, $this->order($places, $arrival));

        if ($leisureDays > 0) {
            $groups = $this->insertLeisureDays($draft, $groups, $leisureDays);
        }

        return $this->build($draft, $groups);
    }

    /**
     * Districts in a nearest-neighbour loop from the arrival point (by district centroid),
     * places inside each district by nearest neighbour from where we arrive.
     *
     * @param  Collection<int, Place>  $places
     * @param  array{label: string, lat: float, lng: float}  $start
     * @return Collection<int, Place>
     */
    private function order(Collection $places, array $start): Collection
    {
        $byDistrict = $places->groupBy('district_id');
        $districts = District::whereKey($byDistrict->keys())->get()
            ->map(function (District $district) use ($byDistrict) {
                // Districts without a centroid use the average of their chosen places.
                $district->lat ??= $byDistrict[$district->id]->avg('lat');
                $district->lng ??= $byDistrict[$district->id]->avg('lng');

                return $district;
            });

        $ordered = collect();
        $lat = $start['lat'];
        $lng = $start['lng'];

        foreach ($this->nearestNeighbour($districts, $lat, $lng) as $district) {
            foreach ($this->nearestNeighbour($byDistrict[$district->id], $lat, $lng) as $place) {
                $ordered->push($place);
                $lat = (float) $place->lat;
                $lng = (float) $place->lng;
            }
        }

        return $ordered;
    }

    /**
     * @template T of object
     *
     * @param  Collection<int, T>  $items  with lat/lng properties
     * @return Collection<int, T>
     */
    private function nearestNeighbour(Collection $items, float $lat, float $lng): Collection
    {
        $remaining = $items->sortBy('id')->values();
        $ordered = collect();

        while ($remaining->isNotEmpty()) {
            $nextIndex = $remaining->keys()->sortBy(fn ($i) => [Geo::distanceKm($lat, $lng, (float) $remaining[$i]->lat, (float) $remaining[$i]->lng), $remaining[$i]->id])->first();
            $next = $remaining->pull($nextIndex);
            $ordered->push($next);
            $lat = (float) $next->lat;
            $lng = (float) $next->lng;
        }

        return $ordered;
    }

    /**
     * Greedy day filling: keep adding places while the day stays within the limits.
     *
     * @param  Collection<int, Place>  $ordered
     * @return array<int, Collection<int, Place>>
     */
    private function split(TripDraft $draft, Collection $ordered): array
    {
        $groups = [];
        $current = collect();
        $position = $this->point($draft->arrivalPoint);

        foreach ($ordered as $place) {
            $isSunrise = $place->best_time_slot === BestTimeSlot::Sunrise;
            $candidate = $current->concat([$place]);
            $lastIsSunset = $current->last()?->best_time_slot === BestTimeSlot::Sunset;

            $fits = $current->isEmpty()
                || (! $isSunrise && ! $lastIsSunset && $this->simulateDay($draft, $position, $candidate)['fits']);

            if ($fits) {
                $current = $candidate;

                continue;
            }

            $groups[] = $current;
            $position = $this->overnight($draft->tierEnum(), $current->last());
            $current = collect([$place]);
        }

        if ($current->isNotEmpty()) {
            $groups[] = $current;
        }

        return $groups;
    }

    /**
     * Put the extra days after the day that ends in a beach town, else after the
     * longest run of nights in one town, else before the last day.
     *
     * @param  array<int, Collection<int, Place>>  $groups
     * @return array<int, Collection<int, Place>>
     */
    private function insertLeisureDays(TripDraft $draft, array $groups, int $count): array
    {
        $beachIndex = null;
        foreach ($groups as $i => $group) {
            if ($group->contains(fn (Place $p) => $p->categories()->where('slug', 'beach-coastal')->exists())) {
                $beachIndex = $i;
            }
        }

        $index = $beachIndex ?? max(0, count($groups) - 2);
        array_splice($groups, $index + 1, 0, array_fill(0, $count, collect()));

        return $groups;
    }

    /**
     * Time every day: legs, arrival/departure times, lunch, overnight town, departure transfer.
     *
     * @param  array<int, Collection<int, Place>>  $groups  empty collection = leisure day
     */
    private function build(TripDraft $draft, array $groups): Itinerary
    {
        $tier = $draft->tierEnum();
        $start = $draft->start() ?? now()->addMonth()->startOfDay();
        $position = $this->point($draft->arrivalPoint);
        $departure = $this->point($draft->departurePoint);
        $days = [];
        $number = 1;

        foreach (array_values($groups) as $i => $group) {
            $isLast = $i === count($groups) - 1;
            $date = $start->copy()->addDays($number - 1);

            if ($group->isEmpty()) {
                // A final leisure day still ends with the drive to the departure point.
                $transfer = null;
                if ($isLast) {
                    $leg = $this->routing->leg($position['lat'], $position['lng'], $departure['lat'], $departure['lng']);
                    $transfer = $this->transferArray($departure, $leg, $this->startTime($tier));
                }

                $days[] = [
                    'number' => $number++, 'date' => $date->toDateString(), 'type' => 'leisure',
                    'title' => $isLast ? 'Leisure morning in '.$position['label'].' → '.$departure['label'] : 'Leisure day in '.$position['label'],
                    'start' => $position, 'stops' => [], 'lunch_at' => null, 'end_transfer' => $transfer,
                    'overnight' => $isLast ? null : $this->overnightArray($position),
                    'drive_km' => (float) ($transfer['km'] ?? 0), 'drive_minutes' => (int) ($transfer['minutes'] ?? 0),
                    'ends_at' => $transfer['arrive'] ?? null,
                ];

                continue;
            }

            $sim = $this->simulateDay($draft, $position, $group);
            $last = $group->last();
            $overnight = $this->overnight($tier, $last);
            $transfer = null;

            if ($isLast) {
                $leg = $this->routing->leg((float) $last->lat, (float) $last->lng, $departure['lat'], $departure['lng']);
                $withTransfer = $this->dayLimitHours($tier) * 60 >= $sim['active_minutes'] + $leg->minutes;
                if ($withTransfer) {
                    $transfer = $this->transferArray($departure, $leg, $sim['ends_at']);
                    $overnight = null;
                }
            }

            $days[] = [
                'number' => $number++, 'date' => $date->toDateString(), 'type' => 'touring',
                'title' => $position['label'].' → '.($overnight['label'] ?? $departure['label']),
                'start' => $position, 'stops' => $sim['stops'], 'lunch_at' => $sim['lunch_at'],
                'end_transfer' => $transfer,
                'overnight' => $overnight ? $this->overnightArray($overnight) : null,
                'drive_km' => round($sim['drive_km'] + ($transfer['km'] ?? 0), 1),
                'drive_minutes' => $sim['drive_minutes'] + ($transfer['minutes'] ?? 0),
                'ends_at' => $transfer['arrive'] ?? $sim['ends_at'],
            ];

            $position = $overnight ?? $departure;

            // Last touring day too long to also reach the airport: separate departure day.
            if ($isLast && $overnight !== null) {
                $leg = $this->routing->leg($overnight['lat'], $overnight['lng'], $departure['lat'], $departure['lng']);
                $startTime = $this->startTime($tier);
                $days[] = [
                    'number' => $number, 'date' => $start->copy()->addDays($number - 1)->toDateString(), 'type' => 'departure',
                    'title' => $overnight['label'].' → '.$departure['label'],
                    'start' => $overnight, 'stops' => [], 'lunch_at' => null,
                    'end_transfer' => $this->transferArray($departure, $leg, $startTime),
                    'overnight' => null, 'drive_km' => $leg->km, 'drive_minutes' => $leg->minutes,
                    'ends_at' => $this->addMinutes($startTime, $leg->minutes),
                ];
                $number++;
            }
        }

        return new Itinerary($days);
    }

    /**
     * Simulate one day from $start through $places.
     *
     * @param  array{label: string, lat: float, lng: float}  $start
     * @param  Collection<int, Place>  $places
     * @return array{fits: bool, stops: array, lunch_at: ?string, drive_km: float, drive_minutes: int, active_minutes: int, ends_at: string}
     */
    private function simulateDay(TripDraft $draft, array $start, Collection $places): array
    {
        $tier = $draft->tierEnum();
        $firstIsSunrise = $places->first()?->best_time_slot === BestTimeSlot::Sunrise;
        $clock = $this->minutes($firstIsSunrise ? config('lankaguide.itinerary.sunrise_start') : $this->startTime($tier));
        $lunchFrom = $this->minutes(config('lankaguide.itinerary.lunch.from'));
        $lunchLength = (int) config('lankaguide.itinerary.lunch.minutes');

        $lat = $start['lat'];
        $lng = $start['lng'];
        $stops = [];
        $driveKm = 0.0;
        $driveMinutes = 0;
        $visitMinutes = 0;
        $lunchAt = null;
        $closedViolation = false;

        foreach ($places as $place) {
            $leg = $this->routing->leg($lat, $lng, (float) $place->lat, (float) $place->lng);
            $clock += $leg->minutes;
            $driveKm += $leg->km;
            $driveMinutes += $leg->minutes;

            if ($lunchAt === null && $clock >= $lunchFrom) {
                $lunchAt = $this->format($clock);
                $clock += $lunchLength;
            }

            if ($place->open_time && $clock < $this->minutes($place->open_time)) {
                $clock = $this->minutes($place->open_time);
            }

            $arrive = $clock;
            $clock += $place->visit_minutes;
            $visitMinutes += $place->visit_minutes;

            if ($place->close_time && $clock > $this->minutes($place->close_time)) {
                $closedViolation = true;
            }

            $stops[] = [
                'place_id' => $place->id,
                'name' => $place->name,
                'slug' => $place->slug,
                'district' => $place->district?->name,
                'district_slug' => $place->district?->slug,
                'lat' => (float) $place->lat,
                'lng' => (float) $place->lng,
                'arrive' => $this->format($arrive),
                'depart' => $this->format($clock),
                'visit_minutes' => $place->visit_minutes,
                'km_from_prev' => $leg->km,
                'minutes_from_prev' => $leg->minutes,
                'geometry' => $leg->geometry,
                'best_time_slot' => $place->best_time_slot->value,
            ];

            $lat = (float) $place->lat;
            $lng = (float) $place->lng;
        }

        $active = $driveMinutes + $visitMinutes;
        $fits = $active <= $this->dayLimitHours($tier) * 60
            && count($stops) <= (int) config("lankaguide.itinerary.max_stops.{$tier->value}")
            && ! $closedViolation;

        return [
            'fits' => $fits,
            'stops' => $stops,
            'lunch_at' => $lunchAt,
            'drive_km' => round($driveKm, 1),
            'drive_minutes' => $driveMinutes,
            'active_minutes' => $active,
            'ends_at' => $this->format($clock),
        ];
    }

    /**
     * Nearest town with a published hotel of the tier (any tier as a fallback).
     *
     * @return array{label: string, lat: float, lng: float, district_id: ?int, hotel_tier: ?string}
     */
    private function overnight(Tier $tier, Place $near): array
    {
        $radius = (float) config('lankaguide.itinerary.hotel_search_km');
        $hotel = Hotel::published()->tier($tier)->nearTo((float) $near->lat, (float) $near->lng, $radius)->first()
            ?? Hotel::published()->nearTo((float) $near->lat, (float) $near->lng, $radius)->first();

        if ($hotel) {
            return ['label' => $hotel->town, 'lat' => (float) $hotel->lat, 'lng' => (float) $hotel->lng, 'district_id' => $hotel->district_id, 'hotel_tier' => $hotel->tier->value];
        }

        return ['label' => $near->district?->name ?? $near->name, 'lat' => (float) $near->lat, 'lng' => (float) $near->lng, 'district_id' => $near->district_id, 'hotel_tier' => null];
    }

    /**
     * @return array{town: string, lat: float, lng: float, district_id: ?int, has_hotel_of_tier: bool}
     */
    private function overnightArray(array $point): array
    {
        return [
            'town' => $point['label'],
            'lat' => $point['lat'],
            'lng' => $point['lng'],
            'district_id' => $point['district_id'] ?? null,
            'hotel_tier' => $point['hotel_tier'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transferArray(array $departure, $leg, string $from): array
    {
        return [
            'to' => $departure['label'],
            'km' => $leg->km,
            'minutes' => $leg->minutes,
            'arrive' => $this->addMinutes($from, $leg->minutes),
            'geometry' => $leg->geometry,
        ];
    }

    /**
     * Lowest priority first: well-known crowded places and chosen hidden gems are kept longest;
     * among equals, the one furthest from the rest of the trip goes first.
     *
     * @param  Collection<int, Place>  $places
     */
    private function lowestPriority(Collection $places): Place
    {
        $centreLat = $places->avg('lat');
        $centreLng = $places->avg('lng');

        return $places->sortBy([
            fn (Place $a, Place $b) => $this->priority($a) <=> $this->priority($b),
            fn (Place $a, Place $b) => Geo::distanceKm($centreLat, $centreLng, (float) $b->lat, (float) $b->lng) <=> Geo::distanceKm($centreLat, $centreLng, (float) $a->lat, (float) $a->lng),
            fn (Place $a, Place $b) => $b->id <=> $a->id,
        ])->first();
    }

    private function priority(Place $place): int
    {
        return ($place->is_hidden_gem ? 3 : 0) + match ($place->crowd_level) {
            CrowdLevel::High => 2,
            CrowdLevel::Medium => 1,
            default => 0,
        };
    }

    /**
     * @return array<int, string>
     */
    private function warnings(Itinerary $itinerary, TripDraft $draft): array
    {
        $tier = $draft->tierEnum();
        $threshold = (int) config("lankaguide.itinerary.warn_drive_minutes.{$tier->value}");
        $warnings = [];

        foreach ($itinerary->days as $day) {
            if ($day['drive_minutes'] > $threshold) {
                $warnings[] = sprintf('Day %d has about %s of driving.', $day['number'], $this->duration($day['drive_minutes']));
            }
            if (count($day['stops']) > (int) config("lankaguide.itinerary.max_stops.{$tier->value}")) {
                $warnings[] = sprintf('Day %d has %d stops — a busy day.', $day['number'], count($day['stops']));
            }
            if (! empty($day['overnight']) && $day['overnight']['hotel_tier'] === null) {
                $warnings[] = sprintf('No hotel is listed near %s yet; your agent will arrange one.', $day['overnight']['town']);
            }
        }

        return $warnings;
    }

    public function dayLimitHours(Tier $tier): float
    {
        return (float) (Setting::get("day_limit_hours_{$tier->value}") ?? config("lankaguide.itinerary.day_limit_hours.{$tier->value}"));
    }

    private function startTime(Tier $tier): string
    {
        return config("lankaguide.itinerary.start_time.{$tier->value}");
    }

    /**
     * @return array{label: string, lat: float, lng: float}
     */
    private function point(string $name): array
    {
        $point = config("lankaguide.arrival_points.{$name}") ?? config('lankaguide.arrival_points.BIA Katunayake');

        return ['label' => $name, 'lat' => (float) $point['lat'], 'lng' => (float) $point['lng']];
    }

    private function minutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time));

        return $h * 60 + $m;
    }

    private function format(int $minutes): string
    {
        $minutes = min($minutes, 23 * 60 + 59);

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    private function addMinutes(string $time, int $minutes): string
    {
        return $this->format($this->minutes($time) + $minutes);
    }

    private function duration(int $minutes): string
    {
        return intdiv($minutes, 60).' h'.($minutes % 60 ? ' '.($minutes % 60).' min' : '');
    }

    /**
     * Dates for display, e.g. "Mon 12 Jan".
     */
    public static function dayLabel(string $date): string
    {
        return Carbon::parse($date)->format('D j M');
    }
}
