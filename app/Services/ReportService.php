<?php

namespace App\Services;

use App\Enums\PublishStatus;
use App\Enums\Tier;
use App\Enums\TripStatus;
use App\Enums\UserRole;
use App\Models\District;
use App\Models\Trip;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

/**
 * Admin reports: trip requests, conversion, revenue, destinations, hotels, agents and data
 * readiness. Amounts are USD decimals (as stored); views and exports format them.
 *
 * Trips count when they were submitted inside the period. Package templates and drafts
 * never count.
 */
class ReportService
{
    /** Trips the business has won: the traveller confirmed (and may already be travelling). */
    public const BOOKED = [TripStatus::Confirmed, TripStatus::InProgress, TripStatus::Completed];

    /** Trips an agent has priced and approved, including those confirmed since. */
    public const APPROVED_OR_LATER = [TripStatus::Approved, TripStatus::Confirmed, TripStatus::InProgress, TripStatus::Completed];

    public const REPORTS = [
        'monthly' => 'Requests and revenue by month',
        'status' => 'Trips by status',
        'tiers' => 'Trips by travel style',
        'places' => 'Most requested places',
        'districts' => 'Most requested districts',
        'hotels' => 'Most chosen hotels',
        'agents' => 'Agent performance',
        'countries' => 'Traveller countries',
        'content' => 'Data readiness by district',
        'trips' => 'All trip requests',
    ];

    public function __construct(
        public readonly Carbon $from,
        public readonly Carbon $to,
        public readonly ?Tier $tier = null,
    ) {}

    public static function forPeriod(?string $from, ?string $to, ?string $tier = null): self
    {
        $end = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();
        $start = $from ? Carbon::parse($from)->startOfDay() : $end->copy()->subMonthsNoOverflow(11)->startOfMonth();

        return new self($start, $end, Tier::tryFrom((string) $tier));
    }

    /**
     * Submitted trips in the period (no drafts, no package templates).
     *
     * @return Builder<Trip>
     */
    public function trips(): Builder
    {
        return Trip::query()
            ->whereNot('trips.status', TripStatus::Draft->value)
            ->whereBetween('trips.submitted_at', [$this->from, $this->to])
            ->when($this->tier, fn ($q) => $q->where('trips.tier', $this->tier->value));
    }

    /**
     * Headline numbers.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $statuses = $this->trips()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $requests = (int) $statuses->sum();
        $approved = (int) collect(self::APPROVED_OR_LATER)->sum(fn (TripStatus $s) => $statuses[$s->value] ?? 0);
        $booked = (int) collect(self::BOOKED)->sum(fn (TripStatus $s) => $statuses[$s->value] ?? 0);
        $revenue = $this->amount($this->trips()->whereIn('status', $this->values(self::BOOKED)));
        $pipeline = $this->amount($this->trips()->where('status', TripStatus::Approved->value));

        return [
            'requests' => $requests,
            'approved' => $approved,
            'booked' => $booked,
            'cancelled' => (int) ($statuses[TripStatus::Cancelled->value] ?? 0),
            'rejected' => (int) ($statuses[TripStatus::Rejected->value] ?? 0),
            'open' => (int) collect([TripStatus::Submitted, TripStatus::UnderReview])->sum(fn (TripStatus $s) => $statuses[$s->value] ?? 0),
            'approval_rate' => $requests ? round($approved / $requests * 100, 1) : null,
            'booking_rate' => $requests ? round($booked / $requests * 100, 1) : null,
            'revenue' => $revenue,
            'pipeline' => $pipeline,
            'average_value' => $booked ? Money::decimal(intdiv(Money::cents($revenue), $booked)) : null,
            'average_days' => $requests ? round((float) $this->trips()->avg('days'), 1) : null,
            'travellers' => (int) $this->trips()->sum(DB::raw('adults + children + infants')),
            'response_hours' => $this->averageResponseHours($this->trips()),
            'guests' => $this->trips()->whereNull('user_id')->count(),
        ];
    }

    /**
     * One row per month in the period, including empty months.
     *
     * @return Collection<int, array{month: string, label: string, requests: int, booked: int, revenue: string}>
     */
    public function monthly(): Collection
    {
        $booked = $this->values(self::BOOKED);
        $rows = $this->trips()
            ->selectRaw("DATE_FORMAT(submitted_at, '%Y-%m') as month, count(*) as requests")
            ->selectRaw('sum(case when status in ('.implode(',', array_fill(0, count($booked), '?')).') then 1 else 0 end) as booked', $booked)
            ->selectRaw('sum(case when status in ('.implode(',', array_fill(0, count($booked), '?')).') then coalesce(final_total, estimated_total, 0) else 0 end) as revenue', $booked)
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        $months = collect();
        for ($month = $this->from->copy()->startOfMonth(); $month <= $this->to; $month->addMonthNoOverflow()) {
            $row = $rows->get($month->format('Y-m'));
            $months->push([
                'month' => $month->format('Y-m'),
                'label' => $month->format('M Y'),
                'requests' => (int) ($row->requests ?? 0),
                'booked' => (int) ($row->booked ?? 0),
                'revenue' => Money::decimal(Money::cents($row->revenue ?? '0')),
            ]);
        }

        return $months;
    }

