<?php

namespace App\Http\Controllers;

use App\Enums\SenderRole;
use App\Enums\TripStatus;
use App\Enums\UserRole;
use App\Http\Requests\SubmitTripRequest;
use App\Http\Requests\TripMessageRequest;
use App\Http\Requests\TripReviewRequest;
use App\Models\Review;
use App\Models\Trip;
use App\Models\User;
use App\Services\TripPdf;
use App\Services\TripSubmissionService;
use App\Services\TripWorkflow;
use App\Support\TripDraft;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Submitting a plan, the private trip page (owner, staff or token link), PDF, messages,
 * cancel and review (FR-18 to FR-22, FR-25, FR-30), and My Trips.
 */
class TripController extends Controller
{
    /**
     * My Trips dashboard for the signed-in traveller.
     */
    public function index(Request $request): View
    {
        return view('trips.my-trips', [
            'trips' => $request->user()->trips()
                ->whereNot('status', TripStatus::Draft)
                ->withCount(['messages as unread_count' => fn ($q) => $q->whereNull('read_at')->where('sender_role', SenderRole::Agent->value)])
                ->latest('id')
                ->get(),
        ]);
    }

    public function store(SubmitTripRequest $request, TripSubmissionService $submissions): RedirectResponse
    {
        $draft = TripDraft::fromSession($request->session());

        if (! $draft->hasItinerary() || ! $draft->startDate) {
            return redirect()->route('plan')->with('error', 'Please finish planning your trip before submitting.');
        }

        $draft->specialRequests = $request->validated('special_requests');
        $user = $request->user();

        if (! $user && $request->boolean('create_account')) {
            $user = $this->createAccount($request);
        }

        $lead = $user && ! $request->filled('full_name')
            ? ['full_name' => $user->name, 'email' => $user->email, 'phone' => $user->phone, 'country' => $user->country, 'age' => $user->age]
            : $request->safe()->only(['full_name', 'email', 'phone', 'whatsapp', 'country', 'age', 'companions']);

        $trip = $submissions->submit($draft, $lead, $user);

        TripDraft::forget($request->session());
        $this->grantAccess($request, $trip);

        return redirect()->to($trip->privateUrl())->with('submitted', true);
    }

    public function show(Request $request, Trip $trip, TripWorkflow $workflow): View
    {
        $this->authorizeAccess($request, $trip);

        if ($trip->status === TripStatus::Draft) {
            abort(404);
        }

        $workflow->markRead($trip, 'traveller');

        return view('trips.show', [
            'trip' => $trip->load([
                'tripDays.stops.place.district', 'tripDays.hotel', 'tripDays.roomRate',
                'priceItems', 'statusHistory', 'messages.sender', 'vehicle', 'guide', 'leadTraveller', 'cuisines',
            ]),
            'canCancel' => $request->user()?->can('cancel', $trip) ?? false,
            'canReview' => ($request->user()?->can('review', $trip) ?? false) && ! Review::where('trip_id', $trip->id)->exists(),
            'canDownload' => app(TripPdf::class)->available() && in_array($trip->status, [TripStatus::Approved, TripStatus::Confirmed, TripStatus::InProgress, TripStatus::Completed], true),
            'submitted' => session('submitted', false),
        ]);
    }

    public function pdf(Request $request, Trip $trip, TripPdf $pdf): Response
    {
        $this->authorizeAccess($request, $trip);

        abort_unless(
            $request->user()?->isStaff() || in_array($trip->status, [TripStatus::Approved, TripStatus::Confirmed, TripStatus::InProgress, TripStatus::Completed], true),
            403,
            'The PDF is available once an agent has approved the trip.',
        );

        abort_unless($pdf->available(), 503, 'PDF download is not available yet. Please try again later.');

        return $pdf->make($trip)->download($pdf->filename($trip));
    }

    public function message(TripMessageRequest $request, Trip $trip, TripWorkflow $workflow): RedirectResponse
    {
        $this->authorizeAccess($request, $trip);

        $workflow->message($trip, $request->validated('body'), $request->user(), SenderRole::Traveller);

        return redirect()->to($trip->privateUrl().'#messages')->with('status', 'Message sent to your agent.');
    }

    public function cancel(Request $request, Trip $trip, TripWorkflow $workflow): RedirectResponse
    {
        $this->authorize('cancel', $trip);

        $workflow->transition($trip, TripStatus::Cancelled, $request->user(), 'Cancelled by the traveller.');

        return redirect()->route('my-trips')->with('status', "Trip {$trip->reference} has been cancelled.");
    }

    public function review(TripReviewRequest $request, Trip $trip): RedirectResponse
    {
        $this->authorize('review', $trip);

        $review = new Review;
        $review->forceFill([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            'trip_id' => $trip->id,
            'author_name' => $request->user()->name,
            'country' => $request->user()->country,
            'is_approved' => false,
        ])->save();

        return redirect()->to($trip->privateUrl())->with('status', 'Thank you for your review! It will appear on the site after a quick check.');
    }

    /**
     * Owner, staff, a valid token in the URL, or a token already validated this session.
     */
    private function authorizeAccess(Request $request, Trip $trip): void
    {
        $token = (string) $request->query('token', '');

        if ($token !== '' && hash_equals($trip->access_token, $token)) {
            $this->grantAccess($request, $trip);

            return;
        }

        if ($request->user()?->can('view', $trip) || $request->session()->get("trip_access.{$trip->reference}") === true) {
            return;
        }

        // 404 rather than 403: don't confirm that a reference exists.
        abort(404);
    }

    private function grantAccess(Request $request, Trip $trip): void
    {
        $request->session()->put("trip_access.{$trip->reference}", true);
    }

    /**
     * One-click account at submit. If the email is already registered we don't attach the trip
     * to that account (we can't prove it's theirs): the trip is submitted as a guest instead.
     */
    private function createAccount(SubmitTripRequest $request): ?User
    {
        if (User::where('email', $request->validated('email'))->exists()) {
            session()->flash('warning', 'An account with this email already exists. Log in to see this trip under My Trips; meanwhile use the private link we emailed you.');

            return null;
        }

        $user = new User;
        $user->forceFill([
            'name' => $request->validated('full_name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
            'phone' => $request->validated('phone'),
            'country' => $request->validated('country'),
            'age' => $request->validated('age'),
            'role' => UserRole::Traveller,
        ])->save();

        event(new Registered($user));
        Auth::login($user);

        return $user;
    }
}
