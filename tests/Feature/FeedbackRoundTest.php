<?php

namespace Tests\Feature;

use App\Enums\TripStatus;
use App\Enums\UserRole;
use App\Livewire\TripBuilder;
use App\Mail\ContactMessageReceived;
use App\Mail\TripMessageReceived;
use App\Mail\TripStatusChanged;
use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\District;
use App\Models\Hotel;
use App\Models\Media;
use App\Models\Package;
use App\Models\Place;
use App\Models\RoomRate;
use App\Models\Setting;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ItineraryService;
use App\Services\PricingService;
use App\Services\SuggestionService;
use App\Support\TripDraft;
use App\Support\TripPoints;
use Database\Seeders\TravelOptionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * User-feedback round (Nov 2026): place undo, multi-district guidance, start/end anywhere, hotel
 * page, vehicle services and hire, Sinhala guides, registration with NIC, local/foreign pricing,
 * trip change requests, package photos, admin help.
 */
class FeedbackRoundTest extends TestCase
{
    use RefreshDatabase;

    private Category $hills;

    private District $kandy;

    private District $badulla;

    protected function setUp(): void
    {
        parent::setUp();

        config(['lankaguide.routing.ors_key' => null]);
        $this->seed(TravelOptionSeeder::class);
        Setting::put('usd_lkr', '300');
        Setting::put('display_currency', 'LKR');

        $this->hills = Category::factory()->create(['name' => 'Hill Country', 'slug' => 'hill-country']);
        $this->kandy = District::factory()->create(['name' => 'Kandy', 'slug' => 'kandy', 'lat' => 7.29, 'lng' => 80.63]);
        $this->badulla = District::factory()->create(['name' => 'Badulla', 'slug' => 'badulla', 'lat' => 6.99, 'lng' => 81.05]);
        $this->kandy->categories()->attach($this->hills);
        $this->badulla->categories()->attach($this->hills);
    }

    private function place(string $name, District $district, float $lat, float $lng, array $extra = []): Place
    {
        $place = Place::factory()->published()->for($district)->create([
            'name' => $name, 'lat' => $lat, 'lng' => $lng, 'visit_minutes' => 60, 'best_time_slot' => 'any', 'open_time' => null, 'close_time' => null, ...$extra,
        ]);
        $place->categories()->attach($this->hills);

        return $place;
    }

    // ---------------------------------------------------------------- 2. place undo

    public function test_a_place_can_be_removed_again_from_the_card_or_the_list(): void
    {
        $temple = $this->place('Temple of the Tooth', $this->kandy, 7.2936, 80.6413);

        $builder = Livewire::test(TripBuilder::class)
            ->call('selectTier', 'premium')->call('next')
            ->call('toggleCategory', $this->hills->id)->call('next')
            ->call('toggleDistrict', $this->kandy->id)->call('next')
            ->call('togglePlace', $temple->id)
            ->assertSee('In your trip')
            ->assertSee('Your places')
            ->assertSee('Remove Temple of the Tooth from your trip');

        $builder->call('togglePlace', $temple->id)->assertSet('placeIds', [])->assertDontSee('In your trip');
    }

    // ---------------------------------------------------------------- 3. multi-district

    public function test_every_chosen_district_needs_a_place_or_must_be_removed(): void
    {
        $temple = $this->place('Temple of the Tooth', $this->kandy, 7.2936, 80.6413);
        $bridge = $this->place('Nine Arch Bridge', $this->badulla, 6.8768, 81.0608);

        $builder = Livewire::test(TripBuilder::class)
            ->call('selectTier', 'premium')->call('next')
            ->call('toggleCategory', $this->hills->id)->call('next')
            ->call('toggleDistrict', $this->kandy->id)->call('toggleDistrict', $this->badulla->id)->call('next')
            ->assertSet('step', 4)
            ->assertSee('Next district: Kandy')   // tabs are alphabetical: Badulla first
            ->call('showDistrict', $this->kandy->id)
            ->call('togglePlace', $temple->id)
            ->call('next')
            ->assertHasErrors('placeIds')
            ->assertSee("You chose Badulla but haven't added a place there yet")
            ->assertSet('activeDistrictId', $this->badulla->id)
            ->assertSet('step', 4);

        // Either add a place there…
        $builder->call('togglePlace', $bridge->id)->call('next')->assertHasNoErrors()->assertSet('step', 5);

        // …or remove the district.
        $other = Livewire::test(TripBuilder::class)->call('goTo', 4)->call('togglePlace', $bridge->id)
            ->call('next')->assertHasErrors('placeIds')
            ->call('removeDistrict', $this->badulla->id)
            ->assertSet('districtIds', [$this->kandy->id]);
        $other->call('next')->assertHasNoErrors()->assertSet('step', 5);
    }