    /**
     * @return Collection<int, array{status: TripStatus, label: string, trips: int, value: string}>
     */
    public function status(): Collection
    {
        $rows = $this->trips()
            ->selectRaw('status, count(*) as trips, sum(coalesce(final_total, estimated_total, 0)) as value')
            ->groupBy('status')->get()->keyBy(fn ($row) => $row->status->value);

        return collect(TripStatus::cases())
            ->reject(fn (TripStatus $s) => $s === TripStatus::Draft)
            ->map(fn (TripStatus $s) => [
                'status' => $s,
                'label' => $s->label(),
                'trips' => (int) ($rows[$s->value]->trips ?? 0),
                'value' => Money::decimal(Money::cents($rows[$s->value]->value ?? '0')),
            ])->values();
    }

    /**
     * @return Collection<int, array{tier: Tier, label: string, trips: int, booked: int, revenue: string, average_days: float|null}>
     */
    public function tiers(): Collection
    {
        $booked = $this->values(self::BOOKED);
        $rows = $this->trips()
            ->selectRaw('tier, count(*) as trips, avg(days) as average_days')
            ->selectRaw('sum(case when status in ('.implode(',', array_fill(0, count($booked), '?')).') then 1 else 0 end) as booked', $booked)
            ->selectRaw('sum(case when status in ('.implode(',', array_fill(0, count($booked), '?')).') then coalesce(final_total, estimated_total, 0) else 0 end) as revenue', $booked)
            ->groupBy('tier')->get()->keyBy(fn ($row) => $row->tier->value);

        return collect(Tier::cases())->map(fn (Tier $t) => [
            'tier' => $t,
            'label' => $t->label(),
            'trips' => (int) ($rows[$t->value]->trips ?? 0),
            'booked' => (int) ($rows[$t->value]->booked ?? 0),
            'revenue' => Money::decimal(Money::cents($rows[$t->value]->revenue ?? '0')),
            'average_days' => isset($rows[$t->value]) ? round((float) $rows[$t->value]->average_days, 1) : null,
        ]);
    }

    /**
     * Places that appear in the most trip requests.
     *
     * @return Collection<int, array{place: string, district: string, trips: int, booked: int}>
     */
    public function places(int $limit = 15): Collection
    {
        $booked = $this->values(self::BOOKED);

        return $this->trips()
            ->join('trip_days', 'trip_days.trip_id', '=', 'trips.id')
            ->join('trip_stops', 'trip_stops.trip_day_id', '=', 'trip_days.id')
            ->join('places', 'places.id', '=', 'trip_stops.place_id')
            ->join('districts', 'districts.id', '=', 'places.district_id')
            ->selectRaw('places.name as place, districts.name as district, count(distinct trips.id) as trips')
            ->selectRaw('count(distinct case when trips.status in ('.implode(',', array_fill(0, count($booked), '?')).') then trips.id end) as booked', $booked)
            ->groupBy('places.id', 'places.name', 'districts.name')
            ->orderByDesc('trips')->orderBy('places.name')
            ->limit($limit)->get()
            ->map(fn ($row) => ['place' => $row->place, 'district' => $row->district, 'trips' => (int) $row->trips, 'booked' => (int) $row->booked]);
    }

