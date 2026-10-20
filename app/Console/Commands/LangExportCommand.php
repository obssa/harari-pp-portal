<?php

namespace App\Console\Commands;

use App\Domain\Translation\Support\Locales;
use App\Domain\Translation\Support\TranslationCatalog;
use App\Domain\Translation\Support\TranslationFile;
use Illuminate\Console\Command;

/**
 * Writes the live UI strings (shipped files plus admin edits) as flat JSON in
 * the same format as lang/*.json: every English key, empty when untranslated.
 *
 * On the server the files go to storage/app/translations for download; on a
 * developer machine --to-lang writes lang/*.json so admin edits can be
 * committed back to the repository.
 */
final class LangExportCommand extends Command
{
    protected $signature = 'lang:export
        {locales?* : Locales to export (default: all except English)}
        {--path= : Folder to write to (default: storage/app/translations)}
        {--to-lang : Write into lang/ (developer machines only)}
        {--files-only : Ignore strings edited in the admin}';

    protected $description = 'Export UI strings per locale as flat JSON';

    public function handle(TranslationCatalog $catalog): int
    {
        /** @var list<string> $requested */
        $requested = (array) $this->argument('locales');
        $locales = $requested !== [] ? $requested : array_values(array_diff(Locales::all(), ['en']));

        foreach ($locales as $locale) {
            if (! Locales::isSupported($locale)) {
                $this->components->error("Unknown locale: {$locale}");

                return self::INVALID;
            }
        }

        $folder = $this->option('to-lang')
            ? lang_path()
            : (string) ($this->option('path') ?: storage_path('app/translations'));
        if (! is_dir($folder)) {
            mkdir($folder, 0775, true);
        }

        foreach ($locales as $locale) {
            $file = rtrim($folder, '/\\').DIRECTORY_SEPARATOR."{$locale}.json";
            file_put_contents($file, self::encode($this->export($catalog, $locale)));
            $coverage = $catalog->coverage($locale, ! $this->option('files-only'));
            $this->components->twoColumnDetail($file, "{$coverage['translated']}/{$coverage['total']} translated");
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function export(TranslationCatalog $catalog, string $locale): array
    {
        $values = $catalog->values($locale, ! $this->option('files-only'));
        $out = [];
        foreach ($catalog->sourceKeys() as $key) {
            $out[$key] = $values[$key] ?? '';
        }

        return $out;
    }

    /**
     * Same layout as the files in the repository, so exports diff cleanly.
     *
     * @param  array<string, string>  $values
     */
    public static function encode(array $values): string
    {
        return TranslationFile::encode($values);
    }
}
