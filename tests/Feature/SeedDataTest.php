<?php

namespace Tests\Feature;

use App\Enums\PublishStatus;
use App\Enums\Tier;
use App\Models\Category;
use App\Models\Cuisine;
use App\Models\District;
use App\Models\Guide;
use App\Models\Place;
use App\Models\Province;
use App\Models\Setting;
use App\Models\Vehicle;
use Database\Seeders\PlaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SeedDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_all_provinces_and_districts_are_seeded(): void
    {
        $this->assertSame(9, Province::count());
        $this->assertSame(25, District::count());

        $this->assertSame(
            ['Colombo', 'Gampaha', 'Kalutara'],
            Province::where('slug', 'western')->sole()->districts()->orderBy('id')->pluck('name')->all(),
        );

        District::all()->each(function (District $district) {
            $this->assertNotNull($district->lat, "{$district->name} has no latitude");
            $this->assertTrue($district->lat >= 5.9 && $district->lat <= 9.9, "{$district->name} latitude outside Sri Lanka");
            $this->assertTrue($district->lng >= 79.5 && $district->lng <= 82.0, "{$district->name} longitude outside Sri Lanka");
        });
    }

    public function test_every_district_named_in_the_category_mapping_exists(): void
    {
        $slugs = District::pluck('slug')->all();

        foreach (require database_path('seeders/data/categories.php') as $category) {
            foreach ([...$category['districts'], ...$category['extra_districts']] as $name) {
                $this->assertContains(Str::slug($name), $slugs, "District [{$name}] in [{$category['name']}] was not seeded");
            }
        }
    }

    public function test_categories_are_linked_to_the_srs_districts(): void
    {
        $this->assertSame(6, Category::count());

        $beach = Category::where('slug', 'beach-coastal')->sole();
        $this->assertEqualsCanonicalizing(
            ['galle', 'matara', 'hambantota', 'kalutara', 'gampaha', 'trincomalee', 'batticaloa', 'ampara', 'puttalam'],
            $beach->districts->pluck('slug')->all(),
        );

        // Kandy is both Historical and Hill Country (SRS 4.1).
        $kandy = District::where('slug', 'kandy')->sole();
        $this->assertTrue($kandy->categories->pluck('slug')->contains('historical-cultural'));
        $this->assertTrue($kandy->categories->pluck('slug')->contains('hill-country'));
    }

    public function test_at_least_100_draft_places_are_seeded_without_coordinates(): void
    {
        $this->assertGreaterThanOrEqual(100, Place::count());
        $this->assertSame(Place::count(), Place::where('status', PublishStatus::Draft)->count());
        $this->assertSame(0, Place::whereNotNull('lat')->count(), 'Coordinates come from the Wikipedia importer');
        $this->assertSame(0, Place::whereNull('wikipedia_title')->count());
        $this->assertSame(0, Place::published()->count());
    }

    public function test_place_data_has_unique_slugs_and_wikipedia_titles(): void
    {
        $rows = collect(require database_path('seeders/data/places.php'));

        $this->assertSame($rows->count(), $rows->map(fn ($row) => Str::slug($row[0]))->unique()->count(), 'Duplicate place slug');
        $this->assertSame($rows->count(), $rows->pluck(1)->unique()->count(), 'Duplicate Wikipedia title');
        $this->assertSame($rows->count(), Place::count());
    }

    public function test_every_place_is_reachable_in_the_builder(): void
    {
        // The builder shows a place only when one of its categories is linked to its district.
        Place::with(['categories', 'district.categories'])->get()->each(function (Place $place) {
            $this->assertNotEmpty($place->categories, "{$place->name} has no category");
            $this->assertNotEmpty(
                $place->categories->pluck('id')->intersect($place->district->categories->pluck('id')),
                "{$place->name} ({$place->district->name}) has no category linked to its district",
            );
        });
    }

    public function test_hidden_gem_flag_matches_the_hidden_gems_category(): void
    {
        $gems = Category::where('slug', 'hidden-gems')->sole();

        $this->assertEqualsCanonicalizing(
            $gems->places()->pluck('places.id')->all(),
            Place::hiddenGems()->pluck('id')->all(),
        );
        $this->assertGreaterThan(0, Place::hiddenGems()->count());
    }

    public function test_every_category_key_in_the_place_data_is_known(): void
    {
        $keys = collect(require database_path('seeders/data/places.php'))->pluck(3)->flatten()->unique();

        $this->assertEmpty($keys->diff(array_keys(PlaceSeeder::CATEGORY_KEYS)));
    }

    public function test_each_tier_has_a_vehicle_for_every_group_size_from_1_to_45(): void
    {
        foreach (Tier::cases() as $tier) {
            foreach (range(1, 45) as $pax) {
                $this->assertSame(
                    1,
                    Vehicle::tier($tier)->forPax($pax)->count(),
                    "Expected exactly one {$tier->value} vehicle for {$pax} travellers",
                );
            }
        }
    }

    public function test_guides_cuisines_and_settings_are_seeded(): void
    {
        $this->assertSame(8, Cuisine::count());
        $this->assertGreaterThan(0, Guide::where('type', 'chauffeur')->count());
        $this->assertGreaterThan(0, Guide::where('type', 'national')->count());
        $this->assertSame(2, Guide::speaks('German')->count());

        $this->assertSame('USD', Setting::get('currency'));
        $this->assertSame('10', Setting::get('service_fee_premium'));
        $this->assertNotNull(Setting::get('usd_lkr'));
        $this->assertSame('fallback', Setting::get('missing_key', 'fallback'));
    }

    public function test_seeding_twice_does_not_duplicate_rows(): void
    {
        $counts = fn () => [Place::count(), District::count(), Vehicle::count(), Guide::count(), Setting::count()];
        $before = $counts();

        $this->seed();

        $this->assertSame($before, $counts());
    }
}