    public function test_next_district_moves_through_the_chosen_districts(): void
    {
        Livewire::test(TripBuilder::class)
            ->call('selectTier', 'premium')->call('next')
            ->call('toggleCategory', $this->hills->id)->call('next')
            ->call('toggleDistrict', $this->kandy->id)->call('toggleDistrict', $this->badulla->id)->call('next')
            ->call('nextDistrict')
            ->assertSet('activeDistrictId', $this->kandy->id)
            ->assertDontSee('Next district:');
    }

    // ---------------------------------------------------------------- 4. start and end anywhere

    public function test_a_trip_can_start_and_end_in_any_town(): void
    {
        $this->assertContains('Kandy', TripPoints::names());
        $this->assertContains('Ella', TripPoints::names());
        $this->assertContains('BIA Katunayake', TripPoints::names());

        $draft = new TripDraft;
        $draft->tier = 'premium';
        $draft->startDate = now()->addMonth()->toDateString();
        $draft->days = 2;
        $draft->arrivalPoint = 'Kandy';
        $draft->departurePoint = 'Ella';
        $draft->placeIds = [$this->place('Temple of the Tooth', $this->kandy, 7.2936, 80.6413)->id, $this->place('Nine Arch Bridge', $this->badulla, 6.8768, 81.0608)->id];
        Hotel::factory()->published()->for($this->kandy)->create(['town' => 'Kandy', 'tier' => 'premium', 'lat' => 7.29, 'lng' => 80.63]);

        $itinerary = app(ItineraryService::class)->generate($draft);
        $this->assertStringStartsWith('Kandy', $itinerary->days[0]['title']);
        $this->assertStringContainsString('Ella', end($itinerary->days)['title']);

        Livewire::test(TripBuilder::class)->set('arrivalPoint', 'Nowhere Town')->call('goTo', 5);
        $this->assertTrue(true);
    }

    public function test_step_five_offers_towns_as_start_and_end_points(): void
    {
        $draft = new TripDraft;
        $draft->tier = 'premium';
        $draft->completedStep = 4;
        $draft->placeIds = [$this->place('Temple of the Tooth', $this->kandy, 7.2936, 80.6413)->id];
        $draft->districtIds = [$this->kandy->id];
        $draft->categoryIds = [$this->hills->id];
        $this->withSession([TripDraft::SESSION_KEY => $draft->toArray()]);

        Livewire::withQueryParams(['step' => 5])->test(TripBuilder::class)
            ->assertSee('Start the trip at')
            ->assertSee('<optgroup label="Towns">', false)
            ->assertSee('Already in Sri Lanka?')
            ->set('arrivalPoint', 'Atlantis')
            ->set('startDate', now()->addMonth()->toDateString())->set('days', 2)
            ->call('next')
            ->assertHasErrors('arrivalPoint');
    }

    // ---------------------------------------------------------------- 5. hotel page

    public function test_hotel_details_page(): void
    {
        $hotel = Hotel::factory()->published()->for($this->kandy)->create([
            'name' => 'Lake View Hotel', 'slug' => 'lake-view-hotel', 'town' => 'Kandy', 'tier' => 'premium', 'star_rating' => 4,
            'type' => 'hotel', 'amenities' => ['pool', 'wifi'], 'phone' => '+94 81 222 3333', 'website' => 'https://lakeview.example',
            'lat' => 7.29, 'lng' => 80.64,
        ]);
        RoomRate::factory()->for($hotel)->create(['room_type' => 'Deluxe', 'meal_plan' => 'HB', 'price_per_night' => 100]);
        $this->place('Temple of the Tooth', $this->kandy, 7.2936, 80.6413);

        $this->get(route('hotels.show', $hotel))
            ->assertOk()
            ->assertSee('Lake View Hotel')
            ->assertSee('Swimming pool')
            ->assertSee('Free Wi-Fi')
            ->assertSee('LKR 30,000')
            ->assertSee('Half Board')
            ->assertSee('Temple of the Tooth')
            ->assertSee('+94 81 222 3333')
            ->assertSee('https://www.google.com/maps/search/?api=1&amp;query=7.29%2C80.64', false)
            ->assertSee('"@type":"Hotel"', false)
            ->assertSee('A 4-star Premium hotel in Kandy');

        $draft = Hotel::factory()->create(['slug' => 'draft-hotel']);
        $this->get(route('hotels.show', $draft))->assertNotFound();

        $demo = Hotel::factory()->published()->create(['slug' => 'demo-one', 'is_demo' => true]);
        $this->get(route('hotels.show', $demo))->assertOk()->assertSee('noindex', false)->assertSee('sample listing');
    }

