<?php

namespace Tests\Feature;

use App\Enums\TripStatus;
use App\Enums\UserRole;
use App\Models\Media;
use App\Models\Package;
use App\Models\Place;
use App\Models\Trip;
use App\Models\User;
use App\Support\TripDraft;
use Database\Seeders\CategorySeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\PackageSeeder;
use Database\Seeders\PlaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackagesAndSeoTest extends TestCase
{
    use RefreshDatabase;

    private function seedPackages(): void
    {
        $this->seed([LocationSeeder::class, CategorySeeder::class, PlaceSeeder::class, PackageSeeder::class]);
    }

    public function test_package_seeder_creates_six_packages_with_template_trips_and_is_idempotent(): void
    {
        $this->seedPackages();
        $this->seed(PackageSeeder::class);

        $this->assertSame(6, Package::count());
        $classic = Package::where('slug', 'cultural-triangle-classic')->sole();
        $this->assertSame(5, $classic->days);
        $this->assertSame(TripStatus::Draft, $classic->templateTrip->status);
        $this->assertSame(5, $classic->templateTrip->tripDays()->count());
        $this->assertGreaterThan(5, $classic->templateTrip->tripDays()->withCount('stops')->get()->sum('stops_count'));
        $this->assertSame(0, $classic->templateTrip->travellers()->count());
    }

    public function test_template_trips_stay_out_of_the_agent_queue(): void
    {
        $this->seedPackages();
        $agent = User::factory()->create(['role' => UserRole::Agent]);

        $this->actingAs($agent)->get(route('admin.trips.index', ['q' => 'PKG-']))->assertOk()->assertSee('No trips in this list.');
    }

    public function test_package_pages(): void
    {
        $this->seedPackages();
        $package = Package::where('slug', 'hill-country-tea-trails')->sole();

        $this->get(route('packages.index'))->assertOk()->assertSee('Hill Country and Tea Trails')->assertSee('Grand Sri Lanka in 10 Days');
        $this->get(route('packages.show', $package))
            ->assertOk()
            ->assertSee('Day 3')
            ->assertSee('Book Now')
            ->assertSee('"@type":"TouristTrip"', false);
        $this->get('/packages/no-such-package')->assertNotFound();
    }

    public function test_customize_loads_the_package_into_the_builder_draft(): void
    {
        $this->seedPackages();
        $package = Package::where('slug', 'cultural-triangle-classic')->sole();
        $sigiriya = Place::where('name', 'Sigiriya Rock Fortress')->sole();
        $sigiriya->update(['status' => 'published', 'lat' => 7.957, 'lng' => 80.760]);

        $this->withSession([TripDraft::SESSION_KEY => ['adults' => 4, 'start_date' => '2026-12-10']])
            ->post(route('packages.customize', $package), ['mode' => 'customize'])
            ->assertRedirect(route('plan', ['step' => 4]));

        $draft = TripDraft::fromSession(session()->driver());
        $this->assertSame('premium', $draft->tier);
        $this->assertSame(5, $draft->days);
        $this->assertSame([$sigiriya->id], $draft->placeIds, 'Only published places with coordinates are loaded.');
        $this->assertSame($package->id, $draft->packageId);
        $this->assertSame(4, $draft->adults);
        $this->assertSame('2026-12-10', $draft->startDate);
        $this->assertContains($sigiriya->district_id, $draft->districtIds);

        $this->post(route('packages.customize', $package), ['mode' => 'book'])->assertRedirect(route('plan', ['step' => 5]));
    }

    public function test_admin_saves_a_trip_as_a_package(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $agent = User::factory()->create(['role' => UserRole::Agent]);
        $trip = Trip::factory()->status(TripStatus::Approved)->withDays(2)->create(['days' => 2, 'adults' => 2, 'final_total' => '1500.00']);
        $trip->travellers()->create(['full_name' => 'Private Person', 'is_lead' => true]);

        $this->actingAs($agent)->post(route('admin.trips.package', $trip), ['name' => 'Agent package'])->assertForbidden();

        $this->actingAs($admin)->post(route('admin.trips.package', $trip), ['name' => 'Two-day sampler'])->assertRedirect();

        $package = Package::where('slug', 'two-day-sampler')->sole();
        $this->assertSame('750.00', $package->from_price);
        $this->assertNotSame($trip->id, $package->template_trip_id);
        $this->assertSame(TripStatus::Draft, $package->templateTrip->status);
        $this->assertSame(4, $package->templateTrip->tripDays()->withCount('stops')->get()->sum('stops_count'));
        $this->assertSame(0, $package->templateTrip->travellers()->count());

        $this->actingAs($admin)->post(route('admin.trips.package', $trip), ['name' => 'Two-day sampler']);
        $this->assertTrue(Package::where('slug', 'two-day-sampler-2')->exists());
    }

    public function test_sitemap_lists_public_pages_only(): void
    {
        $published = Place::factory()->published()->create(['name' => 'Visible Falls']);
        $draft = Place::factory()->create(['name' => 'Hidden Draft', 'slug' => 'hidden-draft']);

        $response = $this->get('/sitemap.xml');

        $response->assertOk()->assertHeader('content-type', 'application/xml; charset=UTF-8');
        $response->assertSee(e($published->url()), false);
        $response->assertSee(route('packages.index'), false);
        $response->assertDontSee('hidden-draft');
        $response->assertDontSee('/admin');
    }

    public function test_robots_txt(): void
    {
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /');

        $this->app['env'] = 'production';
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /admin')->assertSee('Sitemap: '.route('sitemap'));
    }

    public function test_security_headers(): void
    {
        $response = $this->get(route('home'));

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString("frame-ancestors 'self'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('tile.openstreetmap.org', $response->headers->get('Content-Security-Policy'));
    }

    public function test_image_credits_page(): void
    {
        $place = Place::factory()->published()->create(['name' => 'Credited Temple']);
        Media::factory()->for($place, 'mediable')->create(['status' => 'published', 'author' => 'Jane Photographer', 'license' => 'CC BY-SA 4.0', 'license_url' => 'https://creativecommons.org/licenses/by-sa/4.0/']);

        $this->get(route('credits'))->assertOk()->assertSee('Jane Photographer')->assertSee('CC BY-SA 4.0')->assertSee('Credited Temple');
    }

    public function test_place_page_has_structured_data(): void
    {
        $place = Place::factory()->published()->create(['name' => 'Structured Rock']);

        $this->get($place->url())->assertOk()->assertSee('"@type":"TouristAttraction"', false)->assertSee('"@type":"BreadcrumbList"', false);
    }
}
