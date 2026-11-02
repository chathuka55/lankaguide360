<?php

namespace Tests\Feature;

use App\Enums\GuideType;
use App\Enums\Tier;
use App\Enums\UserRole;
use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\PricingService;
use App\Services\SuggestionService;
use App\Support\TripDraft;
use Database\Seeders\TravelOptionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Self-drive hire for Sri Lankan residents with a valid Sri Lankan driving licence (Nov 2026).
 */
class SelfDriveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TravelOptionSeeder::class);
        Setting::put('usd_lkr', '300');
    }

    private function draft(Vehicle $vehicle, string $type = 'local'): TripDraft
    {
        $draft = new TripDraft;
        $draft->tier = 'budget';
        $draft->adults = 2;
        $draft->days = 2;
        $draft->vehicleId = $vehicle->id;
        $draft->travellerType = $type;
        $draft->selfDrive = true;
        $draft->itinerary = ['days' => [
            ['number' => 1, 'stops' => [], 'overnight' => null, 'drive_km' => 180, 'drive_minutes' => 240],
            ['number' => 2, 'stops' => [], 'overnight' => null, 'drive_km' => 70, 'drive_minutes' => 90],
        ]];

        return $draft;
    }

    public function test_seeded_cars_and_vans_offer_self_drive_but_coaches_do_not(): void
    {
        $this->assertTrue(Vehicle::where('type', 'Car')->sole()->offersSelfDrive());
        $this->assertFalse(Vehicle::where('type', 'Large coach')->sole()->offersSelfDrive());
        $this->assertNotEmpty(Vehicle::where('type', 'Car')->sole()->amenities, 'Fresh databases get vehicle services too.');
    }

    public function test_self_drive_price_uses_the_self_drive_rate_and_extra_km(): void
    {
        $car = Vehicle::where('type', 'Car')->sole();
        $car->update(['self_drive_day_rate' => 30, 'self_drive_deposit' => 100, 'self_drive_km_per_day' => 100, 'km_rate' => 0.30]);

        $price = app(PricingService::class)->breakdown($this->draft($car))->toArray();
        $lines = collect($price['lines']);

        $this->assertTrue($lines->contains(fn ($l) => $l['description'] === 'Car, self-drive, 2 days' && $l['amount'] === '60.00'));
        // 250 km driven, 200 km included → 50 km × 0.30.
        $extra = $lines->first(fn ($l) => str_starts_with($l['description'], 'Extra mileage'));
        $this->assertSame('15.00', $extra['amount']);
        $this->assertFalse($lines->contains(fn ($l) => str_contains($l['description'], 'with driver')));
        $this->assertTrue(collect($price['notes'])->contains(fn ($n) => str_contains($n, 'refundable deposit') && str_contains($n, 'driving licence')));
    }

    public function test_foreign_visitors_always_pay_for_a_driver(): void
    {
        $car = Vehicle::where('type', 'Car')->sole();

        $lines = collect(app(PricingService::class)->breakdown($this->draft($car, 'foreign'))->toArray()['lines']);

        $this->assertTrue($lines->contains(fn ($l) => $l['description'] === 'Car with driver, 2 days'));
        $this->assertFalse($lines->contains(fn ($l) => str_contains($l['description'], 'self-drive')));
    }

    public function test_suggestions_for_self_drive(): void
    {
        $suggestions = app(SuggestionService::class);

        $this->assertTrue($suggestions->vehicleFor(2, null, Tier::Budget, true)->offersSelfDrive());
        $this->assertTrue($suggestions->vehicleOptions(4, true)->every(fn (Vehicle $v) => $v->offersSelfDrive() && $v->max_pax >= 4));
        $this->assertNull($suggestions->vehicleFor(12, null, Tier::Budget, true), 'No self-drive vehicle seats 12.');
        $rule = $suggestions->guideRule(2, Tier::Premium, true);
        $this->assertNotContains(GuideType::Chauffeur, $rule['options']);
    }

    public function test_vehicle_hire_page_takes_self_drive_requests_with_a_licence(): void
    {
        Mail::fake();
        $car = Vehicle::where('type', 'Car')->sole();
        $coach = Vehicle::where('type', 'Large coach')->sole();
        $base = [
            'name' => 'Nimal', 'email' => 'nimal@example.test', 'phone' => '+94 77 123 4567',
            'start_date' => now()->addWeek()->toDateString(), 'end_date' => now()->addWeeks(2)->toDateString(),
            'pickup' => 'Kandy', 'travellers' => 2, 'mode' => 'self',
        ];

        $this->get(route('vehicles.index'))->assertOk()->assertSee('Self-drive')->assertSee('Sri Lankan residents with a Sri Lankan licence');

        $this->post(route('vehicles.request', $car), $base)->assertSessionHasErrors(['driver_name', 'licence_no', 'licence_expiry', 'resident']);
        $this->post(route('vehicles.request', $car), [...$base, 'driver_name' => 'Nimal', 'licence_no' => 'B1234567', 'licence_expiry' => now()->addDays(8)->toDateString(), 'resident' => '1'])
            ->assertSessionHasErrors('licence_expiry');

        $valid = [...$base, 'driver_name' => 'Nimal Perera', 'licence_no' => 'b1234567', 'licence_expiry' => now()->addYears(2)->toDateString(), 'resident' => '1'];
        $this->post(route('vehicles.request', $coach), $valid)->assertSessionHasErrors('mode');
        $this->post(route('vehicles.request', $car), $valid)->assertRedirect()->assertSessionHas('status');

        $message = ContactMessage::sole();
        $this->assertSame('Vehicle hire request: Car (self-drive)', $message->subject);
        $this->assertStringContainsString('licence B1234567', $message->body);
        Mail::assertQueued(ContactMessageReceived::class);

        // With a driver no licence is needed.
        $this->post(route('vehicles.request', $coach), [...$base, 'mode' => 'driver', 'travellers' => 30])->assertSessionHasNoErrors();
    }

    public function test_admin_sets_self_drive_rates(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $van = Vehicle::where('type', 'Mini coach')->sole();

        $this->actingAs($admin)->get(route('admin.vehicles.edit', $van))->assertOk()->assertSee('Self-drive day rate');

        $this->actingAs($admin)->put(route('admin.vehicles.update', $van), [
            ...$van->only(['type', 'example_model', 'min_pax', 'max_pax', 'luggage_capacity', 'day_rate', 'km_rate']),
            'tier' => $van->tier->value, 'is_rentable' => '1',
            'self_drive' => '1', 'self_drive_day_rate' => '', 'self_drive_deposit' => '200', 'self_drive_km_per_day' => '100',
        ])->assertSessionHasErrors('self_drive_day_rate');

        $this->actingAs($admin)->put(route('admin.vehicles.update', $van), [
            ...$van->only(['type', 'example_model', 'min_pax', 'max_pax', 'luggage_capacity', 'day_rate', 'km_rate']),
            'tier' => $van->tier->value, 'is_rentable' => '1',
            'self_drive' => '1', 'self_drive_day_rate' => '80', 'self_drive_deposit' => '200', 'self_drive_km_per_day' => '100',
        ])->assertSessionHasNoErrors();

        $this->assertTrue($van->fresh()->offersSelfDrive());
    }
}
