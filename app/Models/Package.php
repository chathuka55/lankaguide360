<?php

namespace App\Models;

use App\Enums\Tier;
use App\Models\Concerns\HasMedia;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'template_trip_id', 'name', 'slug', 'tier', 'days', 'from_price', 'cover_image', 'summary',
    'inclusions', 'exclusions', 'is_featured', 'sort_order',
])]
#[WithoutTimestamps]
class Package extends Model
{
    use HasMedia;

    protected function casts(): array
    {
        return [
            'tier' => Tier::class,
            'days' => 'integer',
            'from_price' => 'decimal:2',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<Trip, $this>
     */
    /**
     * The package's own cover photo, or the first published photo of a place on its route
     * (packages created from trips have no photos of their own).
     */
    public function coverPhoto(): ?Media
    {
        if ($this->cover) {
            return $this->cover;
        }

        $placeIds = TripStop::query()
            ->join('trip_days', 'trip_days.id', '=', 'trip_stops.trip_day_id')
            ->where('trip_days.trip_id', $this->template_trip_id)
            ->orderBy('trip_days.day_number')->orderBy('trip_stops.sequence')
            ->pluck('trip_stops.place_id');

        return Media::query()
            ->where('mediable_type', 'place')
            ->whereIn('mediable_id', $placeIds)
            ->where('status', 'published')
            ->orderByRaw('FIELD(mediable_id, '.($placeIds->map(fn ($id) => (int) $id)->join(',') ?: '0').')')
            ->orderByDesc('is_cover')->orderBy('sort_order')
            ->first();
    }

    public function templateTrip(): BelongsTo
    {
        return $this->belongsTo(Trip::class, 'template_trip_id');
    }

    #[Scope]
    protected function featured(Builder $query): void
    {
        $query->where('is_featured', true)->orderBy('sort_order');
    }
}
