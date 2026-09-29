<?php

namespace Tests\Feature;

use App\Enums\GuideType;
use App\Enums\MealPlan;
use App\Enums\PriceCategory;
use App\Enums\Tier;
use App\Models\District;
use App\Models\Guide;
use App\Models\Hotel;
use App\Models\Place;
use App\Models\RoomRate;
use App\Models\Setting;
use App\Models\Vehicle;
use App\Services\Itinerary\Itinerary;
use App\Services\PricingService;
use App\Services\SuggestionService;
use App\Support\Money;
use App\Support\TripDraft;
use Database\Seeders\LocationSeeder;
use Database\Seeders\TravelOptionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PricingAndSuggestionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([LocationSeeder::class, TravelOptionSeeder::class]);
    }

    /**
     * 2 adults + children aged 8 and 3, Premium, half board, one night in a BB hotel.
     */
    public function test_breakdown_with_known_inputs(): void
    {
        Setting::put('service_fee_premium', '10');
        Setting::put('tax_percent', '5');
        Setting::put('usd_lkr', '300');
        Setting::put('meal_dinner', '15');
        Setting::put('ticket_child_free_age', '5');

        $fortress = Place::factory()->published()->create(['fee_foreign_adult' => 30, 'fee_foreign_child' => 15]);
        $garden = Place::factory()->published()->create(['fee_foreign_adult' => 0, 'fee_foreign_child' => 0]);
        $hotel = Hotel::factory()->published()->create(['name' => 'Hill Hotel', 'tier' => Tier::Premium]);
        $rate = RoomRate::factory()->for($hotel)->create(['room_type' => 'Deluxe', 'meal_plan' => 'BB', 'price_per_night' => 100, 'max_occupancy' => 2, 'extra_bed_price' => 20]);
        $vehicle = Vehicle::factory()->create(['type' => 'Test van', 'tier' => 'premium', 'day_rate' => 50, 'km_rate' => 0.40]);
        $guide = Guide::factory()->create(['name' => 'Chauffeur-guide Test', 'type' => 'chauffeur', 'languages' => ['English'], 'day_rate' => 15]);

        $itinerary = new Itinerary([
            ['number' => 1, 'stops' => [['place_id' => $fortress->id]], 'overnight' => ['town' => 'Kandy', 'lat' => 7.29, 'lng' => 80.63, 'district_id' => null, 'hotel_tier' => 'premium'], 'drive_km' => 100.0, 'drive_minutes' => 150],
            ['number' => 2, 'stops' => [['place_id' => $garden->id]], 'overnight' => null, 'drive_km' => 50.0, 'drive_minutes' => 80],
        ]);

        $draft = new TripDraft;
        $draft->tier = 'premium';
        $draft->adults = 2;
        $draft->childrenAges = [8, 3];
        $draft->mealPlan = 'HB';
        $draft->hotels = [1 => ['hotel_id' => $hotel->id, 'room_rate_id' => $rate->id]];
        $draft->vehicleId = $vehicle->id;
        $draft->guideType = 'chauffeur';
        $draft->guideId = $guide->id;

        $price = app(PricingService::class)->breakdown($draft, $itinerary);

        $this->assertSame(10000 + 4000, $price->categoryTotal(PriceCategory::Accommodation), '1 room + 2 extra beds');
        $this->assertSame(10000 + 6000, $price->categoryTotal(PriceCategory::Transport), '2 days + 150 km');
        $this->assertSame(3000, $price->categoryTotal(PriceCategory::Guide));
        $this->assertSame(4500, $price->categoryTotal(PriceCategory::Meals), 'Dinner for 2 adults + 2 half-price children');
        $this->assertSame(6000 + 1500, $price->categoryTotal(PriceCategory::Tickets), 'Child aged 3 enters free');

        $this->assertSame(45000, $price->subtotal);
        $this->assertSame(4500, $price->serviceFee);
        $this->assertSame(2475, $price->tax);
        $this->assertSame(51975, $price->total);
        $this->assertSame(12994, $price->perPerson);
        $this->assertSame(15592500, $price->totalLkr);
        $this->assertSame($price->subtotal, array_sum(array_column($price->lines, 'amount_cents')), 'Lines add up to the subtotal');
        $this->assertSame('519.75', $price->toArray()['total']);
        $this->assertContains('1 child seat(s) included for children under 4.', $price->notes);
    }

    public function test_nights_without_a_priced_hotel_are_flagged(): void
    {
        $draft = new TripDraft;
        $draft->tier = 'budget';
        $itinerary = new Itinerary([
            ['number' => 1, 'stops' => [], 'overnight' => ['town' => 'Ella', 'lat' => 6.87, 'lng' => 81.05, 'district_id' => null, 'hotel_tier' => null], 'drive_km' => 0, 'drive_minutes' => 0],
            ['number' => 2, 'stops' => [], 'overnight' => null, 'drive_km' => 0, 'drive_minutes' => 0],
        ]);

        $price = app(PricingService::class)->breakdown($draft, $itinerary);

        $this->assertStringContainsString('hotel to be confirmed', $price->lines[0]['description']);
        $this->assertNotEmpty($price->notes);
    }

    public function test_money_helpers(): void
    {
        $this->assertSame(3650, Money::cents('36.50'));
        $this->assertSame(40, Money::cents('0.40'));
        $this->assertSame('1234.56', Money::decimal(123456));
        $this->assertSame(250, Money::percent(2500, '10'));
        $this->assertSame('$1,234.56', Money::format(123456));
    }

    /**
     * @return array<string, array{int, ?int, string, string}>
     */
    public static function vehicleCases(): array
    {
        return [
            '3 travellers' => [3, null, 'premium', 'Hybrid sedan / SUV'],
            '4 travellers' => [4, null, 'premium', 'Flat-roof KDH van'],
            '9 travellers' => [9, null, 'premium', 'High-roof KDH van'],
            '10 travellers' => [10, null, 'premium', 'Mini coach with AC'],
            '3 travellers, 7 bags' => [3, 7, 'premium', 'Flat-roof KDH van'],
            'budget pair' => [2, 2, 'budget', 'Car'],
            'luxury 5' => [5, null, 'luxury', 'Premium van with extra legroom'],
        ];
    }

    #[DataProvider('vehicleCases')]
    public function test_vehicle_suggestion(int $travellers, ?int $bags, string $tier, string $expected): void
    {
        $this->assertSame($expected, app(SuggestionService::class)->vehicleFor($travellers, $bags, Tier::from($tier))?->type);
    }

    public function test_child_seats_and_vehicle_options(): void
    {
        $draft = new TripDraft;
        $draft->adults = 2;
        $draft->childrenAges = [2, 7];
        $draft->infants = 1;

        $this->assertSame(5, $draft->travellers());
        $this->assertSame(2, $draft->childSeats(), 'Child under 4 + infant');

        $options = app(SuggestionService::class)->vehicleOptions(5);
        $this->assertTrue($options->every(fn (Vehicle $v) => $v->max_pax >= 5), 'Only vehicles with enough seats');
    }

    public function test_guide_rules(): void
    {
        $suggestions = app(SuggestionService::class);

        $this->assertSame(GuideType::National, $suggestions->guideRule(12, Tier::Budget)['type']);
        $this->assertTrue($suggestions->guideRule(12, Tier::Budget)['required']);
        $this->assertSame(GuideType::National, $suggestions->guideRule(2, Tier::Luxury)['type']);
        $this->assertSame(GuideType::Chauffeur, $suggestions->guideRule(2, Tier::Budget)['type']);
        $this->assertSame('National guide (German)', $suggestions->guideFor(GuideType::National, 'German')?->name);
        $this->assertSame(MealPlan::HalfBoard, $suggestions->defaultMealPlan(Tier::Premium));
    }

    public function test_hotel_suggestions_prefer_the_town_priced_and_kid_friendly_hotels(): void
    {
        $kandy = District::where('slug', 'kandy')->sole();
        $plain = Hotel::factory()->published()->for($kandy)->create(['town' => 'Kandy', 'tier' => 'premium', 'kid_friendly' => false, 'name' => 'A Plain', 'star_rating' => 4]);
        $kids = Hotel::factory()->published()->for($kandy)->create(['town' => 'Kandy', 'tier' => 'premium', 'kid_friendly' => true, 'name' => 'B Family', 'star_rating' => 4]);
        $unpriced = Hotel::factory()->published()->for($kandy)->create(['town' => 'Kandy', 'tier' => 'premium', 'name' => 'C No Rates', 'star_rating' => 5]);
        Hotel::factory()->published()->for($kandy)->create(['town' => 'Kandy', 'tier' => 'luxury', 'name' => 'Luxury Only']);
        Hotel::factory()->for($kandy)->create(['town' => 'Kandy', 'tier' => 'premium', 'name' => 'Draft Hotel']);
        RoomRate::factory()->for($plain)->create(['meal_plan' => 'HB', 'price_per_night' => 90]);
        RoomRate::factory()->for($kids)->create(['meal_plan' => 'BB', 'price_per_night' => 80]);

        $suggestions = app(SuggestionService::class)->hotelsFor('Kandy', 7.29, 80.63, Tier::Premium, '2026-12-01', MealPlan::HalfBoard, ['kid_friendly' => true]);

        $this->assertSame(['B Family', 'A Plain', 'C No Rates'], $suggestions->map(fn ($s) => $s['hotel']->name)->all());
        $this->assertSame('BB', $suggestions[0]['rate']->meal_plan->value, 'Falls back to another meal plan when HB is not offered');
        $this->assertSame('HB', $suggestions[1]['rate']->meal_plan->value);
        $this->assertNull($suggestions[2]['rate']);
    }
}
