<?php

namespace Tests\Feature;

use App\Enums\TripStatus;
use App\Enums\UserRole;
use App\Mail\TripAccepted;
use App\Mail\TripReceiptIssued;
use App\Mail\TripStatusChanged;
use App\Models\Setting;
use App\Models\Trip;
use App\Models\TripReceipt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BookingReceiptTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Setting::put('usd_lkr', '300');
        Setting::put('display_currency', 'LKR');
        $this->agent = User::factory()->create(['role' => UserRole::Agent, 'email' => 'agent@example.test']);
    }

    private function trip(TripStatus $status, array $attributes = []): Trip
    {
        $trip = Trip::factory()->status($status)->withDays(2)->create([
            'days' => 2, 'adults' => 2, 'final_total' => '1000.00', 'agent_id' => $this->agent->id, ...$attributes,
        ]);
        $trip->travellers()->create(['full_name' => 'Nimal Perera', 'email' => 'nimal@example.test', 'country' => 'Sri Lanka', 'is_lead' => true]);
        $trip->priceItems()->create(['category' => 'accommodation', 'description' => 'Hotel nights', 'qty' => 2, 'unit_price' => '500.00', 'amount' => '1000.00']);

        return $trip;
    }

    private function finalize(Trip $trip, array $extra = []): TestResponse
    {
        $times = $trip->tripDays()->with('stops')->get()->flatMap->stops
            ->mapWithKeys(fn ($stop, $i) => [$stop->id => ['arrive_at' => sprintf('%02d:00', 8 + $i), 'depart_at' => sprintf('%02d:30', 8 + $i)]])->all();

        return $this->actingAs($this->agent)->post(route('admin.trips.finalize', $trip), ['times' => $times, ...$extra]);
    }

    public function test_traveller_accepts_an_approved_trip(): void
    {
        $trip = $this->trip(TripStatus::Approved);

        $this->actingAs($trip->user)->get(route('trips.show', $trip))->assertSee('Accept and confirm booking')->assertSee('LKR 300,000');

        $this->actingAs($trip->user)->post(route('trips.accept', $trip))->assertSessionHasErrors('accept');
        $this->actingAs($trip->user)->post(route('trips.accept', $trip), ['accept' => '1'])->assertRedirect($trip->privateUrl());

        $trip->refresh();
        $this->assertSame(TripStatus::Confirmed, $trip->status);
        $this->assertNotNull($trip->accepted_at);
        Mail::assertQueued(TripAccepted::class, fn ($mail) => $mail->hasTo('agent@example.test'));
        Mail::assertQueued(TripStatusChanged::class, fn ($mail) => $mail->status === TripStatus::Confirmed);
    }

    public function test_a_guest_with_the_private_link_can_accept_but_strangers_cannot(): void
    {
        $trip = $this->trip(TripStatus::Approved, ['user_id' => null]);

        $this->post(route('trips.accept', $trip), ['accept' => '1'])->assertNotFound();
        $this->actingAs(User::factory()->create())->post(route('trips.accept', $trip), ['accept' => '1'])->assertNotFound();
        $this->assertSame(TripStatus::Approved, $trip->fresh()->status);

        auth()->logout();
        $this->get($trip->privateUrl())->assertOk();
        $this->post(route('trips.accept', $trip), ['accept' => '1'])->assertRedirect();
        $this->assertSame(TripStatus::Confirmed, $trip->fresh()->status);
    }

    public function test_only_approved_trips_can_be_accepted(): void
    {
        $trip = $this->trip(TripStatus::UnderReview);

        $this->actingAs($trip->user)->post(route('trips.accept', $trip), ['accept' => '1'])->assertSessionHas('error');
        $this->assertSame(TripStatus::UnderReview, $trip->fresh()->status);
    }

    public function test_receipt_needs_an_accepted_booking(): void
    {
        $trip = $this->trip(TripStatus::Approved);

        $this->finalize($trip)->assertSessionHas('error');
        $this->assertNull($trip->receipt()->first());
        $this->actingAs($this->agent)->get(route('admin.trips.show', $trip))->assertSee('Waiting for the traveller to accept');
    }

    public function test_agent_finalizes_timings_and_issues_the_e_receipt(): void
    {
        $trip = $this->trip(TripStatus::Confirmed, ['accepted_at' => now()]);

        $this->finalize($trip, ['paid_usd' => '300.00', 'payment_method' => 'bank_transfer', 'payment_note' => '30% deposit'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $receipt = TripReceipt::sole();
        $this->assertMatchesRegularExpression('/^LG360-R-\d{4}-00001$/', $receipt->number);
        $this->assertSame(1, $receipt->revision);
        $this->assertSame('LKR', $receipt->currency);
        $this->assertSame('1000.00', $receipt->total_usd);
        $this->assertSame('700.00', $receipt->balanceUsd());
        $this->assertSame('LKR 210,000', $receipt->money($receipt->balanceUsd()));
        $this->assertNotNull($trip->fresh()->itinerary_finalized_at);
        $this->assertSame('08:00', $receipt->snapshot['itinerary'][0]['stops'][0]['arrive']);
        $this->assertSame('08:00:00', $trip->tripDays()->first()->stops()->first()->arrive_at);

        Mail::assertQueued(TripReceiptIssued::class, function (TripReceiptIssued $mail) use ($receipt) {
            $pdf = collect($mail->attachments())->first(fn ($a) => $a->as === "LankaGuide360-e-receipt-{$receipt->number}.pdf");
            $this->assertNotNull($pdf, 'The e-receipt PDF is attached');
            $this->assertStringStartsWith('%PDF', $pdf->attachWith(fn () => '', fn ($data) => $data()));

            return $mail->hasTo('nimal@example.test');
        });
    }

    public function test_reissuing_keeps_the_number_and_freezes_the_snapshot(): void
    {
        $trip = $this->trip(TripStatus::Confirmed);
        $this->finalize($trip);
        $number = TripReceipt::sole()->number;

        // Later edits don't change the issued receipt until it is re-issued.
        $trip->update(['final_total' => '1100.00']);
        $this->assertSame('1000.00', TripReceipt::sole()->total_usd);

        $this->finalize($trip, ['paid_usd' => '1100.00', 'payment_method' => 'card']);
        $receipt = TripReceipt::sole();
        $this->assertSame($number, $receipt->number);
        $this->assertSame(2, $receipt->revision);
        $this->assertTrue($receipt->isPaidInFull());

        $second = $this->trip(TripStatus::Confirmed);
        $this->finalize($second);
        $this->assertStringEndsWith('-00002', $second->receipt()->first()->number);
    }

    public function test_times_are_validated(): void
    {
        $trip = $this->trip(TripStatus::Confirmed);
        $stop = $trip->tripDays()->first()->stops()->first();

        $this->actingAs($this->agent)->post(route('admin.trips.finalize', $trip), ['times' => [$stop->id => ['arrive_at' => '10:00', 'depart_at' => '09:00']]])
            ->assertSessionHasErrors("times.{$stop->id}.depart_at");
        $this->actingAs($this->agent)->post(route('admin.trips.finalize', $trip), ['times' => [$stop->id => ['arrive_at' => '25:00']]])
            ->assertSessionHasErrors("times.{$stop->id}.arrive_at");
        $this->actingAs($trip->user)->post(route('admin.trips.finalize', $trip), [])->assertForbidden();
        $this->assertNull($trip->receipt()->first());
    }

    public function test_traveller_views_prints_and_downloads_the_receipt(): void
    {
        $trip = $this->trip(TripStatus::Confirmed);
        $this->finalize($trip, ['paid_usd' => '1000.00']);
        $receipt = TripReceipt::sole();

        $this->actingAs($trip->user)->get(route('trips.show', $trip))->assertSee('Your e-receipt '.$receipt->number);
        $this->actingAs($trip->user)->get(route('my-trips'))->assertSee('E-receipt ready');
        $this->actingAs($trip->user)->get(route('trips.receipt', $trip))
            ->assertOk()
            ->assertSee($receipt->number)
            ->assertSee('Nimal Perera')
            ->assertSee('Paid in full')
            ->assertSee('data:image/png;base64,', false)
            ->assertSee('window.print()', false);
        $this->actingAs($trip->user)->get(route('trips.receipt.pdf', $trip))->assertOk()->assertHeader('content-type', 'application/pdf');

        auth()->logout();
        $this->get(route('trips.receipt', $trip))->assertNotFound();
        $this->get(route('trips.receipt', ['trip' => $trip->reference, 'token' => $trip->access_token]))->assertOk();
    }

    public function test_no_receipt_before_it_is_issued(): void
    {
        $trip = $this->trip(TripStatus::Confirmed);

        $this->actingAs($trip->user)->get(route('trips.receipt', $trip))->assertNotFound();
    }

    public function test_qr_verification_page(): void
    {
        $trip = $this->trip(TripStatus::Confirmed);
        $this->finalize($trip);
        $receipt = TripReceipt::sole();

        $this->get($receipt->verifyUrl())->assertOk()->assertSee('Genuine booking')->assertSee('N. Perera')->assertDontSee('nimal@example.test')->assertDontSee('300,000');
        $this->get(route('receipts.verify', ['number' => $receipt->number, 'code' => 'wrong']))->assertOk()->assertSee('Not recognised')->assertDontSee('Perera');
        $this->get(route('receipts.verify', ['number' => 'LG360-R-1999-99999']))->assertSee('Not recognised');

        $trip->update(['status' => TripStatus::Cancelled]);
        $this->get($receipt->verifyUrl())->assertSee('No longer valid');
        $this->actingAs($trip->user)->get(route('trips.receipt', $trip))->assertSee('CANCELLED');
    }
}
