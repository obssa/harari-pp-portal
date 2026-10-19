<?php

namespace App\Console\Commands;

use App\Domain\Translation\Support\Locales;
use App\Domain\Translation\Support\TranslationCatalog;
use Illuminate\Console\Command;

/**
 * Translation health check (PLAN.md §6, run in CI):
 *  - fails when Amharic or Afaan Oromo miss a key or break a placeholder;
 *  - fails when code uses a key that English does not define;
 *  - fails when a locale file keeps keys English no longer has;
 *  - reports Harari coverage without failing (it is filled in by hand).
 */
final class LangMissingCommand extends Command
{
    /** Locales that must be complete before every commit. */
    public const REQUIRED = ['am', 'om'];

    protected $signature = 'lang:missing
        {--files-only : Ignore strings edited in the admin (used in CI, where there is no database)}
        {--json : Print a machine-readable report}';

    protected $description = 'Report missing or broken UI translations (fails for am/om)';

    public function handle(TranslationCatalog $catalog): int
    {
        $withDatabase = ! $this->option('files-only');
        $used = $catalog->keysUsedInCode([app_path(), resource_path('views'), base_path('routes')]);
        $undefined = array_values(array_diff(array_keys($used), $catalog->sourceKeys()));

        $report = ['undefined_in_english' => $undefined, 'locales' => []];
        $failed = $undefined !== [];

        foreach (Locales::all() as $locale) {
            if ($locale === 'en') {
                continue;
            }
            $missing = $catalog->missing($locale, $withDatabase);
            $problems = $catalog->placeholderProblems($locale, $withDatabase);
            $orphans = $catalog->orphans($locale);
            $required = in_array($locale, self::REQUIRED, true);

            $report['locales'][$locale] = [
                'required' => $required,
                'coverage' => $catalog->coverage($locale, $withDatabase),
                'missing' => $missing,
                'placeholder_problems' => $problems,
                'orphans' => $orphans,
            ];

            $failed = $failed || $problems !== [] || $orphans !== [] || ($required && $missing !== []);
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $failed ? self::FAILURE : self::SUCCESS;
        }

        $this->table(
            ['Locale', 'Translated', 'Missing', 'Placeholder problems', 'Stray keys', 'Required'],
            collect($report['locales'])->map(fn (array $r, string $locale): array => [
                $locale,
                "{$r['coverage']['translated']}/{$r['coverage']['total']} ({$r['coverage']['percent']}%)",
                count($r['missing']),
                count($r['placeholder_problems']),
                count($r['orphans']),
                $r['required'] ? 'yes' : 'no (reported only)',
            ])->values()->all(),
        );

        foreach ($report['locales'] as $locale => $r) {
            $this->listKeys("Missing in {$locale}", $r['required'] ? $r['missing'] : [], 'error');
            $this->listKeys("Placeholder or plural problems in {$locale}", $r['placeholder_problems'], 'error');
            $this->listKeys("Keys in {$locale}.json that en.json no longer has", $r['orphans'], 'error');
        }
        $this->listKeys('Keys used in code but missing from lang/en.json', $undefined, 'error');

        if ($failed) {
            $this->components->error('Translations are incomplete. Fix the keys above.');

            return self::FAILURE;
        }

        $this->components->info('Amharic and Afaan Oromo are complete; Harari coverage is reported above.');

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $keys
     */
    private function listKeys(string $title, array $keys, string $style): void
    {
        if ($keys === []) {
            return;
        }

        $this->newLine();
        $this->line("<{$style}>{$title} (".count($keys).'):</>');
        foreach (array_slice($keys, 0, 50) as $key) {
            $this->line("  - {$key}");
        }
        if (count($keys) > 50) {
            $this->line('  … and '.(count($keys) - 50).' more');
        }
    }
}
