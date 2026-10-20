<?php

namespace App\Domain\Translation\Actions;

use App\Domain\Translation\Models\TranslationImport;
use App\Domain\Translation\Models\UiTranslation;
use App\Domain\Translation\Support\TranslationCatalog;
use App\Domain\Translation\Support\TranslationFile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Brings a translation file in (ADR-0033) in two steps: the preview lists
 * what would be added or changed and what is refused (unknown keys,
 * changed placeholders, texts too long); applying writes the changes and
 * keeps the earlier values, so the whole import can be rolled back. Empty
 * cells leave a text as it is unless the importer asks to clear them.
 */
final class ImportTranslations
{
    public function __construct(
        private readonly TranslationFile $files,
        private readonly TranslationCatalog $catalog,
        private readonly EditUiTranslation $edit,
    ) {}

    public function preview(string $path, string $fileName, string $locale, User $actor, bool $clearEmpty = false): TranslationImport
    {
        abort_unless($actor->can('translations.import') && $actor->canEditTranslationsFor($locale), 403);

        $incoming = $this->files->read($path, $fileName, $locale);
        $english = $this->catalog->values('en');
        $current = $this->catalog->values($locale);

        $changes = [];
        $problems = [];
        $added = $changed = $removed = 0;
        foreach ($incoming as $key => $text) {
            $text = trim($text);
            if (! array_key_exists($key, $english)) {
                $problems[] = ['key' => $key, 'problem' => 'unknown_key'];

                continue;
            }
            $old = (string) ($current[$key] ?? '');
            if ($text === '' && ! $clearEmpty) {
                continue;
            }
            if ($text === $old) {
                continue;
            }
            if (mb_strlen($text) > 5000) {
                $problems[] = ['key' => $key, 'problem' => 'too_long'];

                continue;
            }
            if ($text !== '' && $locale !== 'en' && ! TranslationCatalog::placeholdersMatch($english[$key], $text)) {
                $problems[] = ['key' => $key, 'problem' => 'placeholders'];

                continue;
            }

            $changes[$key] = ['old' => $old, 'new' => $text];
            match (true) {
                $old === '' => $added++,
                $text === '' => $removed++,
                default => $changed++,
            };
        }

        return TranslationImport::query()->create([
            'locale' => $locale,
            'file_name' => mb_substr(basename($fileName), 0, 190),
            'format' => strtolower(pathinfo($fileName, PATHINFO_EXTENSION)),
            'status' => TranslationImport::PENDING,
            'added' => $added,
            'changed' => $changed,
            'removed' => $removed,
            'changes' => $changes,
            'problems' => $problems,
            'user_id' => $actor->id,
        ]);
    }

    public function apply(TranslationImport $import, User $actor): void
    {
        abort_unless($actor->can('translations.import') && $actor->canEditTranslationsFor($import->locale), 403);
        if ($import->status !== TranslationImport::PENDING) {
            throw ValidationException::withMessages(['import' => __('translations.import.validation.not_pending')]);
        }

        DB::transaction(function () use ($import, $actor): void {
            $snapshot = [];
            $lines = UiTranslation::query()->where('group', '*')->whereIn('key', array_keys($import->changes ?? []))->get()->keyBy('key');
            foreach ($import->changes ?? [] as $key => $change) {
                $snapshot[$key] = $lines->get($key)?->text[$import->locale] ?? null;
                $this->edit->handle($key, $import->locale, $change['new'], $actor, 'import');
            }

            $import->forceFill(['status' => TranslationImport::APPLIED, 'snapshot' => $snapshot, 'applied_at' => CarbonImmutable::now()])->save();
        });
    }

    /** Puts back what the admin had before the import, key by key. */
    public function rollback(TranslationImport $import, User $actor): void
    {
        abort_unless($actor->can('translations.import') && $actor->canEditTranslationsFor($import->locale), 403);
        if ($import->status !== TranslationImport::APPLIED) {
            throw ValidationException::withMessages(['import' => __('translations.import.validation.not_applied')]);
        }

        DB::transaction(function () use ($import, $actor): void {
            foreach ($import->snapshot ?? [] as $key => $previous) {
                $this->edit->handle((string) $key, $import->locale, $previous ?? '', $actor, 'import');
            }
            $import->forceFill(['status' => TranslationImport::ROLLED_BACK, 'rolled_back_at' => CarbonImmutable::now()])->save();
        });
    }

    public function discard(TranslationImport $import): void
    {
        if ($import->status === TranslationImport::PENDING) {
            $import->forceFill(['status' => TranslationImport::DISCARDED])->save();
        }
    }
}
