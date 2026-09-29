<?php

namespace Tests\Feature\Admin;

use App\Enums\MealPlan;
use App\Enums\PublishStatus;
use App\Enums\Tier;
use App\Models\District;
use App\Models\Hotel;
use App\Models\RoomRate;
use App\Models\User;
use Database\Seeders\LocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HotelAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LocationSeeder::class);
        $this->admin = User::factory()->admin()->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function validHotel(array $overrides = []): array
    {
        return [
            'name' => 'Cinnamon Hills',
            'slug' => '',
            'district_id' => District::where('slug', 'kandy')->value('id'),
            'town' => 'Kandy',
            'type' => 'hotel',
            'tier' => 'premium',
            'star_rating' => '4',
            'kid_friendly' => '1',
            'amenities' => ['pool', 'wifi', 'beach_front'],
            'lat' => '7.29',
            'lng' => '80.63',
            'website' => 'https://hills.example',
            'phone' => '+94 81 000 0000',
            'is_active' => '1',
            'status' => 'draft',
            ...$overrides,
        ];
    }

    public function test_admin_creates_and_edits_a_hotel(): void
    {
        $this->actingAs($this->admin)->post(route('admin.hotels.store'), $this->validHotel())->assertSessionHasNoErrors();

        $hotel = Hotel::where('slug', 'cinnamon-hills-kandy')->sole();
        $this->assertSame(['pool', 'wifi', 'beach_front'], $hotel->amenities);
        $this->assertSame(Tier::Premium, $hotel->tier);
        $this->assertTrue($hotel->kid_friendly);

        $this->actingAs($this->admin)
            ->put(route('admin.hotels.update', $hotel), $this->validHotel(['slug' => $hotel->slug, 'tier' => 'luxury', 'amenities' => []]))
            ->assertRedirect(route('admin.hotels.edit', $hotel));

        $this->assertSame(Tier::Luxury, $hotel->fresh()->tier);
        $this->assertSame([], $hotel->fresh()->amenities);
    }

    public function test_amenities_must_be_simple_tags(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.hotels.store'), $this->validHotel(['amenities' => ['Pool <script>']]))
            ->assertSessionHasErrors('amenities.0');
    }

    public function test_room_rates_can_be_added_changed_and_deleted(): void
    {
        $hotel = Hotel::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.hotels.rates.store', $hotel), [
            'room_type' => 'Deluxe double',
            'meal_plan' => 'HB',
            'price_per_night' => '120.50',
            'max_occupancy' => 2,
            'extra_bed_price' => '',
            'season_from' => '2026-11-01',
            'season_to' => '2027-04-30',
            'is_estimate' => '1',
        ])->assertRedirect(route('admin.hotels.edit', $hotel).'#rates');

        $rate = $hotel->roomRates()->sole();
        $this->assertSame('120.50', $rate->price_per_night);
        $this->assertSame(MealPlan::HalfBoard, $rate->meal_plan);
        $this->assertSame('0.00', $rate->extra_bed_price);
        $this->assertTrue($rate->is_estimate);

        $this->actingAs($this->admin)->put(route('admin.rates.update', $rate), [
            'room_type' => 'Deluxe double',
            'meal_plan' => 'FB',
            'price_per_night' => '150',
            'max_occupancy' => 3,
            'extra_bed_price' => '25',
            'season_from' => '',
            'season_to' => '',
            'is_estimate' => '0',
        ])->assertSessionHasNoErrors();

        $rate->refresh();
        $this->assertSame('150.00', $rate->price_per_night);
        $this->assertNull($rate->season_from);
        $this->assertFalse($rate->is_estimate);

        $this->actingAs($this->admin)->delete(route('admin.rates.destroy', $rate));
        $this->assertModelMissing($rate);
    }

    public function test_season_end_must_not_be_before_start(): void
    {
        $hotel = Hotel::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.hotels.rates.store', $hotel), [
            'room_type' => 'Standard', 'meal_plan' => 'BB', 'price_per_night' => '40', 'max_occupancy' => 2,
            'season_from' => '2027-01-10', 'season_to' => '2027-01-01',
        ])->assertSessionHasErrors('season_to');

        $this->assertSame(0, RoomRate::count());
    }

    public function test_bulk_publish_hotels(): void
    {
        $withCoords = Hotel::factory()->create();
        $withoutCoords = Hotel::factory()->create(['lat' => null, 'lng' => null]);

        $this->actingAs($this->admin)
            ->post(route('admin.hotels.bulk'), ['action' => 'publish', 'ids' => [$withCoords->id, $withoutCoords->id]])
            ->assertSessionHas('status', '1 hotel(s) updated.');

        $this->assertSame(PublishStatus::Published, $withCoords->fresh()->status);
        $this->assertSame(PublishStatus::Draft, $withoutCoords->fresh()->status);
    }

    public function test_hotel_edit_page_shows_rates_and_photo_tools(): void
    {
        $hotel = Hotel::factory()->create();
        RoomRate::factory()->for($hotel)->create(['room_type' => 'Garden suite']);

        $this->actingAs($this->admin)->get(route('admin.hotels.edit', $hotel))
            ->assertOk()
            ->assertSee('Garden suite', false)
            ->assertSee('Add a room rate')
            ->assertSee('Search Wikimedia Commons')
            ->assertSee('I have the right to use this image');
    }
}
