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
    'district_id', 'town', 'name', 'description', 'slug', 'type', 'tier', 'star_rating', 'kid_friendly',
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
            'is_demo' => 'boolean',
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

    /** Labels for the amenity keys stored in `amenities`. */
    public const AMENITY_LABELS = [
        'wifi' => 'Free Wi-Fi', 'pool' => 'Swimming pool', 'beach_front' => 'Beach front', 'air_conditioning' => 'Air conditioning',
        'restaurant' => 'Restaurant', 'bar' => 'Bar', 'spa' => 'Spa', 'gym' => 'Gym', 'parking' => 'Parking',
        'kids_club' => 'Kids club', 'room_service' => 'Room service', 'wheelchair' => 'Wheelchair access',
    ];

    public function url(): string
    {
        return route('hotels.show', $this);
    }

    /**
     * The admin's description, or a short factual summary for imported hotels that have none.
     */
    public function summary(): string
    {
        if (filled($this->description)) {
            return $this->description;
        }

        $kind = str_replace('_', ' ', (string) ($this->type ?: 'hotel'));
        $stars = $this->star_rating ? "{$this->star_rating}-star " : '';
        $where = trim(($this->town ? "in {$this->town}" : '').($this->district?->name ? ", {$this->district->name} District" : ''), ', ');
        $features = collect($this->amenities ?? [])->map(fn ($a) => strtolower(self::AMENITY_LABELS[$a] ?? str_replace('_', ' ', $a)))->take(4)->join(', ', ' and ');

        return ucfirst(trim("A {$stars}{$this->tier->label()} {$kind} {$where}")).'.'
            .($features !== '' ? " Facilities include {$features}." : '')
            .($this->kid_friendly ? ' Families with children are welcome.' : '');
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
