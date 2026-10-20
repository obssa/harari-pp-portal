<?php

namespace App\Domain\Projects\Actions;

use App\Domain\Content\Actions\WriteTranslations;
use App\Domain\Content\Models\Category;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\Statistic;
use App\Domain\Translation\Support\Locales;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Saves a statistic with its label, unit and source per language
 * (ADR-0027). A language left without a label is removed.
 */
final class SaveStatistic
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Statistic $statistic, array $data, User $actor): Statistic
    {
        /** @var array<string, array<string, mixed>> $translations */
        $translations = (array) ($data['translations'] ?? []);
        WriteTranslations::requireName($translations, 'label');

        if (! is_numeric($data['value'] ?? null) || abs((float) $data['value']) >= 1e17) {
            throw ValidationException::withMessages(['data.value' => __('projects.validation.value')]);
        }

        $source = trim((string) ($data['source_url'] ?? ''));
        if ($source !== '' && (! str_starts_with($source, 'https://') || filter_var($source, FILTER_VALIDATE_URL) === false)) {
            throw ValidationException::withMessages(['data.source_url' => __('organisation.validation.https')]);
        }

        try {
            $asOf = filled($data['as_of'] ?? null) ? CarbonImmutable::parse((string) $data['as_of'])->startOfDay() : null;
        } catch (Throwable) {
            throw ValidationException::withMessages(['data.as_of' => __('organisation.validation.date')]);
        }

        return DB::transaction(function () use ($statistic, $data, $translations, $actor, $source, $asOf): Statistic {
            $statistic->fill([
                'sector_id' => filled($data['sector_id'] ?? null) && Category::query()->whereKey($data['sector_id'])->where('type', Category::TYPE_SECTOR)->exists() ? (string) $data['sector_id'] : null,
                'project_id' => filled($data['project_id'] ?? null) && Project::query()->whereKey($data['project_id'])->exists() ? (string) $data['project_id'] : null,
                'value' => round((float) $data['value'], 2),
                'as_of' => $asOf,
                'source_url' => $source !== '' ? $source : null,
                'on_achievements' => (bool) ($data['on_achievements'] ?? true),
                'sort' => (int) ($data['sort'] ?? 0),
            ]);
            if (! $statistic->exists) {
                $statistic->created_by = $actor->id;
            }
            $statistic->updated_by = $actor->id;
            $statistic->save();

            $existing = $statistic->translations()->get()->keyBy('locale');
            foreach (Locales::all() as $locale) {
                if (! array_key_exists($locale, $translations)) {
                    continue;
                }
                $values = $translations[$locale];
                $label = trim((string) ($values['label'] ?? ''));
                if ($label === '') {
                    $existing->get($locale)?->delete();

                    continue;
                }
                $statistic->putTranslation($locale, [
                    'label' => mb_substr($label, 0, 200),
                    'unit' => filled($values['unit'] ?? null) ? mb_substr(trim((string) $values['unit']), 0, 40) : null,
                    'source' => filled($values['source'] ?? null) ? mb_substr(trim((string) $values['source']), 0, 300) : null,
                ]);
            }

            return $statistic->refresh();
        });
    }
}
