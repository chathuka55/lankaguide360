<?php

namespace Tests\Feature;

use App\Enums\SenderRole;
use App\Enums\Tier;
use App\Enums\TripStatus;
use App\Enums\UserRole;
use App\Mail\NewTripRequest;
use App\Mail\TripMessageReceived;
use App\Mail\TripStatusChanged;
use App\Mail\TripSubmitted;
use App\Models\District;
use App\Models\Hotel;
use App\Models\Place;
use App\Models\Review;
use App\Models\Trip;
use App\Models\User;
use App\Services\ItineraryService;
use App\Services\TripPdf;
use App\Support\TripDraft;
use Database\Seeders\TravelOptionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TripWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        config(['lankaguide.routing.ors_key' => null, 'lankaguide.contact.admin_email' => 'office@example.test']);
        $this->seed(TravelOptionSeeder::class);
        Mail::fake();

        $this->agent = User::factory()->create(['role' => UserRole::Agent, 'email' => 'agent@example.test']);
    }

    /**
     * A generated 3-day Kandy / Sigiriya draft, as the builder leaves it in the session.
     *
     * @return array<string, mixed>
     */
    private function draftSession(): array
    {
        $kandy = District::factory()->create(['name' => 'Kandy', 'slug' => 'kandy']);
        $matale = District::factory()->create(['name' => 'Matale', 'slug' => 'matale']);
        $places = collect([
            ['Temple of the Tooth', $kandy, 7.2936, 80.6413],
            ['Peradeniya Gardens', $kandy, 7.2714, 80.5957],
            ['Sigiriya', $matale, 7.9570, 80.7600],
        ])->map(fn ($p) => Place::factory()->published()->for($p[1])->create(['name' => $p[0], 'lat' => $p[2], 'lng' => $p[3], 'visit_minutes' => 90, 'best_time_slot' => 'any', 'open_time' => null, 'close_time' => null]));
        Hotel::factory()->published()->for($kandy)->create(['town' => 'Kandy', 'tier' => Tier::Premium, 'lat' => 7.29, 'lng' => 80.63]);

        $draft = new TripDraft;
        $draft->tier = 'premium';
        $draft->startDate = now()->addMonths(2)->toDateString();
        $draft->days = 3;
        $draft->placeIds = $places->pluck('id')->all();
        $draft->itinerary = app(ItineraryService::class)->generate($draft)->toArray();

        return [TripDraft::SESSION_KEY => $draft->toArray()];
    }

    /**
     * @return array<string, mixed>
     */
    private function guestForm(array $overrides = []): array
    {
        return [
            'full_name' => 'Nimal Perera',
            'email' => 'nimal@example.test',
            'phone' => '+94 77 123 4567',
            'country' => 'Sri Lanka',
            'age' => 34,
            'consent' => '1',
            'companions' => [['name' => 'Ayesha Perera', 'age' => 31]],
            ...$overrides,
        ];
    }

    private function submittedTrip(array $attributes = []): Trip
    {
        $trip = Trip::factory()->status(TripStatus::Submitted)->withDays(2)->create(['days' => 2, ...$attributes]);
        $trip->travellers()->create(['full_name' => 'Lead Person', 'email' => 'lead@example.test', 'phone' => '+94771234567', 'is_lead' => true]);

        return $trip;
    }

    public function test_guest_submits_a_trip_and_gets_a_private_link(): void
    {
        $response = $this->withSession($this->draftSession())->post(route('trips.store'), $this->guestForm());

        $trip = Trip::sole();
        $response->assertRedirect($trip->privateUrl());

        $this->assertMatchesRegularExpression('/^LG360-\d{4}-00001$/', $trip->reference);
        $this->assertSame(TripStatus::Submitted, $trip->status);
        $this->assertNull($trip->user_id);
        $this->assertSame(3, $trip->days);
        $this->assertSame(3, $trip->tripDays()->count());
        $this->assertSame(3, $trip->tripDays()->withCount('stops')->get()->sum('stops_count'));
        $this->assertSame(['Nimal Perera', 'Ayesha Perera'], $trip->travellers()->orderByDesc('is_lead')->pluck('full_name')->all());
        $this->assertGreaterThan(0, $trip->priceItems()->count());
        $this->assertGreaterThan(0, (float) $trip->estimated_total);
        $this->assertNotNull($trip->data_consent_at);
        $this->assertSame(TripStatus::Submitted->value, $trip->statusHistory()->sole()->to_status);

        Mail::assertQueued(TripSubmitted::class, fn ($mail) => $mail->hasTo('nimal@example.test'));
        Mail::assertQueued(NewTripRequest::class, fn ($mail) => $mail->hasTo('agent@example.test') && $mail->hasTo('office@example.test'));

        // The draft is cleared and the confirmation page shows the reference.
        $this->followRedirects($response)->assertOk()->assertSee($trip->reference)->assertSee('Your trip request has been sent');
        $this->assertNull(session(TripDraft::SESSION_KEY));
    }

    public function test_references_are_sequential(): void
    {
        $session = $this->draftSession();
        $this->withSession($session)->post(route('trips.store'), $this->guestForm());
        $this->withSession($session)->post(route('trips.store'), $this->guestForm());

        $this->assertSame(
            ['LG360-'.now()->year.'-00001', 'LG360-'.now()->year.'-00002'],
            Trip::orderBy('id')->pluck('reference')->all(),
        );
    }

    public function test_guest_must_give_contact_details_and_consent(): void
    {
        $this->withSession($this->draftSession())
            ->post(route('trips.store'), ['full_name' => '', 'phone' => '0771234567'])
            ->assertSessionHasErrors(['full_name', 'email', 'phone', 'country', 'age', 'consent']);

        $this->assertSame(0, Trip::count());
    }

    public function test_submit_without_a_plan_goes_back_to_the_builder(): void
    {
        $this->post(route('trips.store'), $this->guestForm())->assertRedirect(route('plan'));

        $this->assertSame(0, Trip::count());
    }

    public function test_registered_user_submits_and_sees_the_trip_in_my_trips(): void
    {
        $user = User::factory()->create(['name' => 'Sam Traveller', 'email' => 'sam@example.test']);

        $this->actingAs($user)->withSession($this->draftSession())
            ->post(route('trips.store'), ['consent' => '1'])
            ->assertRedirect();

        $trip = Trip::sole();
        $this->assertTrue($trip->user->is($user));
        $this->assertSame('Sam Traveller', $trip->leadTraveller->full_name);
        Mail::assertQueued(TripSubmitted::class, fn ($mail) => $mail->hasTo('sam@example.test'));

        $this->actingAs($user)->get(route('my-trips'))->assertOk()->assertSee($trip->reference);
        $this->actingAs($user)->get(route('trips.show', $trip))->assertOk();
    }

    public function test_guest_can_create_an_account_at_submit(): void
    {
        $this->withSession($this->draftSession())->post(route('trips.store'), $this->guestForm([
            'create_account' => '1', 'password' => 'Secret-pass-123', 'password_confirmation' => 'Secret-pass-123',
        ]));

        $user = User::where('email', 'nimal@example.test')->sole();
        $this->assertSame(UserRole::Traveller, $user->role);
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Trip::sole()->user->is($user));
    }

    public function test_existing_email_is_not_attached_to_the_new_trip(): void
    {
        $owner = User::factory()->create(['email' => 'nimal@example.test']);

        $this->withSession($this->draftSession())->post(route('trips.store'), $this->guestForm([
            'create_account' => '1', 'password' => 'Secret-pass-123', 'password_confirmation' => 'Secret-pass-123',
        ]))->assertSessionHas('warning');

        $this->assertGuest();
        $this->assertNull(Trip::sole()->user_id);
        $this->assertSame(1, User::where('email', 'nimal@example.test')->count());
        $this->assertTrue($owner->fresh()->trips->isEmpty());
    }

    public function test_trip_page_needs_the_token_the_owner_or_staff(): void
    {
        $trip = $this->submittedTrip();
        $stranger = User::factory()->create();

        $this->get(route('trips.show', $trip))->assertNotFound();
        $this->get(route('trips.show', ['trip' => $trip->reference, 'token' => 'wrong-token']))->assertNotFound();
        $this->actingAs($stranger)->get(route('trips.show', $trip))->assertNotFound();

        $this->actingAs($trip->user)->get(route('trips.show', $trip))->assertOk()->assertSee($trip->reference);
        $this->actingAs($this->agent)->get(route('trips.show', $trip))->assertOk();
    }

    public function test_token_link_grants_access_for_the_session(): void
    {
        $trip = $this->submittedTrip(['user_id' => null]);

        $this->get($trip->privateUrl())->assertOk()->assertSee($trip->reference);
        $this->get(route('trips.show', $trip))->assertOk();
    }

    public function test_travellers_cannot_see_other_travellers_trips_in_my_trips(): void
    {
        $mine = $this->submittedTrip();
        $theirs = $this->submittedTrip();

        $this->actingAs($mine->user)->get(route('my-trips'))
            ->assertOk()
            ->assertSee($mine->reference)
            ->assertDontSee($theirs->reference);
    }

    public function test_travellers_cannot_open_the_admin_trip_queue(): void
    {
        $trip = $this->submittedTrip();

        $this->actingAs($trip->user)->get(route('admin.trips.index'))->assertForbidden();
        $this->actingAs($trip->user)->post(route('admin.trips.transition', $trip), ['status' => 'approved', 'final_total' => 1])->assertForbidden();
    }

    public function test_agent_queue_and_detail_pages(): void
    {
        $trip = $this->submittedTrip();

        $this->actingAs($this->agent)->get(route('admin.trips.index'))->assertOk()->assertSee($trip->reference)->assertSee('Assign to me');
        $this->actingAs($this->agent)->get(route('admin.trips.index', ['tab' => 'approved']))->assertOk()->assertDontSee($trip->reference);
        $this->actingAs($this->agent)->get(route('admin.trips.index', ['q' => 'Lead Person']))->assertOk()->assertSee($trip->reference);
        $this->actingAs($this->agent)->get(route('admin.trips.show', $trip))->assertOk()->assertSee('Lead Person')->assertSee('wa.me/94771234567', false);
    }

    public function test_assign_to_me_starts_the_review(): void
    {
        $trip = $this->submittedTrip();

        $this->actingAs($this->agent)->post(route('admin.trips.assign-to-me', $trip))->assertRedirect(route('admin.trips.show', $trip));

        $trip->refresh();
        $this->assertSame(TripStatus::UnderReview, $trip->status);
        $this->assertTrue($trip->agent->is($this->agent));
        Mail::assertQueued(TripStatusChanged::class, fn ($mail) => $mail->hasTo('lead@example.test') && $mail->status === TripStatus::UnderReview);
    }

    public function test_invalid_transitions_are_refused(): void
    {
        $trip = $this->submittedTrip();

        $this->actingAs($this->agent)->from(route('admin.trips.show', $trip))
            ->post(route('admin.trips.transition', $trip), ['status' => 'completed'])
            ->assertSessionHas('error');

        $this->assertSame(TripStatus::Submitted, $trip->fresh()->status);
        Mail::assertNothingQueued();
    }

    public function test_rejecting_needs_a_reason(): void
    {
        $trip = $this->submittedTrip(['status' => TripStatus::UnderReview]);

        $this->actingAs($this->agent)->post(route('admin.trips.transition', $trip), ['status' => 'rejected'])->assertSessionHasErrors('note');
        $this->assertSame(TripStatus::UnderReview, $trip->fresh()->status);

        $this->actingAs($this->agent)->post(route('admin.trips.transition', $trip), ['status' => 'rejected', 'note' => 'No hotels free on those dates.']);
        $this->assertSame(TripStatus::Rejected, $trip->fresh()->status);
        Mail::assertQueued(TripStatusChanged::class, fn ($mail) => $mail->note === 'No hotels free on those dates.');
    }

    public function test_approval_sets_the_final_price_and_emails_the_plan(): void
    {
        $trip = $this->submittedTrip(['status' => TripStatus::UnderReview, 'estimated_total' => '1000.00']);

        $this->actingAs($this->agent)->post(route('admin.trips.transition', $trip), [
            'status' => 'approved', 'final_total' => '1150.50', 'price_note' => 'Peak-season hotel rates',
        ])->assertSessionHasNoErrors();

        $trip->refresh();
        $this->assertSame(TripStatus::Approved, $trip->status);
        $this->assertSame('1150.50', $trip->final_total);
        $this->assertSame('Peak-season hotel rates', $trip->price_note);
        $this->assertNotNull($trip->approved_at);
        $this->assertSame('approved', $trip->statusHistory->last()->to_status);

        Mail::assertQueued(TripStatusChanged::class, function (TripStatusChanged $mail) use ($trip) {
            $mail->assertSeeInHtml('1,150.50');
            $pdf = collect($mail->attachments())->first(fn (Attachment $a) => $a->as === "LankaGuide360-{$trip->reference}.pdf");
            $this->assertNotNull($pdf, 'The approval email attaches the plan as PDF.');
            $this->assertStringStartsWith('%PDF', $pdf->attachWith(fn () => '', fn ($data) => $data()));

            return $mail->hasTo('lead@example.test') && $mail->status === TripStatus::Approved;
        });
    }

    public function test_approval_needs_a_final_price(): void
    {
        $trip = $this->submittedTrip(['status' => TripStatus::UnderReview]);

        $this->actingAs($this->agent)->post(route('admin.trips.transition', $trip), ['status' => 'approved'])->assertSessionHasErrors('final_total');
    }

    public function test_pdf_is_available_once_approved(): void
    {
        if (! app(TripPdf::class)->available()) {
            $this->markTestSkipped('barryvdh/laravel-dompdf is not installed.');
        }

        $trip = $this->submittedTrip(['status' => TripStatus::Approved, 'final_total' => '900.00']);

        $response = $this->actingAs($trip->user)->get(route('trips.pdf', $trip));
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_pdf_is_not_offered_before_approval(): void
    {
        $trip = $this->submittedTrip();

        $this->actingAs($trip->user)->get(route('trips.pdf', $trip))->assertForbidden();
    }

    public function test_messages_go_both_ways_and_are_marked_read(): void
    {
        $trip = $this->submittedTrip(['agent_id' => $this->agent->id]);

        $this->actingAs($trip->user)->post(route('trips.message', $trip), ['body' => 'Can we add a safari?'])->assertRedirect();
        Mail::assertQueued(TripMessageReceived::class, fn ($mail) => $mail->hasTo('agent@example.test'));

        $this->actingAs($this->agent)->get(route('admin.trips.index', ['tab' => 'submitted']))->assertOk();
        $this->assertSame(1, $trip->unreadMessagesFor('agent'));

        $this->actingAs($this->agent)->get(route('admin.trips.show', $trip))->assertOk()->assertSee('Can we add a safari?');
        $this->assertSame(0, $trip->unreadMessagesFor('agent'));

        $this->actingAs($this->agent)->post(route('admin.trips.message', $trip), ['body' => 'Yes, Yala on day 2.']);
        Mail::assertQueued(TripMessageReceived::class, fn ($mail) => $mail->hasTo('lead@example.test'));

        $message = $trip->messages()->reorder()->latest('id')->first();
        $this->assertSame(SenderRole::Agent, $message->sender_role);
        $this->actingAs($trip->user)->get(route('my-trips'))->assertSee('1 new message');
    }

    public function test_traveller_can_cancel_before_confirmation_only(): void
    {
        $trip = $this->submittedTrip();
        $this->actingAs($trip->user)->post(route('trips.cancel', $trip))->assertRedirect(route('my-trips'));
        $this->assertSame(TripStatus::Cancelled, $trip->fresh()->status);

        $confirmed = $this->submittedTrip(['status' => TripStatus::Confirmed]);
        $this->actingAs($confirmed->user)->post(route('trips.cancel', $confirmed))->assertForbidden();

        $other = $this->submittedTrip();
        $this->actingAs($trip->user)->post(route('trips.cancel', $other))->assertForbidden();
    }

    public function test_review_only_after_completion(): void
    {
        $trip = $this->submittedTrip();
        $this->actingAs($trip->user)->post(route('trips.review', $trip), ['rating' => 5, 'comment' => 'Wonderful trip, thanks!'])->assertForbidden();

        $trip->update(['status' => TripStatus::Completed]);
        $this->actingAs($trip->user)->get(route('trips.show', $trip))->assertSee('How was your trip?');
        $this->actingAs($trip->user)->post(route('trips.review', $trip), ['rating' => 5, 'comment' => 'Wonderful trip, thanks!'])->assertRedirect();

        $review = Review::sole();
        $this->assertFalse($review->is_approved);
        $this->assertSame($trip->id, $review->trip_id);
        $this->actingAs($trip->user)->get(route('trips.show', $trip))->assertDontSee('How was your trip?');
    }

    public function test_agent_edits_price_lines_and_sets_final_price(): void
    {
        $trip = $this->submittedTrip(['status' => TripStatus::UnderReview]);
        $trip->priceItems()->create(['category' => 'accommodation', 'description' => 'Hotel', 'qty' => 2, 'unit_price' => '100.00', 'amount' => '200.00']);

        $this->actingAs($this->agent)->post(route('admin.trips.items.store', $trip), [
            'category' => 'discount', 'description' => 'Loyalty discount', 'qty' => 1, 'unit_price' => '-20.50',
        ])->assertSessionHasNoErrors();

        $item = $trip->priceItems()->where('category', 'accommodation')->sole();
        $this->actingAs($this->agent)->put(route('admin.trips.items.update', $item), [
            'category' => 'accommodation', 'description' => 'Hotel (upgraded)', 'qty' => 2, 'unit_price' => '110.25',
        ])->assertSessionHasNoErrors();
        $this->assertSame('220.50', $item->fresh()->amount);

        $this->actingAs($this->agent)->post(route('admin.trips.recalculate', $trip));
        $this->assertSame('200.00', $trip->fresh()->final_total);
    }

    public function test_agent_edits_plan_and_stops(): void
    {
        $trip = $this->submittedTrip(['status' => TripStatus::UnderReview]);
        $day = $trip->tripDays()->first();
        [$first, $second] = $day->stops()->get()->all();
        $hotel = Hotel::factory()->published()->create(['town' => 'Kandy']);

        $this->actingAs($this->agent)->put(route('admin.trips.update', $trip), [
            'guide_type' => 'national', 'guide_language' => 'German', 'meal_plan' => 'HB', 'internal_notes' => 'VIP client',
            'days' => [$day->id => ['hotel_id' => $hotel->id, 'overnight_town' => 'Kandy']],
        ])->assertSessionHasNoErrors();

        $trip->refresh();
        $this->assertSame('German', $trip->guide_language);
        $this->assertSame('VIP client', $trip->internal_notes);
        $this->assertSame($hotel->id, $day->fresh()->hotel_id);

        $this->actingAs($this->agent)->post(route('admin.trips.stops.move', $second), ['direction' => 'up']);
        $this->assertSame([$second->id, $first->id], $day->stops()->pluck('id')->all());

        $this->actingAs($this->agent)->delete(route('admin.trips.stops.destroy', $first));
        $this->assertSame([$second->id], $day->stops()->pluck('id')->all());

        // Internal notes never reach the traveller's page.
        $this->actingAs($trip->user)->get(route('trips.show', $trip))->assertDontSee('VIP client');
    }
}