    // ---------------------------------------------------------------- 6 + 7. vehicles

    public function test_vehicle_hire_page_and_request(): void
    {
        Mail::fake();
        $van = Vehicle::where('max_pax', '>=', 4)->first();
        $van->update(['amenities' => ['wifi', 'water'], 'provider_name' => 'Kandy Cabs', 'provider_phone' => '+94 77 111 2222', 'is_rentable' => true]);
        $hidden = Vehicle::whereKeyNot($van->id)->first();
        $hidden->update(['is_rentable' => false, 'type' => 'Secret Limo']);

        $this->get(route('vehicles.index'))->assertOk()
            ->assertSee($van->type)->assertSee('Kandy Cabs')->assertSee('Wi-Fi')->assertSee('Drinking water')
            ->assertDontSee('Secret Limo');

        $this->post(route('vehicles.request', $van), [
            'name' => 'Nimal', 'email' => 'nimal@example.test', 'phone' => '+94 77 123 4567',
            'start_date' => now()->addWeek()->toDateString(), 'end_date' => now()->addWeeks(2)->toDateString(),
            'pickup' => 'Kandy', 'travellers' => 4, 'message' => 'Child seat please',
        ])->assertRedirect()->assertSessionHas('status');

        $message = ContactMessage::sole();
        $this->assertSame("Vehicle hire request: {$van->type}", $message->subject);
        $this->assertStringContainsString('Pick-up: Kandy', $message->body);
        Mail::assertQueued(ContactMessageReceived::class);

        $this->post(route('vehicles.request', $hidden), [])->assertSessionHasErrors();
        $this->post(route('vehicles.request', $van), ['name' => 'x'])->assertSessionHasErrors(['email', 'phone', 'start_date', 'pickup']);
    }

    public function test_refreshment_pack_and_vehicle_services_in_the_builder_price(): void
    {
        Setting::put('refreshments_per_person_day', '3');
        $draft = new TripDraft;
        $draft->tier = 'premium';
        $draft->adults = 2;
        $draft->days = 3;
        $draft->vehicleId = Vehicle::first()->id;
        $draft->refreshments = true;

        $lines = collect(app(PricingService::class)->breakdown($draft)->toArray()['lines']);
        $pack = $lines->first(fn ($l) => str_starts_with($l['description'], 'Refreshment pack'));
        $this->assertNotNull($pack);
        $this->assertSame('18.00', $pack['amount']); // 2 people × 3 days × USD 3
    }

    // ---------------------------------------------------------------- 8. Sinhala

    public function test_sinhala_tamil_and_english_guides_are_always_offered(): void
    {
        $languages = app(SuggestionService::class)->guideLanguages();

        $this->assertContains('Sinhala', $languages);
        $this->assertContains('Tamil', $languages);
        $this->assertContains('English', $languages);
    }

    // ---------------------------------------------------------------- 9 + 10. accounts

    public function test_registration_classifies_local_travellers_and_requires_an_nic(): void
    {
        $base = ['name' => 'Kamal Silva', 'email' => 'kamal@example.test', 'password' => 'password', 'password_confirmation' => 'password', 'phone' => '+94 77 123 4567', 'nationality' => 'Sri Lanka'];

        $this->post('/register', [...$base, 'country' => 'Sri Lanka'])->assertSessionHasErrors('nic');
        $this->post('/register', [...$base, 'country' => 'Sri Lanka', 'nic' => '123'])->assertSessionHasErrors('nic');
        $this->post('/register', [...$base, 'country' => 'Sri Lanka', 'nic' => '199099912345'])->assertSessionHasErrors('nic'); // day 999
        $this->assertGuest();

        $this->post('/register', [...$base, 'country' => 'Sri Lanka', 'nic' => '90123 4567v'])->assertRedirect();
        $user = User::where('email', 'kamal@example.test')->sole();
        $this->assertSame('local', $user->traveller_type);
        $this->assertSame('901234567V', $user->nic);
        $this->assertSame(UserRole::Traveller, $user->role);
    }

