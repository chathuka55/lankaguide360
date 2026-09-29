<?php

namespace App\Models\Concerns;

use App\Enums\MediaStatus;
use App\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Photo gallery (media table) for places, hotels and other models with public pages.
 *
 * @mixin Model
 */
trait HasMedia
{
    /**
     * Draft and published photos, in display order (rejected photos excluded).
     *
     * @return MorphMany<Media, $this>
     */
    public function media(): MorphMany
    {
        return $this->allMedia()->where('status', '!=', MediaStatus::Rejected->value);
    }

    /**
     * Every media row, including rejected ones kept so importers don't fetch them again.
     *
     * @return MorphMany<Media, $this>
     */
    public function allMedia(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Photos visitors can see.
     *
     * @return MorphMany<Media, $this>
     */
    public function publishedMedia(): MorphMany
    {
        return $this->allMedia()->where('status', MediaStatus::Published->value);
    }

    /**
     * The photo marked as cover. Eager-load with ->with('cover').
     *
     * @return MorphOne<Media, $this>
     */
    public function cover(): MorphOne
    {
        return $this->morphOne(Media::class, 'mediable')
            ->where('is_cover', true)
            ->where('status', '!=', MediaStatus::Rejected->value);
    }
}
