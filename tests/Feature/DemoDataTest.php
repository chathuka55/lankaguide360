<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\District;
use App\Models\Hotel;
use App\Models\Place;
use App\Models\RoomRate;
use App\Models\Trip;
use App\Models\TripDay;
use App\Models\User;
use App\Services\DemoHotelService;
use App\Services\Import\WikipediaImporter;
use App\Support\Geo;
use Database\Seeders\CategorySeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\PlaceCoordinateSeeder;
use Database\Seeders\PlaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class DemoDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([LocationSeeder::class, CategorySeeder::class, PlaceSeeder::class, PlaceCoordinateSeeder::class]);
    }

    public function test_demo_hotels_cover_every_district_and_every_located_place_in_every_tier(): void
    {
        // A remote place far from every listed town gets its own demo town.
        $remote = Place::factory()->create(['name' => 'Remote Waterfall', 'district_id' => District::where('slug', 'monaragala')->value('id'), 'lat' => 7.10, 'lng' => 81.55]);

        $this->artisan('lg:demo-hotels')->assertSuccessful();

        $demo = Hotel::where('is_demo', true)->get();
        $this->assertGreaterThan(200, $demo->count());
        $this->assertTrue($demo->every(fn (Hotel $h) => $h->isPublished() && $h->is_active && $h->roomRates()->exists()));
        $this->assertSame(0, RoomRate::whereIn('hotel_id', $demo->pluck('id'))->where('is_estimate', false)->count());

        foreach (['budget', 'premium', 'luxury'] as $tier) {
            $this->assertSame([], District::whereDoesntHave('hotels', fn ($q) => $q->where('is_demo', true)->where('tier', $tier))->pluck('name')->all(), "every district has a {$tier} demo hotel");
        }

        foreach (Place::whereNotNull('lat')->get() as $place) {
            foreach (['budget', 'premium', 'luxury'] as $tier) {
                $nearest = $demo->where('tier', $tier)->min(fn (Hotel $h) => Geo::distanceKm((float) $h->lat, (float) $h->lng, (float) $place->lat, (float) $place->lng));
                $this->assertLessThanOrEqual(DemoHotelService::COVERAGE_KM + 1, $nearest, "{$place->name} has a {$tier} hotel nearby");
            }
        }

        $this->assertTrue($demo->contains(fn (Hotel $h) => $h->district_id === $remote->district_id && Geo::distanceKm((float) $h->lat, (float) $h->lng, 7.10, 81.55) < 2));
    }

    public function test_running_twice_adds_nothing_and_remove_deletes_only_demo_hotels(): void
    {
        $real = Hotel::factory()->published()->create(['name' => 'Real Hotel']);
        $this->artisan('lg:demo-hotels')->assertSuccessful();
        $count = Hotel::count();

        $this->artisan('lg:demo-hotels')->assertSuccessful();
        $this->assertSame($count, Hotel::count());

        // A trip that used a demo hotel keeps its day but loses the hotel.
        $demoHotel = Hotel::where('is_demo', true)->first();
        $day = TripDay::factory()->for(Trip::factory())->create(['hotel_id' => $demoHotel->id, 'room_rate_id' => $demoHotel->roomRates->first()->id]);

        $this->artisan('lg:demo-hotels --remove')->assertSuccessful();

        $this->assertSame(0, Hotel::where('is_demo', true)->count());
        $this->assertSame(0, RoomRate::whereNotIn('hotel_id', Hotel::pluck('id'))->count());
        $this->assertModelExists($real);
        $this->assertNull($day->fresh()->hotel_id);
    }

    public function test_demo_hotels_are_labelled_and_filterable(): void
    {
        $this->artisan('lg:demo-hotels')->assertSuccessful();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $demo = Hotel::where('is_demo', true)->first();
        Hotel::factory()->published()->create(['name' => 'Real Lodge']);

        $this->actingAs($admin)->get(route('admin.hotels.index', ['source' => 'demo', 'q' => $demo->name]))->assertOk()->assertSee($demo->name)->assertSee('Demo listing');
        $this->actingAs($admin)->get(route('admin.hotels.index', ['source' => 'real', 'q' => 'Lodge']))->assertSee('Real Lodge')->assertDontSee($demo->name);
    }

    public function test_checked_coordinates_fill_gaps_fix_errors_and_survive_the_wikipedia_import(): void
    {
        $kalkudah = Place::where('slug', 'kalkudah-beach')->sole();
        $this->assertEqualsWithDelta(81.559, (float) $kalkudah->lng, 0.001, 'East coast, not the Wikipedia point on the west coast');
        $this->assertNotNull($kalkudah->coordinates_checked_at);
        $this->assertNotNull(Place::where('slug', 'pidurangala-rock')->value('lat'));

        // Never read the real import cache in storage/app/import-cache.
        config(['lankaguide.import.cache_path' => storage_path('framework/testing/import-'.uniqid())]);
        Sleep::fake();
        Http::fake(['*' => Http::response([
            'type' => 'standard', 'extract' => 'Kalkudah is a coastal town.',
            'coordinates' => ['lat' => 8.1167, 'lon' => 79.7167],
            'content_urls' => ['desktop' => ['page' => 'https://en.wikipedia.org/wiki/Kalkudah']],
        ])]);
        app(WikipediaImporter::class)->run(['place' => 'kalkudah-beach']);

        $kalkudah->refresh();
        $this->assertEqualsWithDelta(81.559, (float) $kalkudah->lng, 0.001);
        $this->assertSame('Kalkudah is a coastal town.', $kalkudah->description, 'Other fields still update');
    }

    public function test_moving_the_pin_in_admin_marks_the_location_checked(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $place = Place::where('slug', 'ravana-falls')->sole();
        $this->assertNull($place->coordinates_checked_at);

        $this->actingAs($admin)->get(route('admin.places.edit', $place))->assertOk();
        $data = [
            'name' => $place->name, 'slug' => $place->slug, 'district_id' => $place->district_id, 'categories' => $place->categories->pluck('id')->all(),
            'visit_minutes' => 30, 'best_time_slot' => 'any', 'crowd_level' => 'medium', 'fee_foreign_adult' => '0', 'fee_foreign_child' => '0',
            'lat' => '6.8411', 'lng' => '81.0549', 'wikipedia_title' => $place->wikipedia_title, 'status' => 'draft',
        ];
        $this->actingAs($admin)->put(route('admin.places.update', $place), $data)->assertSessionHasNoErrors();

        $this->assertNotNull($place->fresh()->coordinates_checked_at);
        $this->actingAs($admin)->get(route('admin.places.edit', $place))->assertSee('https://www.google.com/maps/search/?api=1&amp;query=6.8411%2C81.0549', false);
    }

    public function test_google_maps_links(): void
    {
        $place = Place::factory()->published()->create(['lat' => 7.956944, 'lng' => 80.759722]);

        $this->assertSame('https://www.google.com/maps/search/?api=1&query=7.956944%2C80.759722', $place->googleMapsUrl());
        $this->assertNull(Place::factory()->make(['lat' => null, 'lng' => null])->googleMapsUrl());
        $this->get($place->url())->assertOk()->assertSee('Open in Google Maps');
    }
}
