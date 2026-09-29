<?php

namespace Tests\Feature;

use App\Enums\PriceCategory;
use App\Enums\PublishStatus;
use App\Enums\SenderRole;
use App\Enums\Tier;
use App\Enums\TripStatus;
use App\Models\Category;
use App\Models\Cuisine;
use App\Models\District;
use App\Models\Guide;
use App\Models\Hotel;
use App\Models\ImportLog;
use App\Models\Media;
use App\Models\Place;
use App\Models\RoomRate;
use App\Models\Setting;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_trip_loads_days_stops_places_and_people(): void
    {
        $agent = User::factory()->agent()->create();
        $trip = Trip::factory()->withDays(stopsPerDay: 2)->create(['days' => 3, 'agent_id' => $agent->id]);

        $trip->travellers()->create(['full_name' => 'Lead Guest', 'email' => 'lead@example.com', 'is_lead' => true]);
        $trip->priceItems()->create(['category' => PriceCategory::Transport, 'description' => 'Van, 3 days', 'qty' => 3, 'unit_price' => 45, 'amount' => 135]);
        $trip->statusHistory()->create(['from_status' => null, 'to_status' => 'draft']);
        $trip->messages()->create(['sender_id' => $agent->id, 'sender_role' => SenderRole::Agent, 'body' => 'Hello!']);

        $trip = Trip::with(['user', 'agent', 'tripDays.stops.place.district', 'leadTraveller', 'priceItems', 'statusHistory', 'messages.sender'])
            ->findOrFail($trip->id);

        $this->assertSame([1, 2, 3], $trip->tripDays->pluck('day_number')->all());
        $this->assertSame([1, 2], $trip->tripDays->first()->stops->pluck('sequence')->all());
        $this->assertInstanceOf(Place::class, $trip->tripDays->first()->stops->first()->place);
        $this->assertInstanceOf(District::class, $trip->tripDays->first()->stops->first()->place->district);
        $this->assertTrue($trip->agent->is($agent));
        $this->assertSame('Lead Guest', $trip->leadTraveller->full_name);
        $this->assertSame(PriceCategory::Transport, $trip->priceItems->first()->category);
        $this->assertSame('135.00', $trip->priceItems->first()->amount);
        $this->assertCount(1, $trip->statusHistory);
        $this->assertSame('Hello!', $trip->messages->first()->body);
        $this->assertSame(TripStatus::Draft, $trip->status);
        $this->assertSame(2, $trip->travellerCount());
        $this->assertTrue($trip->user->trips->contains($trip));
        $this->assertTrue($agent->assignedTrips->contains($trip));
    }

    public function test_trip_categories_cuisines_vehicle_and_guide(): void
    {
        $this->seed();

        $trip = Trip::factory()->create([
            'vehicle_id' => Vehicle::tier(Tier::Premium)->forPax(2)->value('id'),
            'guide_id' => Guide::where('type', 'chauffeur')->value('id'),
        ]);
        $trip->categories()->attach(Category::where('slug', 'beach-coastal')->value('id'));
        $trip->cuisines()->attach(Cuisine::limit(2)->pluck('id'));

        $trip->load(['categories', 'cuisines', 'vehicle', 'guide']);

        $this->assertSame(['beach-coastal'], $trip->categories->pluck('slug')->all());
        $this->assertCount(2, $trip->cuisines);
        $this->assertNotNull($trip->vehicle);
        $this->assertIsArray($trip->guide->languages);
    }

    public function test_place_scopes(): void
    {
        [$beach, $history] = Category::factory()->count(2)->create();
        $galle = District::factory()->create();
        $kandy = District::factory()->create();

        $fort = Place::factory()->published()->for($galle)->create();
        $fort->categories()->attach($history);
        $gem = Place::factory()->published()->hiddenGem()->for($kandy)->create();
        $gem->categories()->attach($beach);
        $draft = Place::factory()->for($galle)->create();
        $draft->categories()->attach($beach);
        Place::factory()->published()->for($galle)->create(['is_active' => false]);

        $this->assertEqualsCanonicalizing([$fort->id, $gem->id], Place::published()->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$fort->id, $draft->id], Place::inDistricts([$galle->id])->whereHas('categories')->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$gem->id, $draft->id], Place::inCategories([$beach->id])->pluck('id')->all());
        $this->assertSame([$gem->id], Place::published()->inCategories([$beach->id])->pluck('id')->all());
        $this->assertSame([$gem->id], Place::hiddenGems()->pluck('id')->all());
        $this->assertSame([$draft->id], Place::drafts()->pluck('id')->all());
    }

    public function test_district_in_categories_scope(): void
    {
        $this->seed();

        $hillCountry = Category::where('slug', 'hill-country')->value('id');

        $this->assertEqualsCanonicalizing(
            ['kandy', 'nuwara-eliya', 'badulla', 'kegalle'],
            District::inCategories([$hillCountry])->pluck('slug')->all(),
        );
    }

    public function test_hotel_tier_scope_room_rates_and_casts(): void
    {
        $luxury = Hotel::factory()->published()->tier(Tier::Luxury)->create(['amenities' => ['pool', 'spa']]);
        Hotel::factory()->published()->tier(Tier::Budget)->create();

        RoomRate::factory()->for($luxury)->create(['season_from' => '2026-11-01', 'season_to' => '2027-03-31', 'price_per_night' => 420]);
        RoomRate::factory()->for($luxury)->create(['season_from' => null, 'season_to' => null, 'price_per_night' => 300]);

        $this->assertSame([$luxury->id], Hotel::tier(Tier::Luxury)->pluck('id')->all());
        $this->assertSame([$luxury->id], Hotel::published()->tier('luxury')->pluck('id')->all());
        $this->assertSame(['pool', 'spa'], $luxury->fresh()->amenities);
        $this->assertSame(Tier::Luxury, $luxury->fresh()->tier);
        $this->assertSame(PublishStatus::Published, $luxury->fresh()->status);

        $this->assertSame(['420.00', '300.00'], $luxury->roomRates()->validOn('2026-12-15')->pluck('price_per_night')->all());
        $this->assertSame(['300.00'], $luxury->roomRates()->validOn('2026-06-15')->pluck('price_per_night')->all());
    }

    public function test_media_morph_relation_cover_helper_and_credit(): void
    {
        $place = Place::factory()->create();
        Media::factory()->for($place, 'mediable')->create(['sort_order' => 2]);
        $cover = Media::factory()->cover()->for($place, 'mediable')->create(['sort_order' => 1, 'author' => 'Jane Doe']);

        $place->load(['media', 'cover']);

        $this->assertCount(2, $place->media);
        $this->assertTrue($place->media->first()->is($cover), 'Media is ordered by sort_order');
        $this->assertTrue($place->cover->is($cover));
        $this->assertSame('place', $cover->fresh()->mediable_type, 'Morph map stores the short type name');
        $this->assertTrue($cover->mediable->is($place));
        $this->assertSame('Photo: Jane Doe, CC BY-SA 4.0, via Wikimedia Commons', $cover->credit());
        $this->assertStringEndsWith('-800.webp', $cover->url(800));
        $this->assertStringContainsString(' 400w', $cover->srcset());
    }

    public function test_import_log_targets_a_model(): void
    {
        $place = Place::factory()->create();

        $log = ImportLog::create([
            'importer' => 'wikipedia',
            'target_type' => 'place',
            'target_id' => $place->id,
            'status' => 'not_found',
            'message' => 'Title returned 404',
        ]);

        $this->assertTrue($log->fresh()->target->is($place));
        $this->assertNotNull($log->created_at);
    }

    public function test_setting_cache_refreshes_on_save(): void
    {
        Setting::put('tax_percent', '5');
        $this->assertSame('5', Setting::get('tax_percent'));

        Setting::put('tax_percent', '7');
        $this->assertSame('7', Setting::get('tax_percent'));
    }
}
