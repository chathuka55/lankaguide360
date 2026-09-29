<?php

namespace Tests\Feature;

use App\Enums\Tier;
use App\Enums\TripStatus;
use App\Livewire\TripBuilder;
use App\Models\Category;
use App\Models\District;
use App\Models\Hotel;
use App\Models\Place;
use App\Models\RoomRate;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Support\TripDraft;
use Database\Seeders\TravelOptionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class TripBuilderTest extends TestCase
{
    use RefreshDatabase;

    private Category $beach;

    private Category $hills;

    private District $galle;

    private District $kandy;

    private District $jaffna;

    /** @var array<string, Place> */
    private array $places = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['lankaguide.routing.ors_key' => null]);
        $this->seed(TravelOptionSeeder::class);

        $this->beach = Category::factory()->create(['name' => 'Beach & Coastal', 'slug' => 'beach-coastal']);
        $this->hills = Category::factory()->create(['name' => 'Hill Country', 'slug' => 'hill-country']);

        $this->galle = District::factory()->create(['name' => 'Galle', 'slug' => 'galle', 'lat' => 6.05, 'lng' => 80.22]);
        $this->kandy = District::factory()->create(['name' => 'Kandy', 'slug' => 'kandy', 'lat' => 7.29, 'lng' => 80.63]);
        $this->jaffna = District::factory()->create(['name' => 'Jaffna', 'slug' => 'jaffna', 'lat' => 9.66, 'lng' => 80.02]);
        $this->galle->categories()->attach($this->beach);
        $this->kandy->categories()->attach($this->hills);

        foreach ([
            ['Galle Fort', 'galle-fort', $this->galle, $this->beach, 6.0269, 80.2170],
            ['Unawatuna Beach', 'unawatuna-beach', $this->galle, $this->beach, 6.0094, 80.2497],
            ['Temple of the Tooth', 'temple-of-the-tooth', $this->kandy, $this->hills, 7.2936, 80.6413],
            ['Peradeniya Gardens', 'peradeniya-gardens', $this->kandy, $this->hills, 7.2714, 80.5957],
            ['Jaffna Fort', 'jaffna-fort', $this->jaffna, $this->beach, 9.6620, 80.0080],
        ] as [$name, $slug, $district, $category, $lat, $lng]) {
            $place = Place::factory()->published()->for($district)->create([
                'name' => $name, 'slug' => $slug, 'lat' => $lat, 'lng' => $lng, 'visit_minutes' => 90,
                'best_time_slot' => 'any', 'open_time' => null, 'close_time' => null,
            ]);
            $place->categories()->attach($category);
            $this->places[$name] = $place;
        }

        foreach ([['Galle', $this->galle, 6.03, 80.21], ['Kandy', $this->kandy, 7.29, 80.63]] as [$town, $district, $lat, $lng]) {
            foreach (['Budget', 'Premium'] as $tier) {
                $hotel = Hotel::factory()->published()->for($district)->create(['name' => "{$town} {$tier} Hotel", 'town' => $town, 'tier' => strtolower($tier), 'lat' => $lat, 'lng' => $lng]);
                RoomRate::factory()->for($hotel)->create(['room_type' => 'Deluxe', 'meal_plan' => 'HB', 'price_per_night' => 100]);
                RoomRate::factory()->for($hotel)->create(['room_type' => 'Suite', 'meal_plan' => 'HB', 'price_per_night' => 160]);
            }
        }
    }

    private function draft(): TripDraft
    {
        return TripDraft::fromSession(session()->driver());
    }

    /**
     * Walk steps 1–5 for a premium Galle + Kandy trip.
     */
    private function throughStepFive(): Testable
    {
        return Livewire::test(TripBuilder::class)
            ->call('selectTier', 'premium')->call('next')
            ->call('toggleCategory', $this->beach->id)->call('toggleCategory', $this->hills->id)->call('next')
            ->call('toggleDistrict', $this->galle->id)->call('toggleDistrict', $this->kandy->id)->call('next')
            ->call('togglePlace', $this->places['Galle Fort']->id)
            ->call('togglePlace', $this->places['Temple of the Tooth']->id)
            ->call('togglePlace', $this->places['Peradeniya Gardens']->id)
            ->call('next')
            ->set('startDate', now()->addMonth()->toDateString())
            ->set('days', 4)
            ->set('adults', 2);
    }

    public function test_the_plan_page_renders_the_wizard(): void
    {
        $this->get(route('plan'))
            ->assertOk()
            ->assertSeeLivewire(TripBuilder::class)
            ->assertSee('Travel style')
            ->assertSee('per person per day');
    }

    public function test_each_step_is_validated_before_moving_on(): void
    {
        Livewire::test(TripBuilder::class)
            ->call('next')->assertHasErrors('tier')->assertSet('step', 1)
            ->call('selectTier', 'budget')->call('next')->assertSet('step', 2)
            ->call('next')->assertHasErrors('categoryIds')->assertSet('step', 2);

        $this->assertSame('budget', $this->draft()->tier);
    }

    public function test_later_steps_cannot_be_skipped_to(): void
    {
        Livewire::test(TripBuilder::class)->call('goTo', 5)->assertSet('step', 1);
        Livewire::withQueryParams(['step' => 6])->test(TripBuilder::class)->assertSet('step', 1);
    }

    public function test_categories_filter_the_districts(): void
    {
        $component = Livewire::test(TripBuilder::class)
            ->call('selectTier', 'premium')->call('next')
            ->call('toggleCategory', $this->hills->id)->call('next')
            ->assertSet('step', 3)
            ->assertViewHas('allowed', fn ($allowed) => $allowed->all() === [$this->kandy->id]);

        // Galle is not linked to Hill Country: clicking it does nothing.
        $component->call('toggleDistrict', $this->galle->id)->assertSet('districtIds', [])
            ->call('toggleDistrict', $this->kandy->id)->assertSet('districtIds', [$this->kandy->id])
            ->assertDispatched('districts-changed');
    }

    public function test_districts_filter_the_places(): void
    {
        Livewire::test(TripBuilder::class)
            ->call('selectTier', 'premium')->call('next')
            ->call('toggleCategory', $this->beach->id)->call('next')
            ->call('toggleDistrict', $this->galle->id)->call('next')
            ->assertSet('step', 4)
            ->assertSee('Galle Fort')
            ->assertSee('Unawatuna Beach')
            ->assertDontSee('Temple of the Tooth')
            ->assertDontSee('Jaffna Fort')
            ->call('next')->assertHasErrors('placeIds')
            ->call('togglePlace', $this->places['Galle Fort']->id)
            ->assertSee('1</span> place chosen', false);
    }

    public function test_deselecting_a_district_removes_its_places(): void
    {
        Livewire::test(TripBuilder::class)
            ->call('selectTier', 'premium')->call('next')
            ->call('toggleCategory', $this->beach->id)->call('toggleCategory', $this->hills->id)->call('next')
            ->call('toggleDistrict', $this->galle->id)->call('toggleDistrict', $this->kandy->id)->call('next')
            ->call('togglePlace', $this->places['Galle Fort']->id)
            ->call('togglePlace', $this->places['Temple of the Tooth']->id)
            ->call('goTo', 3)
            ->call('toggleDistrict', $this->galle->id)
            ->assertSet('placeIds', [$this->places['Temple of the Tooth']->id]);
    }

    public function test_choices_survive_a_page_refresh(): void
    {
        $this->throughStepFive();

        // A new component instance (a refresh) reads everything back from the session.
        Livewire::withQueryParams(['step' => 5])->test(TripBuilder::class)
            ->assertSet('step', 5)
            ->assertSet('tier', 'premium')
            ->assertSet('districtIds', [$this->galle->id, $this->kandy->id])
            ->assertSet('placeIds', [$this->places['Galle Fort']->id, $this->places['Temple of the Tooth']->id, $this->places['Peradeniya Gardens']->id])
            ->assertSet('days', 4);
    }

    public function test_place_deep_link_and_add_to_my_trip_are_picked_up(): void
    {
        $this->post(route('plan.places.toggle', $this->places['Unawatuna Beach']));

        Livewire::withQueryParams(['place' => 'temple-of-the-tooth'])->test(TripBuilder::class)
            ->assertSet('placeIds', [$this->places['Unawatuna Beach']->id, $this->places['Temple of the Tooth']->id])
            ->assertSet('districtIds', [$this->galle->id, $this->kandy->id])
            ->assertSet('categoryIds', [$this->beach->id, $this->hills->id]);
    }

    public function test_category_deep_link(): void
    {
        Livewire::withQueryParams(['category' => 'hill-country'])->test(TripBuilder::class)
            ->assertSet('categoryIds', [$this->hills->id]);
    }

    public function test_dates_and_travellers_are_validated(): void
    {
        $this->throughStepFive()
            ->set('startDate', now()->subDay()->toDateString())
            ->set('days', 30)
            ->call('addChild')->set('childrenAges.0', 1)
            ->call('next')
            ->assertHasErrors(['startDate', 'days'])
            ->assertSet('childrenAges', [2])
            ->assertSet('step', 5);
    }

    public function test_step_five_builds_the_itinerary_and_suggests_hotels_vehicle_and_price(): void
    {
        $component = $this->throughStepFive()->call('next')->assertHasNoErrors()->assertSet('step', 6);

        $draft = $this->draft();
        $this->assertTrue($draft->hasItinerary());
        $this->assertCount(4, $draft->itinerary['days']);
        $this->assertSame('HB', $draft->mealPlan);
        $this->assertNotEmpty($draft->hotels);
        foreach ($draft->hotels as $choice) {
            $this->assertStringContainsString('Premium Hotel', Hotel::find($choice['hotel_id'])->name);
            $this->assertNotNull($choice['room_rate_id']);
        }
        $this->assertNotNull($draft->vehicleId);

        $component->assertSee('Night 1')->assertSee('Premium Hotel')->assertViewHas('price', fn ($price) => $price->total > 0);
    }

    public function test_room_type_and_hotel_can_be_changed(): void
    {
        $component = $this->throughStepFive()->call('next');
        $day = array_key_first($this->draft()->hotels);
        $hotel = Hotel::find($this->draft()->hotels[$day]['hotel_id']);
        $suite = $hotel->roomRates()->where('room_type', 'Suite')->sole();

        $component->call('chooseRate', $day, $suite->id);
        $this->assertSame($suite->id, $this->draft()->hotels[$day]['room_rate_id']);

        // A rate from another hotel is refused.
        $other = RoomRate::whereNot('hotel_id', $hotel->id)->first();
        $component->call('chooseRate', $day, $other->id);
        $this->assertSame($suite->id, $this->draft()->hotels[$day]['room_rate_id']);
    }

    public function test_vehicle_must_seat_the_group(): void
    {
        $component = $this->throughStepFive()->set('adults', 5)->call('next')->call('next')->assertSet('step', 7);
        $small = Vehicle::where('max_pax', '<', 5)->first();

        $component->call('selectVehicle', $small->id)->assertNotSet('vehicleId', $small->id);
        $this->assertGreaterThanOrEqual(5, Vehicle::find($component->get('vehicleId'))->max_pax);
    }

    public function test_changing_the_tier_after_the_plan_resets_hotels_and_price(): void
    {
        $component = $this->throughStepFive()->call('next')->call('next')->call('next')->assertSet('step', 8);

        $component->call('goTo', 1)->call('selectTier', 'budget');

        $draft = $this->draft();
        $this->assertNull($draft->itinerary);
        $this->assertSame([], $draft->hotels);
        $this->assertSame('BB', $draft->mealPlan);

        $component->call('goTo', 6)->assertSet('step', 1, 'Steps after the change must be walked again.')
            ->call('next')->call('next')->call('next')->call('next')->call('next')->assertSet('step', 6);
        foreach ($this->draft()->hotels as $choice) {
            $this->assertStringContainsString('Budget Hotel', Hotel::find($choice['hotel_id'])->name);
        }
    }

    public function test_review_shows_timeline_price_and_submit_form(): void
    {
        $this->throughStepFive()->call('next')->call('next')->call('next')
            ->assertSet('step', 8)
            ->assertSee('Day by day')
            ->assertSee('Temple of the Tooth')
            ->assertSee('Estimate — final price confirmed by your agent.')
            ->assertSee('Submit my trip request')
            ->assertSee('name="full_name"', false);
    }

    public function test_drag_and_drop_moves_a_stop_and_retimes(): void
    {
        $component = $this->throughStepFive()->call('next')->call('next')->call('next');
        $days = array_map(fn ($day) => array_column($day['stops'], 'place_id'), $this->draft()->itinerary['days']);
        $all = array_merge(...$days);

        // Move every stop to day 1 in reverse order.
        $new = array_fill(0, count($days), []);
        $new[0] = array_reverse($all);
        $component->call('reorder', $new)->assertDispatched('itinerary-updated');

        $itinerary = $this->draft()->itinerary;
        $this->assertSame(array_reverse($all), array_column($itinerary['days'][0]['stops'], 'place_id'));
        $this->assertCount(count($days), $itinerary['days']);

        // Unknown place ids are ignored.
        $component->call('reorder', [[999999, ...array_reverse($all)]]);
        $this->assertNotContains(999999, array_column($this->draft()->itinerary['days'][0]['stops'], 'place_id'));
    }

    public function test_the_built_plan_can_be_submitted_as_a_guest(): void
    {
        Mail::fake();
        $this->throughStepFive()->call('next')->call('next')->call('next');

        $this->post(route('trips.store'), [
            'full_name' => 'Guest Traveller', 'email' => 'guest@example.test', 'phone' => '+44 7700 900123',
            'country' => 'United Kingdom', 'age' => 40, 'consent' => '1',
        ])->assertRedirect();

        $trip = Trip::sole();
        $this->assertSame(TripStatus::Submitted, $trip->status);
        $this->assertSame(Tier::Premium, $trip->tier);
        $this->assertSame(4, $trip->days);
        $this->assertSame(3, $trip->tripDays()->withCount('stops')->get()->sum('stops_count'));
        $this->assertTrue($trip->tripDays()->whereNotNull('hotel_id')->exists());
        $this->assertNull(session(TripDraft::SESSION_KEY));
    }

    public function test_package_customize_opens_the_places_step(): void
    {
        $draft = new TripDraft;
        $draft->tier = 'premium';
        $draft->placeIds = [$this->places['Galle Fort']->id];
        $draft->districtIds = [$this->galle->id];
        $draft->categoryIds = [$this->beach->id];
        $draft->completedStep = 4;
        $this->withSession([TripDraft::SESSION_KEY => $draft->toArray()]);

        Livewire::withQueryParams(['step' => 5])->test(TripBuilder::class)->assertSet('step', 5)->assertSee('Number of days');
    }
}
