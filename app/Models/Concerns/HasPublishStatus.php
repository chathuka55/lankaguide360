<?php

namespace App\Models\Concerns;

use App\Enums\PublishStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Draft/published workflow for places and hotels: imported rows start as drafts and
 * only published, active rows reach the public site.
 *
 * @mixin Model
 */
trait HasPublishStatus
{
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where($this->qualifyColumn('status'), PublishStatus::Published)
            ->where($this->qualifyColumn('is_active'), true);
    }

    #[Scope]
    protected function drafts(Builder $query): void
    {
        $query->where($this->qualifyColumn('status'), PublishStatus::Draft);
    }

    public function isPublished(): bool
    {
        return $this->status === PublishStatus::Published;
    }
}
