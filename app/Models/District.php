<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['province_id', 'name', 'slug', 'lat', 'lng', 'description', 'image'])]
#[WithoutTimestamps]
class District extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:6',
            'lng' => 'decimal:6',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<Province, $this>
     */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /**
     * @return HasMany<Place, $this>
     */
    public function places(): HasMany
    {
        return $this->hasMany(Place::class);
    }

    /**
     * @return HasMany<Hotel, $this>
     */
    public function hotels(): HasMany
    {
        return $this->hasMany(Hotel::class);
    }

    /**
     * Districts linked to any of the given category ids (SRS 4.1, builder step 3).
     *
     * @param  array<int, int>  $categoryIds
     */
    #[Scope]
    protected function inCategories(Builder $query, array $categoryIds): void
    {
        $query->whereHas('categories', fn (Builder $q) => $q->whereKey($categoryIds));
    }
}