    /**
     * @return Collection<int, array{district: string, trips: int, stops: int}>
     */
    public function districts(int $limit = 25): Collection
    {
        return $this->trips()
            ->join('trip_days', 'trip_days.trip_id', '=', 'trips.id')
            ->join('trip_stops', 'trip_stops.trip_day_id', '=', 'trip_days.id')
            ->join('places', 'places.id', '=', 'trip_stops.place_id')
            ->join('districts', 'districts.id', '=', 'places.district_id')
            ->selectRaw('districts.name as district, count(distinct trips.id) as trips, count(*) as stops')
            ->groupBy('districts.id', 'districts.name')
            ->orderByDesc('trips')->orderBy('districts.name')
            ->limit($limit)->get()
            ->map(fn ($row) => ['district' => $row->district, 'trips' => (int) $row->trips, 'stops' => (int) $row->stops]);
    }

    /**
     * @return Collection<int, array{hotel: string, town: string, tier: string, nights: int, trips: int}>
     */
    public function hotels(int $limit = 15): Collection
    {
        return $this->trips()
            ->join('trip_days', 'trip_days.trip_id', '=', 'trips.id')
            ->join('hotels', 'hotels.id', '=', 'trip_days.hotel_id')
            ->selectRaw('hotels.name as hotel, hotels.town as town, hotels.tier as tier, count(*) as nights, count(distinct trips.id) as trips')
            ->groupBy('hotels.id', 'hotels.name', 'hotels.town', 'hotels.tier')
            ->orderByDesc('nights')->orderBy('hotels.name')
            ->limit($limit)->get()
            ->map(fn ($row) => ['hotel' => $row->hotel, 'town' => (string) $row->town, 'tier' => ucfirst((string) $row->tier), 'nights' => (int) $row->nights, 'trips' => (int) $row->trips]);
    }

    /**
     * Per agent or admin: assigned, approved, rejected, booked revenue, response time.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function agents(): Collection
    {
        $staff = User::whereIn('role', [UserRole::Agent->value, UserRole::Admin->value])->orderBy('name')->get(['id', 'name', 'role']);
        $unassigned = $this->trips()->whereNull('agent_id')->whereNotIn('status', [TripStatus::Cancelled->value])->count();

        return $staff->map(function (User $user) {
            $mine = fn () => $this->trips()->where('agent_id', $user->id);

            return [
                'agent' => $user->name,
                'role' => $user->role->label(),
                'assigned' => $mine()->count(),
                'open' => $mine()->whereIn('status', [TripStatus::Submitted->value, TripStatus::UnderReview->value])->count(),
                'approved' => $mine()->whereIn('status', $this->values(self::APPROVED_OR_LATER))->count(),
                'rejected' => $mine()->where('status', TripStatus::Rejected->value)->count(),
                'booked' => $mine()->whereIn('status', $this->values(self::BOOKED))->count(),
                'revenue' => $this->amount($mine()->whereIn('status', $this->values(self::BOOKED))),
                'response_hours' => $this->averageResponseHours($mine()),
            ];
        })->filter(fn (array $row) => $row['assigned'] > 0 || $row['role'] === UserRole::Agent->label())
            ->values()
            ->when($unassigned > 0, fn (Collection $rows) => $rows->push([
                'agent' => 'Not assigned', 'role' => '—', 'assigned' => $unassigned, 'open' => null, 'approved' => null,
                'rejected' => null, 'booked' => null, 'revenue' => null, 'response_hours' => null,
            ]));
    }

    /**
     * @return Collection<int, array{country: string, trips: int, travellers: int}>
     */
    public function countries(int $limit = 20): Collection
    {
        return $this->trips()
            ->join('trip_travellers', fn ($join) => $join->on('trip_travellers.trip_id', '=', 'trips.id')->where('trip_travellers.is_lead', true))
            ->selectRaw("coalesce(nullif(trim(trip_travellers.country), ''), 'Not given') as country, count(*) as trips, sum(trips.adults + trips.children + trips.infants) as travellers")
            ->groupBy('country')
            ->orderByDesc('trips')->orderBy('country')
            ->limit($limit)->get()
            ->map(fn ($row) => ['country' => $row->country, 'trips' => (int) $row->trips, 'travellers' => (int) $row->travellers]);
    }

