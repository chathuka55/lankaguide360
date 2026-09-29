<?php

namespace App\Models;

use App\Enums\PublishStatus;
use App\Enums\Tier;
use App\Models\Concerns\HasCoordinates;
use App\Models\Concerns\HasMedia;
use App\Models\Concerns\HasPublishStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'district_id', 'town', 'name', 'slug', 'type', 'tier', 'star_rating', 'kid_friendly',
    'lat', 'lng', 'amenities', 'cover_image', 'is_active', 'status', 'wikipedia_title',
    'osm_id', 'website', 'phone', 'address',
])]
class Hotel extends Model
{
    use HasCoordinates, HasFactory, HasMedia, HasPublishStatus;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'kid_friendly' => false,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'tier' => Tier::class,
            'star_rating' => 'integer',
            'kid_friendly' => 'boolean',
            'lat' => 'decimal:6',
            'lng' => 'decimal:6',
            'amenities' => 'array',
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
     * @return HasMany<RoomRate, $this>
     */
    public function roomRates(): HasMany
    {
        return $this->hasMany(RoomRate::class);
    }

    #[Scope]
    protected function tier(Builder $query, Tier|string $tier): void
    {
        $query->where($this->qualifyColumn('tier'), $tier instanceof Tier ? $tier->value : $tier);
    }

    /**
     * @param  array<int, int>  $districtIds
     */
    #[Scope]
    protected function inDistricts(Builder $query, array $districtIds): void
    {
        $query->whereIn($this->qualifyColumn('district_id'), $districtIds);
    }
}
