<?php

namespace Tests\Feature;

use App\Enums\TripStatus;
use App\Enums\UserRole;
use App\Models\District;
use App\Models\Hotel;
use App\Models\Place;
use App\Models\RoomRate;
use App\Models\Setting;
use App\Models\Trip;
use App\Models\User;
use App\Services\ReportService;
use App\Support\Money;
use Database\Seeders\LocationSeeder;
use Database\Seeders\TravelOptionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsAndCurrencyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::put('usd_lkr', '300');
        Setting::put('display_currency', 'LKR');
        $this->admin = User::factory()->create(['role' => UserRole::Admin, 'name' => 'Admin Person']);
        $this->agent = User::factory()->create(['role' => UserRole::Agent, 'name' => 'Agent Smith']);
    }

    private function trip(TripStatus $status, array $attributes = [], string $country = 'Germany'): Trip
    {
        $trip = Trip::factory()->status($status)->withDays(1)->create([
            'days' => 1, 'adults' => 2, 'tier' => 'premium', 'estimated_total' => '1000.00', 'submitted_at' => now()->subDays(3),
            ...$attributes,
        ]);
        $trip->travellers()->create(['full_name' => 'Lead '.$trip->id, 'email' => "lead{$trip->id}@example.test", 'country' => $country, 'is_lead' => true]);

        return $trip;
    }

    public function test_money_is_stored_in_usd_and_shown_in_the_display_currency(): void
    {
        $this->assertSame('LKR 45,300', Money::show('151.00'));
        $this->assertSame('USD 151.00', Money::usd('151'));
        $this->assertSame('USD 151.00 (LKR 45,300)', Money::both('151.00'));
        $this->assertSame('≈ USD 151', Money::approxUsd('151.00'));
        $this->assertSame(4530000, Money::convert(15100, 'LKR'));

        Setting::put('usd_lkr', '302.55');
        $this->assertSame('LKR 30,255', Money::show('100'));

        Setting::put('display_currency', 'USD');
        $this->assertSame('$151.00', Money::show('151'));
        $this->assertSame('USD 151.00', Money::both('151'));
        $this->assertSame('', Money::approxUsd('151'));

        Setting::put('display_currency', 'XYZ');
        $this->assertSame('LKR', Money::displayCurrency(), 'Unknown codes fall back to LKR');
    }

    public function test_public_prices_are_shown_in_rupees(): void
    {
        $this->seed(TravelOptionSeeder::class);
        Setting::put('usd_lkr', '300');

        $this->get(route('plan'))->assertOk()->assertSee('LKR 13,500')->assertDontSee('$45');
    }

    public function test_admin_can_change_the_display_currency_but_not_the_base_currency(): void
    {
        $this->seed(TravelOptionSeeder::class);
        $settings = Setting::pluck('value', 'key')->all();

        $this->actingAs($this->admin)->put(route('admin.settings.update'), ['settings' => [...$settings, 'currency' => 'LKR']])
            ->assertSessionHasErrors('settings.currency');

        $this->actingAs($this->admin)->put(route('admin.settings.update'), ['settings' => [...$settings, 'display_currency' => 'usd']])
            ->assertSessionHasNoErrors();
        $this->assertSame('USD', Setting::get('display_currency'));
    }

    public function test_reports_are_for_admins_only(): void
    {
        $this->get(route('admin.reports.index'))->assertRedirect(route('login'));
        $this->actingAs($this->agent)->get(route('admin.reports.index'))->assertForbidden();
        $this->actingAs($this->agent)->get(route('admin.reports.export', 'trips'))->assertForbidden();

        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertSee(route('admin.reports.index'));
    }

    public function test_summary_counts_requests_bookings_and_revenue(): void
    {
        $this->trip(TripStatus::Submitted);
        $this->trip(TripStatus::UnderReview, ['agent_id' => $this->agent->id]);
        $this->trip(TripStatus::Approved, ['agent_id' => $this->agent->id, 'final_total' => '1200.00']);
        $this->trip(TripStatus::Confirmed, ['agent_id' => $this->agent->id, 'final_total' => '1500.50'], 'India');
        $this->trip(TripStatus::Completed, ['agent_id' => $this->agent->id, 'final_total' => '999.50']);
        $this->trip(TripStatus::Cancelled);
        $this->trip(TripStatus::Confirmed, ['final_total' => '5000.00', 'submitted_at' => now()->subYears(2)]); // outside the period
        Trip::factory()->status(TripStatus::Draft)->create(['submitted_at' => now()]); // drafts never count

        $summary = ReportService::forPeriod(null, null)->summary();

        $this->assertSame(6, $summary['requests']);
        $this->assertSame(3, $summary['approved']);
        $this->assertSame(2, $summary['booked']);
        $this->assertSame('2500.00', $summary['revenue']);
        $this->assertSame('1200.00', $summary['pipeline']);
        $this->assertSame('1250.00', $summary['average_value']);
        $this->assertSame(33.3, $summary['booking_rate']);
        $this->assertSame(2, $summary['open']);

        $agent = ReportService::forPeriod(null, null)->agents()->firstWhere('agent', 'Agent Smith');
        $this->assertSame(4, $agent['assigned']);
        $this->assertSame(2, $agent['booked']);
        $this->assertSame('2500.00', $agent['revenue']);

        $this->actingAs($this->admin)->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('LKR 750,000')        // booked revenue 2,500 USD × 300
            ->assertSee('Agent Smith')
            ->assertSee('Germany')
            ->assertSee('Data readiness by district');
    }

    public function test_period_and_tier_filters(): void
    {
        $this->trip(TripStatus::Submitted, ['tier' => 'budget', 'submitted_at' => now()->subMonths(2)]);
        $this->trip(TripStatus::Submitted, ['tier' => 'luxury', 'submitted_at' => now()->subDays(2)]);

        $this->assertSame(1, ReportService::forPeriod(now()->subDays(10)->toDateString(), now()->toDateString())->summary()['requests']);
        $this->assertSame(1, ReportService::forPeriod(null, null, 'budget')->summary()['requests']);

        $monthly = ReportService::forPeriod(null, null)->monthly();
        $this->assertCount(12, $monthly, 'Default period: the last 12 months, empty months included');
        $this->assertSame(2, $monthly->sum('requests'));

        $this->actingAs($this->admin)->get(route('admin.reports.index', ['from' => '2026-05-01', 'to' => '2026-01-01']))
            ->assertSessionHasErrors('to');
    }

    public function test_most_requested_places_and_districts(): void
    {
        $trip = $this->trip(TripStatus::Confirmed, ['final_total' => '800']);
        $place = $trip->tripDays->first()->stops->first()->place;

        $places = ReportService::forPeriod(null, null)->places();
        $this->assertSame($place->name, $places->first()['place']);
        $this->assertSame(1, $places->first()['booked']);
        $this->assertSame($place->district->name, ReportService::forPeriod(null, null)->districts()->first()['district']);
    }

    public function test_csv_export_converts_amounts_and_neutralises_formulas(): void
    {
        $trip = $this->trip(TripStatus::Confirmed, ['final_total' => '100.00']);
        $trip->leadTraveller->update(['full_name' => '=HYPERLINK("http://evil.test")']);

        $response = $this->actingAs($this->admin)->get(route('admin.reports.export', 'trips'));
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = file_get_contents($response->baseResponse->getFile()->getPathname());

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Final (LKR)', $csv);
        $this->assertStringContainsString('30000.00', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString($trip->reference, $csv);

        foreach (array_keys(ReportService::REPORTS) as $report) {
            $this->actingAs($this->admin)->get(route('admin.reports.export', $report))->assertOk();
        }
        $this->actingAs($this->admin)->get('/admin/reports/export/nope')->assertNotFound();
    }

    public function test_pdf_report(): void
    {
        $this->trip(TripStatus::Confirmed, ['final_total' => '100.00']);

        $this->actingAs($this->admin)->get(route('admin.reports.pdf'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_data_readiness_needs_routable_places_and_bookable_hotels_in_every_tier(): void
    {
        $this->seed([LocationSeeder::class]);
        $place = Place::factory()->published()->create(['district_id' => District::where('slug', 'kandy')->value('id'), 'lat' => 7.29, 'lng' => 80.63]);
        foreach (['budget', 'premium'] as $tier) {
            RoomRate::factory()->for(Hotel::factory()->published()->create(['district_id' => $place->district_id, 'tier' => $tier]))->create();
        }

        $kandy = fn () => ReportService::forPeriod(null, null)->content()->firstWhere('district', 'Kandy');
        $this->assertFalse($kandy()['ready'], 'No luxury hotel yet');

        RoomRate::factory()->for(Hotel::factory()->published()->create(['district_id' => $place->district_id, 'tier' => 'luxury']))->create();
        $this->assertTrue($kandy()['ready']);
        $this->assertSame(1, $kandy()['routable_places']);
    }
}
