<?php

namespace App\Domain\Content\Concerns;

use App\Domain\Content\Enums\ContentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A language of a record that is either a draft or published, switched by
 * holders of the module's permission (ADR-0017): directory entries,
 * galleries, videos, events, documents and projects.
 *
 * @property ContentStatus $status
 * @property ?CarbonImmutable $published_at
 */
trait PublishedPerLanguage
{
    public static function bootPublishedPerLanguage(): void
    {
        static::saving(function (Model $translation): void {
            if ($translation->getAttribute('status') === ContentStatus::Published && $translation->getAttribute('published_at') === null) {
                $translation->setAttribute('published_at', CarbonImmutable::now());
            }
        });
    }

    public function isLive(): bool
    {
        return $this->status === ContentStatus::Published
            && $this->published_at !== null
            && $this->published_at->lessThanOrEqualTo(CarbonImmutable::now());
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Published->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', CarbonImmutable::now());
    }
}
