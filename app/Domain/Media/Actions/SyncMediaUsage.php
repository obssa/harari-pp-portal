<?php

namespace App\Domain\Media\Actions;

use App\Domain\Media\Models\MediaUsage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Records which library files a model uses in one place, e.g. a post's
 * "featured_image" or "body" (PLAN.md §7 usage tracking). Content models call
 * this whenever they save; files still in use cannot be deleted.
 */
final class SyncMediaUsage
{
    /**
     * @param  iterable<int|string|null>  $mediaIds
     */
    public function handle(Model $usable, string $context, iterable $mediaIds): void
    {
        $ids = collect($mediaIds)->filter()->map(fn ($id): int => (int) $id)->unique()->values();

        DB::transaction(function () use ($usable, $context, $ids): void {
            MediaUsage::query()
                ->where('usable_type', $usable->getMorphClass())
                ->where('usable_id', $usable->getKey())
                ->where('context', $context)
                ->whereNotIn('media_id', $ids->all())
                ->delete();

            foreach ($ids as $id) {
                MediaUsage::query()->firstOrCreate([
                    'media_id' => $id,
                    'usable_type' => $usable->getMorphClass(),
                    'usable_id' => $usable->getKey(),
                    'context' => $context,
                ]);
            }
        });
    }

    /** Forgets every use by this model, e.g. when it is deleted for good. */
    public function forget(Model $usable): void
    {
        MediaUsage::query()
            ->where('usable_type', $usable->getMorphClass())
            ->where('usable_id', $usable->getKey())
            ->delete();
    }
}
