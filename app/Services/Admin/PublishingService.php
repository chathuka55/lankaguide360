<?php

namespace App\Services\Admin;

use App\Enums\MediaStatus;
use App\Enums\PublishStatus;
use App\Models\Hotel;
use App\Models\Place;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Publish / unpublish / reject rules for places and hotels, shared by the review queue,
 * bulk actions and edit forms. A place needs coordinates and a category before visitors
 * (and the itinerary engine) can use it; a hotel needs coordinates.
 */
class PublishingService
{
    /**
     * Reasons the record can't be published yet (empty = ready).
     *
     * @return array<int, string>
     */
    public function problems(Place|Hotel $model): array
    {
        $problems = [];

        if ($model->lat === null || $model->lng === null) {
            $problems[] = 'no map coordinates';
        }

        if ($model instanceof Place && ! $model->categories()->exists()) {
            $problems[] = 'no category';
        }

        if ($model instanceof Hotel && blank($model->town)) {
            $problems[] = 'no town';
        }

        return $problems;
    }

    /**
     * Publish if ready. With $withPhotos, its draft photos are published too.
     *
     * @return array<int, string> problems that blocked publishing (empty on success)
     */
    public function publish(Place|Hotel $model, bool $withPhotos = false): array
    {
        $problems = $this->problems($model);

        if ($problems !== []) {
            return $problems;
        }

        DB::transaction(function () use ($model, $withPhotos) {
            $model->forceFill(['status' => PublishStatus::Published, 'is_active' => true])->save();

            if ($withPhotos) {
                $model->media()->where('status', MediaStatus::Draft->value)->update(['status' => MediaStatus::Published->value]);
            }
        });

        return [];
    }

    public function unpublish(Place|Hotel $model): void
    {
        $model->forceFill(['status' => PublishStatus::Draft])->save();
    }

    /**
     * Hide an imported record from the queue without deleting it, so importers that
     * upsert by osm_id don't suggest it again. Restore with restore().
     */
    public function reject(Place|Hotel $model): void
    {
        $model->forceFill(['status' => PublishStatus::Draft, 'is_active' => false])->save();
    }

    public function restore(Place|Hotel $model): void
    {
        $model->forceFill(['is_active' => true])->save();
    }

    /**
     * Apply a bulk action and return a human summary for the flash message.
     *
     * @param  iterable<Place|Hotel>  $models
     * @return array{done: int, blocked: array<int, string>}
     */
    public function bulk(string $action, iterable $models): array
    {
        $done = 0;
        $blocked = [];

        foreach ($models as $model) {
            switch ($action) {
                case 'publish':
                case 'publish_with_photos':
                    $problems = $this->publish($model, $action === 'publish_with_photos');
                    $problems === [] ? $done++ : $blocked[] = $this->label($model).': '.implode(', ', $problems);
                    break;
                case 'unpublish':
                    $this->unpublish($model);
                    $done++;
                    break;
                case 'reject':
                    $this->reject($model);
                    $done++;
                    break;
                case 'restore':
                    $this->restore($model);
                    $done++;
                    break;
            }
        }

        return ['done' => $done, 'blocked' => $blocked];
    }

    private function label(Model $model): string
    {
        return (string) ($model->name ?? '#'.$model->getKey());
    }
}