    /**
     * Is each district ready for the Trip Builder? Published places with a location, and
     * published hotels with room rates in every tier. Not limited to the period.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function content(): Collection
    {
        $published = PublishStatus::Published->value;
        $places = DB::table('places')->selectRaw('district_id, count(*) as total')
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as published', [$published])
            ->selectRaw('sum(case when status = ? and lat is not null then 1 else 0 end) as routable', [$published])
            ->groupBy('district_id')->get()->keyBy('district_id');

        $hotels = DB::table('hotels')
            ->selectRaw('district_id, tier, count(*) as total')
            ->selectRaw('sum(case when status = ? and exists (select 1 from room_rates where room_rates.hotel_id = hotels.id) then 1 else 0 end) as bookable', [$published])
            ->groupBy('district_id', 'tier')->get()->groupBy('district_id');

        return District::orderBy('name')->get(['id', 'name'])->map(function (District $district) use ($places, $hotels) {
            $p = $places->get($district->id);
            $h = collect($hotels->get($district->id, []))->keyBy('tier');
            $bookable = collect(Tier::cases())->mapWithKeys(fn (Tier $t) => [$t->value => (int) ($h[$t->value]->bookable ?? 0)]);

            return [
                'district' => $district->name,
                'places' => (int) ($p->total ?? 0),
                'published_places' => (int) ($p->published ?? 0),
                'routable_places' => (int) ($p->routable ?? 0),
                'hotels' => (int) $h->sum('total'),
                'bookable_budget' => $bookable['budget'],
                'bookable_premium' => $bookable['premium'],
                'bookable_luxury' => $bookable['luxury'],
                'ready' => (int) ($p->routable ?? 0) > 0 && $bookable->every(fn (int $n) => $n > 0),
            ];
        });
    }

    /**
     * Every trip in the period, for the CSV export.
     *
     * @return LazyCollection<int, array<string, mixed>>
     */
    public function tripRows(): LazyCollection
    {
        return $this->trips()
            ->with(['leadTraveller:id,trip_id,full_name,email,country', 'agent:id,name'])
            ->orderBy('submitted_at')
            ->lazy(200)
            ->map(fn (Trip $trip) => [
                'reference' => $trip->reference,
                'submitted' => $trip->submitted_at?->format('Y-m-d H:i'),
                'status' => $trip->status->label(),
                'tier' => $trip->tier->label(),
                'start_date' => $trip->start_date?->toDateString(),
                'days' => $trip->days,
                'travellers' => $trip->travellerCount(),
                'lead_traveller' => $trip->leadTraveller?->full_name,
                'email' => $trip->leadTraveller?->email,
                'country' => $trip->leadTraveller?->country,
                'agent' => $trip->agent?->name,
                'estimate' => $trip->estimated_total,
                'final' => $trip->final_total,
            ]);
    }

    /**
     * Sum of final price (or the estimate while no final price is set), as a USD decimal.
     */
    private function amount(Builder $query): string
    {
        return Money::decimal(Money::cents((string) ($query->sum(DB::raw('coalesce(final_total, estimated_total, 0)')) ?: '0')));
    }

    /**
     * Average hours from submission to the first status change by an agent.
     */
    private function averageResponseHours(Builder $trips): ?float
    {
        $hours = (clone $trips)
            ->joinSub(
                DB::table('trip_status_history')
                    ->selectRaw('trip_id, min(created_at) as first_change')
                    ->where('from_status', TripStatus::Submitted->value)
                    ->groupBy('trip_id'),
                'first', 'first.trip_id', '=', 'trips.id',
            )
            ->selectRaw('avg(timestampdiff(minute, trips.submitted_at, first.first_change)) as minutes')
            ->value('minutes');

        return $hours === null ? null : round((float) $hours / 60, 1);
    }

    /**
     * @param  array<int, TripStatus>  $statuses
     * @return array<int, string>
     */
    private function values(array $statuses): array
    {
        return array_map(fn (TripStatus $s) => $s->value, $statuses);
    }
}
