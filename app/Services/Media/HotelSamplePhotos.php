<?php

namespace App\Services\Media;

use App\Models\District;
use App\Models\Hotel;
use App\Models\Media;
use App\Models\SamplePhoto;
use Illuminate\Support\Collection;

/**
 * Sample photos for hotels that have none of their own (most OpenStreetMap hotels and every demo
 * hotel). The pool is small (data/hotel_sample_photos.php); each hotel gets a stable choice that
 * suits its setting (hill country, beach or inland, from its district), tier and type.
 * Views must label them "Sample photo": they show the kind of stay, not this hotel.
 *
 * Registered as a scoped singleton, so the pool and district settings load once per request.
 */
final class HotelSamplePhotos
{
    /** @var Collection<int, SamplePhoto>|null */
    private ?Collection $pool = null;

    /** @var array<int, string>|null district id => beach | hill | inland */
    private ?array $settings = null;

    /**
     * Up to four photos: a room, the view, a pool (premium, luxury and resorts) and Sri Lankan food.
     *
     * @return Collection<int, Media>
     */
    public function for(Hotel $hotel): Collection
    {
        $subjects = ['room', 'view'];
        if (in_array($hotel->tier?->value, ['premium', 'luxury'], true) || $hotel->type === 'resort') {
            $subjects[] = 'pool';
        }
        $subjects[] = 'food';

        return collect($subjects)->map(fn (string $subject) => $this->pick($hotel, $subject))->filter()->values();
    }

    /** For hotel cards: one of the hotel's own sample set (room, view or pool), so lists don't repeat. */
    public function cover(Hotel $hotel): ?Media
    {
        $set = $this->for($hotel)->reject(fn (Media $m) => $m->mediable?->subject === 'food')->values();

        return $set->isEmpty() ? null : $set[crc32('cover-'.$hotel->getKey()) % $set->count()];
    }

    private function pick(Hotel $hotel, string $subject): ?Media
    {
        $candidates = $this->pool()->where('subject', $subject);
        if ($candidates->isEmpty()) {
            return null;
        }

        $tier = $hotel->tier?->value;
        $setting = $this->setting($hotel->district_id);

        $settingOk = fn (SamplePhoto $p) => $p->settings === null || in_array($setting, $p->settings, true);
        $tierOk = fn (SamplePhoto $p) => $p->tiers === null || in_array($tier, $p->tiers, true);

        // Best fit first, then ignore the hotel type, then the tier too.
        $fits = $candidates->filter(fn (SamplePhoto $p) => $p->fits($tier, $hotel->type, $setting));
        if ($fits->isEmpty()) {
            $fits = $candidates->filter(fn (SamplePhoto $p) => $tierOk($p) && $settingOk($p));
        }
        if ($fits->isEmpty()) {
            $fits = $candidates->filter($settingOk);
        }
        $fits = ($fits->isEmpty() ? $candidates : $fits)->values();

        return $fits[crc32($subject.'-'.$hotel->getKey()) % $fits->count()]->cover;
    }

    /**
     * @return Collection<int, SamplePhoto>
     */
    private function pool(): Collection
    {
        return $this->pool ??= SamplePhoto::with('cover')->get()->filter(fn (SamplePhoto $p) => $p->cover !== null)->values();
    }

    private function setting(?int $districtId): string
    {
        $this->settings ??= District::with('categories:id,slug')->get(['id'])->mapWithKeys(function (District $district) {
            $slugs = $district->categories->pluck('slug');

            return [$district->id => match (true) {
                $slugs->contains('hill-country') => 'hill',
                $slugs->contains('beach-coastal') => 'beach',
                default => 'inland',
            }];
        })->all();

        return $this->settings[$districtId] ?? 'inland';
    }
}
