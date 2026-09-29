<?php

namespace Tests\Feature\Import;

use App\Enums\PublishStatus;
use App\Enums\Tier;
use App\Models\District;
use App\Models\Hotel;
use App\Models\ImportLog;
use App\Models\Place;
use App\Services\Import\OverpassHotelImporter;
use App\Services\Import\OverpassPlaceSuggester;
use Illuminate\Support\Facades\Http;

class OverpassImportersTest extends ImportTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->writeKandyGeometry();
    }

    public function test_hotels_are_imported_as_drafts_with_guessed_tier_and_town(): void
    {
        Http::fake(['overpass-api.de/*' => Http::response($this->fixture('overpass-hotels.json'))]);

        $report = app(OverpassHotelImporter::class)->run(['district' => 'kandy']);

        $this->assertSame(3, $report->created, 'Unnamed and out-of-district stays are skipped');
        $this->assertSame(3, Hotel::count());
        $this->assertSame(3, Hotel::where('status', PublishStatus::Draft)->count());

        $resort = Hotel::where('osm_id', 'node/10')->sole();
        $this->assertSame(Tier::Luxury, $resort->tier);
        $this->assertSame(5, $resort->star_rating);
        $this->assertSame('Kandy', $resort->town, 'Nearest OSM town when there is no address');
        $this->assertSame(['wifi', 'pool'], $resort->amenities);
        $this->assertSame('grand-hill-resort-kandy', $resort->slug);
        $this->assertSame(District::where('slug', 'kandy')->value('id'), $resort->district_id);

        $guestHouse = Hotel::where('osm_id', 'way/20')->sole();
        $this->assertSame(Tier::Budget, $guestHouse->tier);
        $this->assertSame('Peradeniya', $guestHouse->town, 'addr:city wins');
        $this->assertSame('12 Temple Road, Peradeniya', $guestHouse->address);
        $this->assertSame('+94 81 222 3333', $guestHouse->phone);
        $this->assertEqualsWithDelta(7.27, (float) $guestHouse->lat, 0.00001, 'Ways use their center point');

        $boutique = Hotel::where('osm_id', 'node/30')->sole();
        $this->assertSame('Lakeside Boutique Hotel', $boutique->name, 'name:en preferred');
        $this->assertSame(Tier::Premium, $boutique->tier, '"4S" stars → premium');
        $this->assertSame('https://lakeside.example', $boutique->website);

        Http::assertSent(fn ($request) => str_contains(urldecode($request->body()), '(7.1,80.5,7.5,80.9)')
            && str_contains(urldecode($request->body()), 'out center tags'));
        $this->assertTrue(ImportLog::where('importer', 'osm-hotels')->where('message', 'like', 'Kandy: 4 stays found, 3 created%')->exists());
    }

    public function test_hotel_import_upserts_by_osm_id_and_respects_admin_changes(): void
    {
        Http::fake(['overpass-api.de/*' => Http::response($this->fixture('overpass-hotels.json'))]);
        app(OverpassHotelImporter::class)->run(['district' => 'kandy']);

        Hotel::where('osm_id', 'node/10')->sole()->update(['tier' => Tier::Premium]);
        Hotel::where('osm_id', 'node/30')->sole()->update(['status' => PublishStatus::Published, 'name' => 'Admin Name']);

        $report = app(OverpassHotelImporter::class)->run(['district' => 'kandy', 'fresh' => true]);

        $this->assertSame(0, $report->created);
        $this->assertSame(3, Hotel::count());
        $this->assertSame(Tier::Premium, Hotel::where('osm_id', 'node/10')->value('tier'), 'Admin tier kept');
        $this->assertSame('Admin Name', Hotel::where('osm_id', 'node/30')->value('name'), 'Published hotel untouched');
    }

    public function test_tier_guess(): void
    {
        $this->assertSame(Tier::Luxury, OverpassHotelImporter::guessTier(5));
        $this->assertSame(Tier::Premium, OverpassHotelImporter::guessTier(4));
        $this->assertSame(Tier::Premium, OverpassHotelImporter::guessTier(3));
        $this->assertSame(Tier::Budget, OverpassHotelImporter::guessTier(2));
        $this->assertSame(Tier::Budget, OverpassHotelImporter::guessTier(null));
    }

    public function test_busy_overpass_is_logged_as_failed_for_that_district(): void
    {
        Http::fake(['overpass-api.de/*' => Http::response('busy', 504)]);

        $report = app(OverpassHotelImporter::class)->run(['district' => 'kandy']);

        $this->assertSame(1, $report->failed);
        $this->assertTrue(ImportLog::where('status', 'failed')->where('message', 'like', '%OVERPASS_URL%')->exists());
    }

    public function test_districts_without_a_boundary_fail_with_a_clear_message(): void
    {
        Http::fake();

        $report = app(OverpassHotelImporter::class)->run(['district' => 'galle']);

        $this->assertSame(1, $report->failed);
        Http::assertNothingSent();
        $this->assertTrue(ImportLog::where('message', 'like', '%lg:import-districts%')->exists());
    }

    public function test_place_suggestions_are_drafts_and_skip_duplicates(): void
    {
        // The seeded temple already has coordinates next to OSM's "Temple of the Tooth".
        Place::where('slug', 'temple-of-the-sacred-tooth-relic')->update(['lat' => 7.2936, 'lng' => 80.6414]);
        $before = Place::count();
        Http::fake(['overpass-api.de/*' => Http::response($this->fixture('overpass-places.json'))]);

        $report = app(OverpassPlaceSuggester::class)->run(['district' => 'kandy']);

        $this->assertSame(3, $report->created);
        $this->assertSame(2, $report->skipped, 'Temple of the Tooth (nearby, similar name) and the second Hunnasgiriya Falls');
        $this->assertSame($before + 3, Place::count());

        $falls = Place::where('osm_id', 'node/100')->sole();
        $this->assertSame(PublishStatus::Draft, $falls->status);
        $this->assertSame('osm', $falls->source);
        $this->assertTrue($falls->is_hidden_gem, 'No Wikipedia/Wikidata link → hidden-gem candidate');
        $this->assertSame(['hill-country'], $falls->categories->pluck('slug')->all(), 'Only categories linked to Kandy');

        $ruins = Place::where('osm_id', 'way/102')->sole();
        $this->assertFalse($ruins->is_hidden_gem);
        $this->assertSame(['historical-cultural'], $ruins->categories->pluck('slug')->all());
        $this->assertEqualsWithDelta(7.22, (float) $ruins->lat, 0.00001);

        $this->assertSame('sunset', Place::where('osm_id', 'node/105')->sole()->best_time_slot->value);
        $this->assertFalse(Place::where('osm_id', 'node/101')->exists());
    }

    public function test_suggestions_are_not_created_twice(): void
    {
        Http::fake(['overpass-api.de/*' => Http::response($this->fixture('overpass-places.json'))]);

        app(OverpassPlaceSuggester::class)->run(['district' => 'kandy']);
        $count = Place::count();
        app(OverpassPlaceSuggester::class)->run(['district' => 'kandy', 'fresh' => true]);

        $this->assertSame($count, Place::count());
    }
}
