<?php

namespace App\Domain\Organisation\Actions;

use App\Domain\Content\Actions\WriteTranslations;
use App\Domain\Media\Actions\SyncMediaUsage;
use App\Domain\Organisation\Enums\OfficeType;
use App\Domain\Organisation\Models\Leader;
use App\Domain\Organisation\Models\Office;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves an office with its languages (PLAN.md §7 Offices & structures).
 * The tree cannot loop and stays within Office::MAX_DEPTH levels.
 */
final class SaveOffice
{
    public function __construct(
        private readonly WriteTranslations $translations,
        private readonly SyncMediaUsage $usage,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Office $office, array $data, User $actor): Office
    {
        /** @var array<string, array<string, mixed>> $translations */
        $translations = (array) ($data['translations'] ?? []);
        WriteTranslations::requireName($translations);

        $type = OfficeType::tryFrom((string) ($data['type'] ?? ''));
        if ($type === null) {
            throw ValidationException::withMessages(['data.type' => __('organisation.validation.type_required')]);
        }

        $parentId = filled($data['parent_id'] ?? null) ? (string) $data['parent_id'] : null;
        $this->assertParentAllowed($office, $parentId);

        $headId = filled($data['head_leader_id'] ?? null) ? (string) $data['head_leader_id'] : null;
        if ($headId !== null && Leader::query()->whereKey($headId)->doesntExist()) {
            $headId = null;
        }

        [$latitude, $longitude] = $this->coordinates($data['latitude'] ?? null, $data['longitude'] ?? null);

        return DB::transaction(function () use ($office, $data, $translations, $actor, $type, $parentId, $headId, $latitude, $longitude): Office {
            $office->fill([
                'type' => $type,
                'parent_id' => $parentId,
                'head_leader_id' => $headId,
                'phone' => filled($data['phone'] ?? null) ? trim((string) $data['phone']) : null,
                'email' => WriteTranslations::email($data['email'] ?? null),
                'latitude' => $latitude,
                'longitude' => $longitude,
                'cover_image_id' => filled($data['cover_image_id'] ?? null) ? (int) $data['cover_image_id'] : null,
                'sort' => max(0, (int) ($data['sort'] ?? 0)),
            ]);
            if (! $office->exists) {
                $office->created_by = $actor->id;
            }
            $office->updated_by = $actor->id;
            $office->save();

            $this->translations->handle($office, $translations, ['summary', 'address', 'hours'], $actor->can('offices.manage'));
            $this->usage->handle($office, 'cover_image', [$office->cover_image_id]);

            return $office->refresh();
        });
    }

    /**
     * @return array{0: ?float, 1: ?float}
     */
    private function coordinates(mixed $latitude, mixed $longitude): array
    {
        if (blank($latitude) && blank($longitude)) {
            return [null, null];
        }
        if (! is_numeric($latitude) || ! is_numeric($longitude)
            || abs((float) $latitude) > 90 || abs((float) $longitude) > 180) {
            throw ValidationException::withMessages(['data.latitude' => __('organisation.validation.coordinates')]);
        }

        return [round((float) $latitude, 6), round((float) $longitude, 6)];
    }

    private function assertParentAllowed(Office $office, ?string $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        $parent = Office::query()->find($parentId);
        if ($parent === null) {
            throw ValidationException::withMessages(['data.parent_id' => __('organisation.validation.parent_missing')]);
        }

        $chain = [$parentId, ...$parent->ancestorIds()];
        if ($office->exists && in_array($office->id, $chain, true)) {
            throw ValidationException::withMessages(['data.parent_id' => __('organisation.validation.parent_loop')]);
        }

        $below = $office->exists ? $this->depthBelow($office) : 0;
        if (count($chain) + 1 + $below > Office::MAX_DEPTH) {
            throw ValidationException::withMessages(['data.parent_id' => __('organisation.validation.too_deep', ['max' => Office::MAX_DEPTH])]);
        }
    }

    /** Levels of sub-offices under this office (0 when it has none). */
    private function depthBelow(Office $office, int $level = 0): int
    {
        if ($level >= Office::MAX_DEPTH) {
            return $level;
        }

        $deepest = 0;
        foreach (Office::query()->where('parent_id', $office->id)->get() as $child) {
            $deepest = max($deepest, 1 + $this->depthBelow($child, $level + 1));
        }

        return $deepest;
    }
}
