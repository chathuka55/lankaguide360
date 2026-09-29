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
