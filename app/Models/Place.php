<?php

namespace App\Models;

use App\Enums\BestTimeSlot;
use App\Enums\CrowdLevel;
use App\Enums\PublishStatus;
use App\Models\Concerns\HasCoordinates;
use App\Models\Concerns\HasMedia;
use App\Models\Concerns\HasPublishStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'district_id', 'name', 'slug', 'short_description', 'description', 'lat', 'lng',
    'visit_minutes', 'open_time', 'close_time', 'best_time_slot',
    'fee_foreign_adult', 'fee_foreign_child', 'crowd_level', 'is_hidden_gem', 'best_months',
    'cover_image', 'is_active', 'status', 'source', 'wikipedia_title', 'wikipedia_url', 'osm_id',
])]
class Place extends Model
{
    use HasCoordinates, HasFactory, HasMedia, HasPublishStatus;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'source' => 'seed',
        'visit_minutes' => 90,
        'best_time_slot' => 'any',
        'crowd_level' => 'medium',
        'is_hidden_gem' => false,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:6',
            'lng' => 'decimal:6',
            'visit_minutes' => 'integer',
            'best_time_slot' => BestTimeSlot::class,
            'fee_foreign_adult' => 'decimal:2',
            'fee_foreign_child' => 'decimal:2',
            'crowd_level' => CrowdLevel::class,
            'is_hidden_gem' => 'boolean',
            'is_active' => 'boolean',
            'status' => PublishStatus::class,
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<District, $this>
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /**
     * @return HasMany<TripStop, $this>
     */
    public function tripStops(): HasMany
    {
        return $this->hasMany(TripStop::class);
    }

    /**
     * Public page: /destinations/{district}/{place} (NFR-13 clean URLs).
     */
    public function url(): string
    {
        return route('destinations.show', [$this->district, $this]);
    }

    /**
     * "2 h 30 min" style duration of a visit.
     */
    public function visitDuration(): string
    {
        $hours = intdiv($this->visit_minutes, 60);
        $minutes = $this->visit_minutes % 60;

        return trim(($hours ? "{$hours} h " : '').($minutes ? "{$minutes} min" : ''));
    }

    public function hasCoordinates(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }

    /**
     * @param  array<int, int>  $districtIds
     */
    #[Scope]
    protected function inDistricts(Builder $query, array $districtIds): void
    {
        $query->whereIn($this->qualifyColumn('district_id'), $districtIds);
    }

    /**
     * Places tagged with any of the given category ids.
     *
     * @param  array<int, int>  $categoryIds
     */
    #[Scope]
    protected function inCategories(Builder $query, array $categoryIds): void
    {
        $query->whereHas('categories', fn (Builder $q) => $q->whereKey($categoryIds));
    }

    #[Scope]
    protected function hiddenGems(Builder $query): void
    {
        $query->where($this->qualifyColumn('is_hidden_gem'), true);
    }
}
