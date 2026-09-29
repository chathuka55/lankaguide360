<?php

namespace Tests\Feature;

use App\Enums\Tier;
use App\Models\District;
use App\Models\Hotel;
use App\Models\Place;
use App\Models\RouteCache;
use App\Services\ItineraryService;
use App\Services\RoutingService;
use App\Support\TripDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ItineraryServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, Place> */
    private array $places = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['lankaguide.routing.ors_key' => null]);

        $kandy = District::factory()->create(['name' => 'Kandy', 'slug' => 'kandy', 'lat' => 7.27, 'lng' => 80.71]);
        $matale = District::factory()->create(['name' => 'Matale', 'slug' => 'matale', 'lat' => 7.67, 'lng' => 80.73]);
        $nuwara = District::factory()->create(['name' => 'Nuwara Eliya', 'slug' => 'nuwara-eliya', 'lat' => 6.98, 'lng' => 80.70]);
        $badulla = District::factory()->create(['name' => 'Badulla', 'slug' => 'badulla', 'lat' => 7.00, 'lng' => 81.10]);

        foreach ([
            ['Temple of the Tooth', $kandy, 7.2936, 80.6413, 90, 'any'],
            ['Peradeniya Gardens', $kandy, 7.2714, 80.5957, 150, 'morning'],
            ['Kandy Lake', $kandy, 7.2906, 80.6440, 45, 'sunset'],
            ['Sigiriya', $matale, 7.9570, 80.7600, 180, 'sunrise'],
            ['Dambulla Cave Temple', $matale, 7.8567, 80.6492, 90, 'any'],
            ['Aluvihare', $matale, 7.4978, 80.6215, 60, 'any'],
            ['Gregory Lake', $nuwara, 6.9567, 80.7806, 60, 'afternoon'],
            ['Horton Plains', $nuwara, 6.8023, 80.8016, 240, 'sunrise'],
            ['Hakgala Gardens', $nuwara, 6.9267, 80.8189, 90, 'any'],
            ['Nine Arch Bridge', $badulla, 6.8768, 81.0608, 60, 'morning'],
        ] as [$name, $district, $lat, $lng, $minutes, $slot]) {
            $this->places[$name] = Place::factory()->published()->for($district)->create([
                'name' => $name, 'lat' => $lat, 'lng' => $lng, 'visit_minutes' => $minutes, 'best_time_slot' => $slot, 'open_time' => null, 'close_time' => null,
            ]);
        }

        foreach ([['Kandy', $kandy, 7.29, 80.63], ['Sigiriya', $matale, 7.95, 80.75], ['Nuwara Eliya', $nuwara, 6.97, 80.77], ['Ella', $badulla, 6.87, 81.05]] as [$town, $district, $lat, $lng]) {
            Hotel::factory()->published()->for($district)->create(['town' => $town, 'tier' => Tier::Premium, 'lat' => $lat, 'lng' => $lng]);
        }
    }

    private function draft(array $placeNames, ?int $days = null, string $tier = 'premium'): TripDraft
    {
        $draft = new TripDraft;
        $draft->tier = $tier;
        $draft->startDate = '2026-12-01';
        $draft->days = $days;
        $draft->placeIds = array_map(fn ($name) => $this->places[$name]->id, $placeNames);

        return $draft;
    }

    private function allPlaces(): array
    {
        return array_keys($this->places);
    }

    public function test_ten_places_in_four_districts_over_six_days(): void
    {
        $itinerary = app(ItineraryService::class)->generate($this->draft($this->allPlaces(), 6));

        $this->assertSame(6, $itinerary->dayCount());
        $this->assertSame(['2026-12-01', '2026-12-02', '2026-12-03', '2026-12-04', '2026-12-05', '2026-12-06'], array_column($itinerary->days, 'date'));

        $limit = app(ItineraryService::class)->dayLimitHours(Tier::Premium) * 60;
        foreach ($itinerary->days as $day) {
            $active = $day['drive_minutes'] + array_sum(array_column($day['stops'], 'visit_minutes'));
            $this->assertLessThanOrEqual($limit + 240, $active, "Day {$day['number']} is far over the daily limit");
            $this->assertLessThanOrEqual(4, count($day['stops']), 'Premium pacing: max 4 stops');
        }

        $this->assertSame([], array_diff(array_map(fn ($p) => $p->id, $this->places), [...$itinerary->placeIds(), ...array_column($itinerary->dropped, 'place_id')]), 'Every place is visited or reported as dropped');
    }

    public function test_touring_days_stay_within_the_daily_hour_limit(): void
    {
        $itinerary = app(ItineraryService::class)->generate($this->draft($this->allPlaces()));
        $limit = app(ItineraryService::class)->dayLimitHours(Tier::Premium) * 60;

        foreach ($itinerary->days as $day) {
            if (count($day['stops']) > 1) {
                $touring = array_sum(array_column($day['stops'], 'minutes_from_prev')) + array_sum(array_column($day['stops'], 'visit_minutes'));
                $this->assertLessThanOrEqual($limit, $touring, "Day {$day['number']}");
            }
        }
        $this->assertSame(10, count($itinerary->placeIds()), 'Without a day limit nothing is dropped');
    }

    public function test_sunrise_places_start_their_day_and_sunset_places_end_it(): void
    {
        $itinerary = app(ItineraryService::class)->generate($this->draft($this->allPlaces()));

        foreach ($itinerary->days as $day) {
            foreach ($day['stops'] as $i => $stop) {
                if ($stop['best_time_slot'] === 'sunrise') {
                    $this->assertSame(0, $i, "{$stop['name']} should be the first stop of day {$day['number']}");
                    $this->assertLessThan('08:00', $stop['arrive']);
                }
                if ($stop['best_time_slot'] === 'sunset') {
                    $this->assertSame(count($day['stops']) - 1, $i, "{$stop['name']} should be the last stop");
                }
            }
        }
    }

    public function test_output_is_deterministic(): void
    {
        $service = app(ItineraryService::class);

        $this->assertSame(
            $service->generate($this->draft($this->allPlaces(), 7))->toArray(),
            $service->generate($this->draft(array_reverse($this->allPlaces()), 7))->toArray(),
        );
    }

    public function test_too_few_days_drops_places_with_reasons(): void
    {
        $itinerary = app(ItineraryService::class)->generate($this->draft($this->allPlaces(), 3));

        $this->assertLessThanOrEqual(3, $itinerary->dayCount());
        $this->assertNotEmpty($itinerary->dropped);
        $this->assertStringContainsString("Doesn't fit in 3 days", $itinerary->dropped[0]['reason']);
        $this->assertNotEmpty(array_filter($itinerary->warnings, fn ($w) => str_contains($w, 'could not be included')));
    }

    public function test_more_days_adds_leisure_days(): void
    {
        $itinerary = app(ItineraryService::class)->generate($this->draft(['Temple of the Tooth', 'Kandy Lake'], 4));

        $this->assertSame(4, $itinerary->dayCount());
        $this->assertContains('leisure', array_column($itinerary->days, 'type'));
    }

    public function test_overnight_towns_have_hotels_of_the_tier_and_the_trip_ends_at_the_airport(): void
    {
        $itinerary = app(ItineraryService::class)->generate($this->draft(['Sigiriya', 'Dambulla Cave Temple', 'Temple of the Tooth']));

        $first = $itinerary->days[0];
        $this->assertStringStartsWith('BIA Katunayake → ', $first['title']);
        foreach (array_slice($itinerary->days, 0, -1) as $day) {
            $this->assertContains($day['overnight']['town'], ['Kandy', 'Sigiriya', 'Nuwara Eliya', 'Ella']);
            $this->assertSame('premium', $day['overnight']['hotel_tier']);
        }

        $last = end($itinerary->days);
        $this->assertNull($last['overnight']);
        $this->assertSame('BIA Katunayake', $last['end_transfer']['to']);
    }

    public function test_long_driving_days_are_flagged(): void
    {
        $draft = $this->draft(['Nine Arch Bridge'], null, 'luxury');
        $itinerary = app(ItineraryService::class)->generate($draft);

        $this->assertNotEmpty(array_filter($itinerary->warnings, fn ($w) => str_contains($w, 'of driving')));
    }

    public function test_retime_keeps_the_travellers_order(): void
    {
        $service = app(ItineraryService::class);
        $draft = $this->draft(['Temple of the Tooth', 'Kandy Lake', 'Peradeniya Gardens']);

        $itinerary = $service->retime($draft, [
            [$this->places['Peradeniya Gardens']->id, $this->places['Temple of the Tooth']->id],
            [],
            [$this->places['Kandy Lake']->id],
        ]);

        $this->assertSame(['Peradeniya Gardens', 'Temple of the Tooth'], array_column($itinerary->days[0]['stops'], 'name'));
        $this->assertSame('leisure', $itinerary->days[1]['type']);
        $this->assertSame(['Kandy Lake'], array_column($itinerary->days[2]['stops'], 'name'));
    }

    public function test_unpublished_or_unlocated_places_are_reported(): void
    {
        $this->places['Aluvihare']->update(['lat' => null, 'lng' => null]);

        $itinerary = app(ItineraryService::class)->generate($this->draft(['Aluvihare', 'Temple of the Tooth']));

        $this->assertSame([$this->places['Temple of the Tooth']->id], $itinerary->placeIds());
        $this->assertSame('Aluvihare', $itinerary->dropped[0]['name']);
    }

    public function test_recommended_days(): void
    {
        $days = app(ItineraryService::class)->recommendedDays(collect(array_values($this->places)), Tier::Premium);

        $this->assertGreaterThanOrEqual(4, $days);
        $this->assertLessThanOrEqual(10, $days);
    }

    public function test_offline_estimates_are_cached_and_used_again(): void
    {
        $routing = app(RoutingService::class);

        $leg = $routing->leg(7.2936, 80.6413, 7.9570, 80.7600);
        $this->assertSame('estimate', $leg->provider);
        $this->assertGreaterThan(70, $leg->km);
        $this->assertSame(1, RouteCache::count());

        $again = $routing->leg(7.29361, 80.64129, 7.95701, 80.76001);
        $this->assertSame($leg->minutes, $again->minutes);
        $this->assertSame(1, RouteCache::count(), 'Rounded coordinates hit the same cache row');
    }

    public function test_hill_country_legs_are_slower(): void
    {
        $routing = app(RoutingService::class);
        $hills = $routing->estimate(6.97, 80.77, 6.87, 81.05);
        $plains = $routing->estimate(7.9, 80.3, 7.8, 80.58);

        $this->assertGreaterThan($plains->minutes / $plains->km, $hills->minutes / $hills->km);
    }

    public function test_openrouteservice_is_used_when_a_key_is_set_and_replaces_estimates(): void
    {
        app(RoutingService::class)->leg(7.2936, 80.6413, 7.9570, 80.7600);

        config(['lankaguide.routing.ors_key' => 'test-key']);
        Http::fake(['api.openrouteservice.org/*' => Http::response([
            'features' => [[
                'properties' => ['summary' => ['distance' => 95300, 'duration' => 8400]],
                'geometry' => ['coordinates' => [[80.6413, 7.2936], [80.70, 7.60], [80.7600, 7.9570]]],
            ]],
        ])]);

        $leg = app(RoutingService::class)->leg(7.2936, 80.6413, 7.9570, 80.7600);

        $this->assertSame('openrouteservice', $leg->provider);
        $this->assertSame(95.3, $leg->km);
        $this->assertSame(140, $leg->minutes);
        $this->assertSame([7.6, 80.7], $leg->geometry[1]);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'test-key'));
        $this->assertSame('openrouteservice', RouteCache::sole()->provider);
    }

    public function test_api_failure_falls_back_to_the_estimate(): void
    {
        config(['lankaguide.routing.ors_key' => 'test-key']);
        Http::fake(['api.openrouteservice.org/*' => Http::response('down', 500)]);

        $this->assertSame('estimate', app(RoutingService::class)->leg(7.2936, 80.6413, 7.9570, 80.7600)->provider);
    }
}
