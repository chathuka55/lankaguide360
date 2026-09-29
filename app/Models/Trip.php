<?php

namespace App\Models;

use App\Enums\GuideType;
use App\Enums\MealPlan;
use App\Enums\Tier;
use App\Enums\TripStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'reference', 'user_id', 'agent_id', 'tier', 'start_date', 'days', 'adults', 'children', 'infants',
    'children_ages', 'arrival_point', 'departure_point', 'meal_plan', 'vehicle_id', 'guide_type',
    'guide_id', 'guide_language', 'special_requests', 'status', 'estimated_total', 'final_total',
    'currency', 'access_token', 'tracking_consent', 'submitted_at', 'approved_at',
    'internal_notes', 'price_note', 'dietary_notes', 'luggage', 'data_consent_at', 'completed_at',
])]
#[Hidden(['access_token', 'internal_notes'])]
class Trip extends Model
{
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'currency' => 'USD',
        'adults' => 1,
        'children' => 0,
        'infants' => 0,
        'arrival_point' => 'BIA Katunayake',
        'departure_point' => 'BIA Katunayake',
        'meal_plan' => 'BB',
        'guide_type' => 'chauffeur',
        'guide_language' => 'English',
        'tracking_consent' => false,
    ];

    protected function casts(): array
    {
        return [
            'tier' => Tier::class,
            'status' => TripStatus::class,
            'meal_plan' => MealPlan::class,
            'guide_type' => GuideType::class,
            'start_date' => 'date',
            'days' => 'integer',
            'adults' => 'integer',
            'children' => 'integer',
            'infants' => 'integer',
            'estimated_total' => 'decimal:2',
            'final_total' => 'decimal:2',
            'tracking_consent' => 'boolean',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'completed_at' => 'datetime',
            'data_consent_at' => 'datetime',
            'luggage' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsTo<Guide, $this>
     */
    public function guide(): BelongsTo
    {
        return $this->belongsTo(Guide::class);
    }

    /**
     * The itinerary days. Named tripDays because `days` is the day-count column (SRS 7.3).
     *
     * @return HasMany<TripDay, $this>
     */
    public function tripDays(): HasMany
    {
        return $this->hasMany(TripDay::class)->orderBy('day_number');
    }

    /**
     * @return HasMany<TripTraveller, $this>
     */
    public function travellers(): HasMany
    {
        return $this->hasMany(TripTraveller::class);
    }

    /**
     * @return HasOne<TripTraveller, $this>
     */
    public function leadTraveller(): HasOne
    {
        return $this->hasOne(TripTraveller::class)->where('is_lead', true);
    }

    /**
     * @return HasMany<TripPriceItem, $this>
     */
    public function priceItems(): HasMany
    {
        return $this->hasMany(TripPriceItem::class);
    }

    /**
     * @return HasMany<TripStatusHistory, $this>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(TripStatusHistory::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * @return HasMany<TripLocation, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(TripLocation::class);
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'trip_category');
    }

    /**
     * @return BelongsToMany<Cuisine, $this>
     */
    public function cuisines(): BelongsToMany
    {
        return $this->belongsToMany(Cuisine::class, 'trip_cuisine');
    }

    /**
     * @return HasOne<Package, $this>
     */
    public function package(): HasOne
    {
        return $this->hasOne(Package::class, 'template_trip_id');
    }

    public function travellerCount(): int
    {
        return $this->adults + $this->children + $this->infants;
    }

    /**
     * The price to show: final (agent-confirmed) when set, else the estimate.
     */
    public function displayTotal(): ?string
    {
        return $this->final_total ?? $this->estimated_total;
    }

    /**
     * Private link for guests (FR-19, NFR-06): the reference plus the 40-character token.
     */
    public function privateUrl(): string
    {
        return route('trips.show', ['trip' => $this->reference, 'token' => $this->access_token]);
    }

    public function contactEmail(): ?string
    {
        return $this->leadTraveller?->email ?? $this->user?->email;
    }

    public function contactName(): string
    {
        return $this->leadTraveller?->full_name ?? $this->user?->name ?? 'Traveller';
    }

    /**
     * Messages the given side has not read yet.
     */
    public function unreadMessagesFor(string $side): int
    {
        return $this->messages()
            ->whereNull('read_at')
            ->where('sender_role', $side === 'agent' ? 'traveller' : 'agent')
            ->count();
    }

    #[Scope]
    protected function tier(Builder $query, Tier|string $tier): void
    {
        $query->where($this->qualifyColumn('tier'), $tier instanceof Tier ? $tier->value : $tier);
    }

    #[Scope]
    protected function withStatus(Builder $query, TripStatus|string ...$statuses): void
    {
        $query->whereIn($this->qualifyColumn('status'), array_map(
            fn (TripStatus|string $status) => $status instanceof TripStatus ? $status->value : $status,
            $statuses,
        ));
    }
}
