<?php

namespace App\Domain\Documents\Actions;

use App\Domain\Content\Actions\WriteTranslations;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentTranslation;
use App\Domain\Media\Actions\SyncMediaUsage;
use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Models\Media;
use App\Domain\Organisation\Models\League;
use App\Domain\Organisation\Models\Office;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Saves a document with its languages and files (ADR-0026). A new file for
 * a language keeps the one it replaces as an earlier version.
 */
final class SaveDocument
{
    /** Most related documents shown on a document page. */
    public const MAX_RELATED = 8;

    public function __construct(
        private readonly WriteTranslations $translations,
        private readonly SyncMediaUsage $usage,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Document $document, array $data, User $actor): Document
    {
        /** @var array<string, array<string, mixed>> $translations */
        $translations = (array) ($data['translations'] ?? []);
        WriteTranslations::requireName($translations, 'title');

        $type = DocumentType::tryFrom((string) ($data['type'] ?? ''));
        if ($type === null) {
            throw ValidationException::withMessages(['data.type' => __('documents.validation.type')]);
        }

        $year = filled($data['year'] ?? null) ? (int) $data['year'] : null;
        if ($year !== null && ($year < 1900 || $year > 2100)) {
            throw ValidationException::withMessages(['data.year' => __('documents.validation.year')]);
        }

        $files = [];
        foreach ($translations as $locale => $values) {
            $fileId = filled($values['file_id'] ?? null) ? (int) $values['file_id'] : null;
            if ($fileId !== null && ! Media::query()->whereKey($fileId)->where('kind', MediaKind::Document->value)->exists()) {
                throw ValidationException::withMessages(["data.translations.{$locale}.file_id" => __('documents.validation.file')]);
            }
            $files[$locale] = $fileId;
        }

        return DB::transaction(function () use ($document, $data, $translations, $type, $year, $files, $actor): Document {
            $document->fill([
                'type' => $type,
                'year' => $year,
                'issued_on' => $this->date($data['issued_on'] ?? null),
                'deadline_at' => $type->isNotice() ? $this->time($data['deadline_at'] ?? null) : null,
                'office_id' => filled($data['office_id'] ?? null) && Office::query()->whereKey($data['office_id'])->exists() ? (string) $data['office_id'] : null,
                'league_id' => filled($data['league_id'] ?? null) && League::query()->whereKey($data['league_id'])->exists() ? (string) $data['league_id'] : null,
            ]);
            if (! $document->exists) {
                $document->created_by = $actor->id;
            }
            $document->updated_by = $actor->id;
            $document->save();

            $before = $document->translations()->get()->keyBy('locale');
            $this->translations->handle($document, $translations, ['summary'], $actor->can('documents.manage'), 'title');

            foreach ($document->translations()->get() as $translation) {
                /** @var DocumentTranslation $translation */
                if (! array_key_exists($translation->locale, $files)) {
                    continue;
                }
                $this->replaceFile($document, $translation, $before->get($translation->locale)?->getAttribute('file_id'), $files[$translation->locale],
                    $translations[$translation->locale]['version_note'] ?? null, $actor);
            }

            $related = array_values(array_diff(array_unique(array_map('strval', (array) ($data['related'] ?? []))), [$document->id]));
            $known = Document::query()->whereKey($related)->pluck('id')->map(fn (mixed $id): string => (string) $id)->all();
            $sync = [];
            foreach (array_slice(array_values(array_intersect($related, $known)), 0, self::MAX_RELATED) as $position => $id) {
                $sync[$id] = ['sort' => $position];
            }
            $document->related()->sync($sync);

            $this->syncUsage($document);

            return $document->refresh();
        });
    }

    /**
     * Makes an earlier version current again; the file it replaces is kept
     * as a version too, so nothing is lost.
     */
    public function restore(Document $document, int $versionId, User $actor): void
    {
        DB::transaction(function () use ($document, $versionId, $actor): void {
            $version = $document->versions()->whereKey($versionId)->firstOrFail();
            /** @var DocumentTranslation|null $translation */
            $translation = $document->translation($version->locale, fallback: false);
            if ($translation === null || $version->media_id === null) {
                throw ValidationException::withMessages(['version' => __('documents.validation.restore')]);
            }

            $this->replaceFile($document, $translation, $translation->file_id, $version->media_id,
                __('documents.restored_note', ['version' => $version->version], $translation->locale), $actor);
            $this->syncUsage($document);
        });
    }

    private function replaceFile(Document $document, DocumentTranslation $translation, mixed $previous, ?int $file, mixed $note, User $actor): void
    {
        $previous = $previous !== null ? (int) $previous : null;
        $note = filled($note) ? mb_substr(trim((string) $note), 0, 300) : null;

        if ($previous === $file) {
            if ($note !== null && $note !== $translation->version_note) {
                $translation->version_note = $note;
                $translation->save();
            }

            return;
        }

        if ($previous !== null) {
            $document->versions()->create([
                'locale' => $translation->locale,
                'media_id' => $previous,
                'version' => $translation->version,
                'note' => $translation->version_note,
                'replaced_at' => CarbonImmutable::now(),
                'replaced_by' => $actor->id,
            ]);
            $translation->version++;
        }

        $translation->file_id = $file;
        $translation->version_note = $file !== null ? $note : null;
        $translation->save();
    }

    private function syncUsage(Document $document): void
    {
        $ids = [
            ...$document->translations()->pluck('file_id')->filter()->all(),
            ...$document->versions()->pluck('media_id')->filter()->all(),
        ];
        $this->usage->handle($document, 'files', array_values(array_unique(array_map('intval', $ids))));
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        return $this->time($value)?->startOfDay();
    }

    private function time(mixed $value): ?CarbonImmutable
    {
        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value);
        } catch (Throwable) {
            throw ValidationException::withMessages(['data.issued_on' => __('organisation.validation.date')]);
        }
    }
}