    public function test_foreign_registration_needs_no_nic(): void
    {
        $this->post('/register', ['name' => 'Anna', 'email' => 'anna@example.test', 'password' => 'password', 'password_confirmation' => 'password', 'phone' => '+49 151 234567', 'country' => 'Germany', 'nationality' => 'Germany', 'nic' => 'ignored'])->assertRedirect();
        $anna = User::where('email', 'anna@example.test')->sole();
        $this->assertSame('foreign', $anna->traveller_type);
        $this->assertNull($anna->nic);
    }

    public function test_new_format_nic_with_a_womans_birthday_is_accepted(): void
    {
        $this->post('/register', ['name' => 'Nimali', 'email' => 'nimali@example.test', 'password' => 'password', 'password_confirmation' => 'password', 'phone' => '0771234567', 'country' => 'Sri Lanka', 'nationality' => 'Sri Lanka', 'nic' => '199556712345'])->assertRedirect();
        $this->assertSame('199556712345', User::where('email', 'nimali@example.test')->value('nic')); // day 567 = woman, valid
    }

    public function test_register_from_the_trip_builder_returns_to_the_trip(): void
    {
        $this->get(route('register', ['next' => 'plan']))->assertOk()->assertSee('Create your account');

        $this->post('/register', ['name' => 'Anna', 'email' => 'anna@example.test', 'password' => 'password', 'password_confirmation' => 'password', 'phone' => '+49 151 234567', 'country' => 'Germany', 'nationality' => 'Germany'])
            ->assertRedirect(route('plan', ['step' => 8]));
    }

