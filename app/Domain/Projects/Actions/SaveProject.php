<?php

namespace App\Domain\Projects\Actions;

use App\Domain\Content\Actions\WriteTranslations;
use App\Domain\Content\Models\Category;
use App\Domain\Media\Actions\SyncMediaUsage;
use App\Domain\Media\Models\Gallery;
use App\Domain\Organisation\Models\Office;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Saves a project with its languages (ADR-0027).
 */
final class SaveProject
{
    public function __construct(
        private readonly WriteTranslations $translations,
        private readonly SyncMediaUsage $usage,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Project $project, array $data, User $actor): Project
    {
        /** @var array<string, array<string, mixed>> $translations */
        $translations = (array) ($data['translations'] ?? []);
        WriteTranslations::requireName($translations, 'title');

        $status = ProjectStatus::tryFrom((string) ($data['status'] ?? ProjectStatus::Planned->value)) ?? ProjectStatus::Planned;
        $starts = $this->date($data['starts_on'] ?? null);
        $ends = $this->date($data['ends_on'] ?? null);
        if ($starts !== null && $ends !== null && $ends->lessThan($starts)) {
            throw ValidationException::withMessages(['data.ends_on' => __('projects.validation.dates')]);
        }

        $progress = filled($data['progress'] ?? null) ? (int) $data['progress'] : null;
        if ($progress !== null && ($progress < 0 || $progress > 100)) {
            throw ValidationException::withMessages(['data.progress' => __('projects.validation.progress')]);
        }
        // A completed project is done.
        if ($status === ProjectStatus::Completed) {
            $progress = 100;
        }

        $budget = filled($data['budget'] ?? null) ? round((float) $data['budget'], 2) : null;
        if ($budget !== null && $budget < 0) {
            throw ValidationException::withMessages(['data.budget' => __('projects.validation.budget')]);
        }

        [$latitude, $longitude] = $this->coordinates($data['latitude'] ?? null, $data['longitude'] ?? null);

        return DB::transaction(function () use ($project, $data, $translations, $actor, $status, $starts, $ends, $progress, $budget, $latitude, $longitude): Project {
            $project->fill([
                'sector_id' => filled($data['sector_id'] ?? null) && Category::query()->whereKey($data['sector_id'])->where('type', Category::TYPE_SECTOR)->exists() ? (string) $data['sector_id'] : null,
                'office_id' => filled($data['office_id'] ?? null) && Office::query()->whereKey($data['office_id'])->exists() ? (string) $data['office_id'] : null,
                'status' => $status,
                'starts_on' => $starts,
                'ends_on' => $ends,
                'budget' => $budget,
                'budget_public' => $budget !== null && (bool) ($data['budget_public'] ?? false),
                'progress' => $progress,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'cover_id' => filled($data['cover_id'] ?? null) ? (int) $data['cover_id'] : null,
                'gallery_id' => filled($data['gallery_id'] ?? null) && Gallery::query()->whereKey($data['gallery_id'])->exists() ? (string) $data['gallery_id'] : null,
                'featured' => (bool) ($data['featured'] ?? false),
            ]);
            if (! $project->exists) {
                $project->created_by = $actor->id;
            }
            $project->updated_by = $actor->id;
            $project->save();

            $this->translations->handle($project, $translations, ['summary', 'location'], $actor->can('projects.manage'), 'title');
            $this->usage->handle($project, 'cover', [$project->cover_id]);

            return $project->refresh();
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
        if (! is_numeric($latitude) || ! is_numeric($longitude) || abs((float) $latitude) > 90 || abs((float) $longitude) > 180) {
            throw ValidationException::withMessages(['data.latitude' => __('organisation.validation.coordinates')]);
        }

        return [round((float) $latitude, 6), round((float) $longitude, 6)];
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value)->startOfDay();
        } catch (Throwable) {
            throw ValidationException::withMessages(['data.starts_on' => __('organisation.validation.date')]);
        }
    }
}
