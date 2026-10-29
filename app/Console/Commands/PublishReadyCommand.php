<?php

namespace App\Console\Commands;

use App\Enums\PublishStatus;
use App\Models\Hotel;
use App\Models\Place;
use App\Services\Admin\PublishingService;
use Illuminate\Console\Command;

/**
 * Publish every draft that is finished, using the same rules as the admin review queue.
 * A place is finished when it passes the publishing rules (location + category) and has a
 * description or a photo; OpenStreetMap suggestions without either stay in the review queue.
 * Rejected (inactive) records are never published.
 */
class PublishReadyCommand extends Command
{
    protected $signature = 'lg:publish-ready
        {--places : Publish finished places (and their freely licensed photos)}
        {--hotels : Publish hotels that have a location and a town}
        {--dry-run : Only count what would be published}';

    protected $description = 'Publish finished draft places (with their photos) and hotels, using the review-queue rules';

    public function handle(PublishingService $publishing): int
    {
        $places = $this->option('places') || ! $this->option('hotels');
        $hotels = $this->option('hotels') || ! $this->option('places');
        $dry = (bool) $this->option('dry-run');

        if ($places) {
            $query = Place::query()
                ->where('status', PublishStatus::Draft->value)
                ->where('is_active', true)
                ->where(fn ($q) => $q->where(fn ($d) => $d->whereNotNull('description')->where('description', '!=', ''))->orWhereHas('allMedia'));

            $this->report('places', $query->count(), $dry ? 0 : $this->publishAll($publishing, $query->lazyById(200), true));
        }

        if ($hotels) {
            $query = Hotel::query()->where('status', PublishStatus::Draft->value)->where('is_active', true);
            $this->report('hotels', $query->count(), $dry ? 0 : $this->publishAll($publishing, $query->lazyById(500), false));
        }

        return self::SUCCESS;
    }

    /**
     * @param  iterable<Place|Hotel>  $models
     */
    private function publishAll(PublishingService $publishing, iterable $models, bool $withPhotos): int
    {
        $done = 0;
        foreach ($models as $model) {
            if ($publishing->publish($model, $withPhotos) === []) {
                $done++;
            }
        }

        return $done;
    }

    private function report(string $what, int $candidates, int $published): void
    {
        $this->option('dry-run')
            ? $this->components->info("{$candidates} {$what} would be checked for publishing.")
            : $this->components->info("Published {$published} of {$candidates} finished draft {$what}.");
    }
}
