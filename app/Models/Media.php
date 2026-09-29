<?php

namespace App\Models;

use App\Enums\MediaSource;
use App\Enums\MediaStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * An image with its WebP variants and license/credit (CLAUDE.md data and images rules).
 */
#[Fillable([
    'mediable_type', 'mediable_id', 'path', 'variants', 'width', 'height', 'alt', 'caption',
    'source', 'source_url', 'author', 'license', 'license_url', 'is_cover', 'sort_order', 'status',
])]
class Media extends Model
{
    use HasFactory;

    /** Widths generated for every image, smallest first. */
    public const WIDTHS = [400, 800, 1600];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'is_cover' => false,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'variants' => 'array',
            'width' => 'integer',
            'height' => 'integer',
            'source' => MediaSource::class,
            'is_cover' => 'boolean',
            'sort_order' => 'integer',
            'status' => MediaStatus::class,
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', MediaStatus::Published);
    }

    /**
     * Public URL of the variant closest to $width (falls back to the original path).
     */
    public function url(int $width = 1600): string
    {
        $variants = $this->variants ?? [];
        $chosen = collect(self::WIDTHS)->first(fn (int $w) => $w >= $width && isset($variants[$w]));

        return Storage::disk('public')->url($chosen ? $variants[$chosen] : $this->path);
    }

    /**
     * "url 400w, url 800w, ..." for <img srcset>.
     */
    public function srcset(): string
    {
        return collect($this->variants ?? [])
            ->map(fn (string $path, int|string $width) => Storage::disk('public')->url($path).' '.$width.'w')
            ->implode(', ');
    }

    /**
     * Credit line, e.g. "Photo: Jane Doe, CC BY-SA 4.0, via Wikimedia Commons".
     */
    public function credit(): ?string
    {
        if (! $this->author && ! $this->license) {
            return null;
        }

        $parts = array_filter([$this->author, $this->license]);
        $via = $this->source === MediaSource::Commons ? ', via Wikimedia Commons' : '';

        return 'Photo: '.implode(', ', $parts).$via;
    }
}
