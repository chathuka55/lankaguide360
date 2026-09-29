<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SenderRole;
use App\Enums\Tier;
use App\Enums\TripStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TripPriceItemRequest;
use App\Http\Requests\Admin\TripTransitionRequest;
use App\Http\Requests\Admin\TripUpdateRequest;
use App\Http\Requests\TripMessageRequest;
use App\Models\Guide;
use App\Models\Hotel;
use App\Models\Package;
use App\Models\Place;
use App\Models\Trip;
use App\Models\TripDay;
use App\Models\TripPriceItem;
use App\Models\TripStop;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\PackageService;
use App\Services\TripWorkflow;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Agent request queue and trip review (SRS 8.5, FR-20 to FR-22).
 */
class TripRequestController extends Controller
{
    public const TABS = [
        'submitted' => 'New',
        'under_review' => 'Under review',
        'approved' => 'Approved',
        'confirmed' => 'Confirmed',
        'in_progress' => 'In progress',
        'completed' => 'Completed',
        'rejected' => 'Rejected',
        'cancelled' => 'Cancelled',
        'mine' => 'Assigned to me',
    ];

    public function __construct(private TripWorkflow $workflow) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Trip::class);

        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'submitted';

        $trips = Trip::query()
            ->whereNot('status', TripStatus::Draft)
            ->with(['leadTraveller', 'agent', 'user'])
            ->withCount(['messages as unread_count' => fn ($q) => $q->whereNull('read_at')->where('sender_role', SenderRole::Traveller->value)])
            ->when($tab === 'mine', fn ($q) => $q->where('agent_id', $request->user()->id)->whereIn('status', TripStatus::open()))
            ->when($tab !== 'mine', fn ($q) => $q->where('status', $tab))
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('reference', 'like', "%{$s}%")
                ->orWhereHas('travellers', fn ($t) => $t->where('full_name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"))))
            ->when($request->query('tier'), fn ($q, $tier) => $q->where('tier', $tier))
            ->when($request->query('from'), fn ($q, $date) => $q->whereDate('start_date', '>=', $date))
            ->when($request->query('to'), fn ($q, $date) => $q->whereDate('start_date', '<=', $date))
            ->orderBy($tab === 'submitted' ? 'submitted_at' : 'updated_at', $tab === 'submitted' ? 'asc' : 'desc')
            ->paginate(20)
            ->withQueryString();

        $counts = Trip::whereNot('status', TripStatus::Draft)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $counts['mine'] = Trip::where('agent_id', $request->user()->id)->whereIn('status', TripStatus::open())->count();

        return view('admin.trips.index', ['trips' => $trips, 'tab' => $tab, 'counts' => $counts]);
    }

    public function show(Trip $trip): View
    {
        $this->authorize('view', $trip);

        $this->workflow->markRead($trip, 'agent');

        $trip->load([
            'tripDays.stops.place.district', 'tripDays.hotel.roomRates', 'tripDays.roomRate',
            'travellers', 'priceItems', 'statusHistory.changedBy', 'messages.sender', 'agent', 'user', 'vehicle', 'guide', 'cuisines', 'categories',
        ]);

        $towns = $trip->tripDays->pluck('overnight_town')->filter()->unique();

        return view('admin.trips.show', [
            'trip' => $trip,
            'itemsTotal' => $trip->priceItems->sum(fn (TripPriceItem $i) => Money::cents($i->amount)),
            'vehicles' => Vehicle::orderByRaw("FIELD(tier, 'budget', 'premium', 'luxury')")->orderBy('max_pax')->get(),
            'guides' => Guide::orderBy('type')->orderBy('name')->get(),
            'hotelsByTown' => Hotel::published()->whereIn('town', $towns)->with('roomRates')->orderBy('name')->get()->groupBy('town'),
            'agents' => User::whereIn('role', [UserRole::Agent->value, UserRole::Admin->value])->orderBy('name')->get(),
            'places' => Place::published()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(TripUpdateRequest $request, Trip $trip): RedirectResponse
    {
        DB::transaction(function () use ($request, $trip) {
            $trip->forceFill($request->safe()->except('days'))->save();

            foreach ($request->validated('days', []) as $dayId => $values) {
                $trip->tripDays()->whereKey($dayId)->first()?->forceFill([
                    'hotel_id' => $values['hotel_id'] ?? null,
                    'room_rate_id' => $values['room_rate_id'] ?? null,
                    'overnight_town' => $values['overnight_town'] ?? null,
                ])->save();
            }

            $this->logEdit($trip, $request->user(), 'Plan details edited.');
        });

        return back()->with('status', 'Trip saved.');
    }

    public function assign(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorize('update', $trip);
        $validated = $request->validate(['agent_id' => ['nullable', 'integer', 'exists:users,id']]);

        $agent = $validated['agent_id'] ? User::find($validated['agent_id']) : null;

        try {
            $this->workflow->assign($trip, $agent, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', $agent ? "Assigned to {$agent->name}." : 'Trip unassigned.');
    }

    public function assignToMe(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorize('update', $trip);

        $this->workflow->assign($trip, $request->user(), $request->user());

        if ($trip->status === TripStatus::Submitted) {
            $this->workflow->transition($trip, TripStatus::UnderReview, $request->user(), 'Review started.');
        }

        return redirect()->route('admin.trips.show', $trip)->with('status', 'The trip is assigned to you.');
    }

    public function transition(TripTransitionRequest $request, Trip $trip): RedirectResponse
    {
        $to = TripStatus::from($request->validated('status'));
        $attributes = $to === TripStatus::Approved
            ? ['final_total' => $request->validated('final_total'), 'price_note' => $request->validated('price_note')]
            : [];

        try {
            $this->workflow->transition($trip, $to, $request->user(), $request->validated('note'), $attributes);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "Status changed to {$to->label()}. The traveller has been emailed.");
    }

    public function message(TripMessageRequest $request, Trip $trip): RedirectResponse
    {
        $this->authorize('update', $trip);

        $this->workflow->message($trip, $request->validated('body'), $request->user(), SenderRole::Agent);

        return redirect()->to(route('admin.trips.show', $trip).'#messages')->with('status', 'Message sent to the traveller.');
    }

    public function storeItem(TripPriceItemRequest $request, Trip $trip): RedirectResponse
    {
        $trip->priceItems()->create($this->itemValues($request));

        return redirect()->to(route('admin.trips.show', $trip).'#price')->with('status', 'Price line added.');
    }

    public function updateItem(TripPriceItemRequest $request, TripPriceItem $item): RedirectResponse
    {
        $item->update($this->itemValues($request));

        return redirect()->to(route('admin.trips.show', $item->trip).'#price')->with('status', 'Price line saved.');
    }

    public function destroyItem(Request $request, TripPriceItem $item): RedirectResponse
    {
        $this->authorize('update', $item->trip);
        $trip = $item->trip;
        $item->delete();

        return redirect()->to(route('admin.trips.show', $trip).'#price')->with('status', 'Price line deleted.');
    }

    /**
     * Set the final price from the edited price lines.
     */
    public function recalculate(Request $request, Trip $trip): RedirectResponse
    {
        $this->authorize('update', $trip);

        $total = $trip->priceItems()->get()->sum(fn (TripPriceItem $i) => Money::cents($i->amount));
        $trip->forceFill(['final_total' => Money::decimal($total)])->save();
        $this->logEdit($trip, $request->user(), 'Final price set from the price lines: $'.Money::decimal($total));

        return redirect()->to(route('admin.trips.show', $trip).'#price')->with('status', 'Final price updated to $'.number_format($total / 100, 2).'.');
    }

    /**
     * "Save as package" (admin): copy this trip's plan into a new, unfeatured package.
     */
    public function saveAsPackage(Request $request, Trip $trip, PackageService $packages): RedirectResponse
    {
        $this->authorize('create', Package::class);
        $validated = $request->validate(['name' => ['required', 'string', 'max:150']]);

        $package = $packages->fromTrip($trip, $validated['name']);

        return redirect()->route('admin.packages.edit', $package)->with('status', 'Package created from '.$trip->reference.'. Add a summary, photo and price, then feature it.');
    }

    public function moveStop(Request $request, TripStop $stop): RedirectResponse
    {
        $day = $stop->tripDay;
        $this->authorize('update', $day->trip);
        $validated = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])], 'day_id' => ['nullable', 'integer']]);

        DB::transaction(function () use ($stop, $day, $validated) {
            $stops = $day->stops()->orderBy('sequence')->get()->values();
            $index = $stops->search(fn ($s) => $s->is($stop));
            $swap = $validated['direction'] === 'up' ? $index - 1 : $index + 1;

            if (isset($stops[$swap])) {
                [$a, $b] = [$stops[$index]->sequence, $stops[$swap]->sequence];
                $stops[$index]->update(['sequence' => $b]);
                $stops[$swap]->update(['sequence' => $a]);
            }
        });

        return redirect()->to(route('admin.trips.show', $day->trip).'#day-'.$day->day_number);
    }

    public function addStop(Request $request, TripDay $day): RedirectResponse
    {
        $this->authorize('update', $day->trip);
        $validated = $request->validate(['place_id' => ['required', 'integer', 'exists:places,id']]);

        $day->stops()->create(['place_id' => $validated['place_id'], 'sequence' => (int) $day->stops()->max('sequence') + 1]);
        $this->logEdit($day->trip, $request->user(), "Stop added to day {$day->day_number}.");

        return redirect()->to(route('admin.trips.show', $day->trip).'#day-'.$day->day_number)->with('status', 'Stop added. Times are indicative; update the plan note if needed.');
    }

    public function removeStop(Request $request, TripStop $stop): RedirectResponse
    {
        $day = $stop->tripDay;
        $this->authorize('update', $day->trip);
        $stop->delete();
        $this->logEdit($day->trip, $request->user(), "Stop removed from day {$day->day_number}.");

        return redirect()->to(route('admin.trips.show', $day->trip).'#day-'.$day->day_number)->with('status', 'Stop removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function itemValues(TripPriceItemRequest $request): array
    {
        $qty = (string) $request->validated('qty');
        $unit = (string) $request->validated('unit_price');

        return [
            'category' => $request->validated('category'),
            'description' => $request->validated('description'),
            'qty' => $qty,
            'unit_price' => $unit,
            'amount' => bcmul($qty, $unit, 2),
        ];
    }

    private function logEdit(Trip $trip, User $user, string $note): void
    {
        $trip->statusHistory()->create([
            'from_status' => $trip->status->value,
            'to_status' => $trip->status->value,
            'changed_by' => $user->id,
            'note' => $note,
        ]);
    }

    /**
     * Tiers for the queue filter.
     *
     * @return array<string, string>
     */
    public static function tierOptions(): array
    {
        return collect(Tier::cases())->mapWithKeys(fn (Tier $t) => [$t->value => $t->label()])->all();
    }
}