    public function test_profile_saves_traveller_details(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'phone' => '+94 77 765 4321', 'country' => 'Sri Lanka', 'nationality' => 'Sri Lanka', 'nic' => '851234567X'])
            ->assertSessionHasNoErrors();
        $this->assertSame('local', $user->fresh()->traveller_type);
        $this->assertSame('851234567X', $user->fresh()->nic);

        $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertSee('NIC number');
    }

    // ---------------------------------------------------------------- 11. local vs foreign prices

    public function test_local_travellers_pay_local_fees_get_the_discount_and_local_tax(): void
    {
        Setting::put('local_discount_percent', '10');
        Setting::put('tax_percent', '5');
        Setting::put('tax_percent_local', '0');
        $fort = $this->place('Old Fort', $this->kandy, 7.29, 80.63, ['fee_foreign_adult' => 30, 'fee_local_adult' => 1]);
        $vehicle = Vehicle::first();

        $draft = new TripDraft;
        $draft->tier = 'premium';
        $draft->adults = 2;
        $draft->days = 1;
        $draft->vehicleId = $vehicle->id;
        $draft->itinerary = ['days' => [['number' => 1, 'stops' => [['place_id' => $fort->id]], 'overnight' => null, 'drive_km' => 0, 'drive_minutes' => 0]]];

        $foreign = app(PricingService::class)->breakdown($draft)->toArray();
        $this->assertTrue(collect($foreign['lines'])->contains(fn ($l) => $l['description'] === 'Old Fort: foreign adult ticket' && $l['amount'] === '60.00'));
        $this->assertNotSame('0.00', $foreign['tax']);
        $this->assertContains('Foreign traveller prices include applicable Sri Lanka Tourism Development Authority taxes and service charges.', $foreign['notes']);

        $draft->travellerType = 'local';
        $local = app(PricingService::class)->breakdown($draft)->toArray();
        $lines = collect($local['lines']);
        $this->assertTrue($lines->contains(fn ($l) => $l['description'] === 'Old Fort: resident adult ticket' && $l['amount'] === '2.00'));
        $discount = $lines->firstWhere('category', 'discount');
        $this->assertNotNull($discount);
        $base = $lines->whereIn('category', ['accommodation', 'transport', 'guide'])->sum(fn ($l) => (float) $l['amount']);
        $this->assertSame('-'.number_format($base * 0.10, 2, '.', ''), $discount['amount']);
        $this->assertSame('0.00', $local['tax']);
        $this->assertLessThan((float) $foreign['total'], (float) $local['total']);
    }

    public function test_registered_local_travellers_start_with_resident_prices_in_the_builder(): void
    {
        $user = User::factory()->create(['traveller_type' => 'local', 'country' => 'Sri Lanka']);

        Livewire::actingAs($user)->test(TripBuilder::class)->assertSet('travellerType', 'local');
        Livewire::test(TripBuilder::class)->assertSet('travellerType', 'local'); // stored in the draft now
    }

    // ---------------------------------------------------------------- 13. change requests

    public function test_traveller_asks_for_changes_instead_of_accepting(): void
    {
        Mail::fake();
        $agent = User::factory()->create(['role' => UserRole::Agent, 'email' => 'agent@example.test']);
        $trip = Trip::factory()->status(TripStatus::Approved)->withDays(1)->create(['days' => 1, 'agent_id' => $agent->id, 'final_total' => '900']);
        $trip->travellers()->create(['full_name' => 'Lead', 'email' => 'lead@example.test', 'is_lead' => true]);
        $trip->statusHistory()->create(['from_status' => 'under_review', 'to_status' => 'approved', 'changed_by' => $agent->id, 'note' => 'Kandy hotel was full, moved you to Lake View.']);

        $this->actingAs($trip->user)->get(route('trips.show', $trip))->assertSee('Note from your agent')->assertSee('Kandy hotel was full');

        $this->actingAs($trip->user)->post(route('trips.request-changes', $trip), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($trip->user)->post(route('trips.request-changes', $trip), ['reason' => 'Can we start two days later?'])->assertRedirect();

        $trip->refresh();
        $this->assertSame(TripStatus::UnderReview, $trip->status);
        $this->assertStringContainsString('two days later', $trip->messages()->sole()->body);
        Mail::assertQueued(TripMessageReceived::class, fn ($m) => $m->hasTo('agent@example.test'));
        Mail::assertNotQueued(TripStatusChanged::class);

        $this->actingAs($trip->user)->post(route('trips.request-changes', $trip), ['reason' => 'again please'])->assertSessionHas('error');
    }

    public function test_agent_moves_the_trip_dates(): void
    {
        $agent = User::factory()->create(['role' => UserRole::Agent]);
        $trip = Trip::factory()->status(TripStatus::UnderReview)->withDays(2)->create(['days' => 2, 'start_date' => '2026-12-01']);

        $this->actingAs($agent)->put(route('admin.trips.update', $trip), [
            'start_date' => '2026-12-05', 'guide_type' => 'chauffeur', 'guide_language' => 'English', 'meal_plan' => 'BB',
        ])->assertSessionHasNoErrors();

        $trip->refresh();
        $this->assertSame('2026-12-05', $trip->start_date->toDateString());
        $this->assertSame(['2026-12-05', '2026-12-06'], $trip->tripDays()->get()->map(fn ($d) => $d->date->toDateString())->all());
        $this->assertStringContainsString('Dates moved from 1 Dec 2026 to 5 Dec 2026', $trip->statusHistory()->latest('id')->value('note'));
    }

    // ---------------------------------------------------------------- 14 + 15. admin help, images

    public function test_admin_help_page_and_getting_started_checklist(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'password' => 'password']);

        $this->actingAs($admin)->get(route('admin.help'))->assertOk()->assertSee('A trip request, start to finish');
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Getting started')
            ->assertSee('Change the default password')
            ->assertSee('Submitted trips from travellers', false); // menu tooltip

        $agent = User::factory()->create(['role' => UserRole::Agent]);
        $this->actingAs($agent)->get(route('admin.help'))->assertOk();
        $this->actingAs($agent)->get(route('admin.dashboard'))->assertDontSee('Getting started');
    }

    public function test_packages_without_their_own_photo_use_a_place_photo(): void
    {
        $temple = $this->place('Temple of the Tooth', $this->kandy, 7.2936, 80.6413);
        $photo = Media::factory()->for($temple, 'mediable')->create(['status' => 'published', 'is_cover' => true]);
        $trip = Trip::factory()->create();
        $trip->tripDays()->create(['day_number' => 1, 'date' => now()->toDateString()])->stops()->create(['place_id' => $temple->id, 'sequence' => 1]);
        $package = Package::create(['template_trip_id' => $trip->id, 'name' => 'Kandy Day', 'slug' => 'kandy-day', 'days' => 1, 'is_featured' => true]);

        $this->assertTrue($package->coverPhoto()->is($photo));
        $this->get(route('packages.index'))->assertOk()->assertSee($photo->url(800), false);
    }

    public function test_site_has_a_favicon(): void
    {
        $this->assertFileExists(public_path('images/brand/apple-touch-icon.png'));
        $this->get(route('home'))->assertSee('images/brand/favicon-32.png', false)->assertSee('images/brand/mark-128.png', false);
        $this->assertFileExists(public_path('favicon.ico'));
        $this->assertFileExists(public_path('images/brand/logo-full.png'));
    }
}
