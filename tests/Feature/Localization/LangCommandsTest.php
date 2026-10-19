<?php

use App\Console\Commands\LangExportCommand;
use App\Domain\Translation\Models\UiTranslation;
use Illuminate\Support\Facades\File;

/**
 * Points lang_path() at a temporary copy of lang/ that a test may break.
 */
function useLangCopy(): string
{
    $copy = storage_path('framework/testing/lang-'.uniqid());
    File::copyDirectory(lang_path(), $copy);
    app()->useLangPath($copy);

    return $copy;
}

/**
 * @param  callable(array<string, string>): array<string, string>  $change
 */
function editLangFile(string $dir, string $locale, callable $change): void
{
    $path = "{$dir}/{$locale}.json";
    $values = $change(json_decode((string) file_get_contents($path), true));
    file_put_contents($path, LangExportCommand::encode($values));
}

afterEach(function () {
    File::deleteDirectory(storage_path('framework/testing'), preserve: true);
});

it('passes for the shipped translations and reports Harari coverage', function () {
    $this->artisan('lang:missing')
        ->expectsOutputToContain('Amharic and Afaan Oromo are complete')
        ->assertSuccessful();
});

it('fails when Amharic is missing a key', function () {
    $dir = useLangCopy();
    editLangFile($dir, 'am', fn (array $v) => array_diff_key($v, ['site.office' => true]));

    $this->artisan('lang:missing')->expectsOutputToContain('site.office')->assertFailed();
});

it('fails when an Afaan Oromo string is emptied', function () {
    $dir = useLangCopy();
    editLangFile($dir, 'om', fn (array $v) => [...$v, 'site.office' => '']);

    $this->artisan('lang:missing')->assertFailed();
});

it('fails when a translation drops a placeholder, even in Harari', function () {
    $dir = useLangCopy();
    editLangFile($dir, 'har', fn (array $v) => [...$v, 'access.demo_name' => 'no role placeholder']);

    $this->artisan('lang:missing')->expectsOutputToContain('access.demo_name')->assertFailed();
});

it('does not fail for missing Harari strings', function () {
    $dir = useLangCopy();
    editLangFile($dir, 'har', fn (array $v) => array_map(fn () => '', $v));

    $this->artisan('lang:missing')->assertSuccessful();
});

it('fails when a locale keeps a key English no longer has', function () {
    $dir = useLangCopy();
    editLangFile($dir, 'am', fn (array $v) => [...$v, 'old.removed_key' => 'ቀድሞ']);

    $this->artisan('lang:missing')->expectsOutputToContain('old.removed_key')->assertFailed();
});

it('fails when code uses a key English does not define', function () {
    $dir = useLangCopy();
    foreach (['en', 'am', 'om', 'har'] as $locale) {
        editLangFile($dir, $locale, fn (array $v) => array_diff_key($v, ['nav.language' => true]));
    }

    $this->artisan('lang:missing')->expectsOutputToContain('nav.language')->assertFailed();
});

it('counts strings edited in the admin unless asked to check files only', function () {
    $dir = useLangCopy();
    editLangFile($dir, 'am', fn (array $v) => [...$v, 'site.office' => '']);
    UiTranslation::query()->create(['key' => 'site.office', 'text' => ['am' => 'ከአስተዳደሩ']]);

    $this->artisan('lang:missing')->assertSuccessful();
    $this->artisan('lang:missing', ['--files-only' => true])->assertFailed();
});

it('prints a machine-readable report', function () {
    $this->artisan('lang:missing', ['--json' => true])
        ->expectsOutputToContain('"undefined_in_english": []')
        ->assertSuccessful();
});

it('exports each language in exactly the format of the repository files', function () {
    $out = storage_path('framework/testing/export');

    $this->artisan('lang:export', ['--path' => $out, '--files-only' => true])->assertSuccessful();

    foreach (['am', 'om', 'har'] as $locale) {
        expect(file_get_contents("{$out}/{$locale}.json"))->toBe(file_get_contents(lang_path("{$locale}.json")));
    }
});

it('includes admin edits in the export and lists every English key', function () {
    $out = storage_path('framework/testing/export');
    UiTranslation::query()->create(['key' => 'site.office', 'text' => ['har' => 'Harari office']]);

    $this->artisan('lang:export', ['locales' => ['har'], '--path' => $out])->assertSuccessful();

    $har = json_decode((string) file_get_contents("{$out}/har.json"), true);
    expect($har['site.office'])->toBe('Harari office')
        ->and(array_keys($har))->toBe(array_keys(json_decode((string) file_get_contents(lang_path('en.json')), true)))
        ->and($har['site.name'])->toBe('');
});

it('writes into lang/ when asked, so admin edits can be committed', function () {
    $dir = useLangCopy();
    UiTranslation::query()->create(['key' => 'site.office', 'text' => ['har' => 'Harari office']]);

    $this->artisan('lang:export', ['locales' => ['har'], '--to-lang' => true])->assertSuccessful();

    expect(json_decode((string) file_get_contents("{$dir}/har.json"), true)['site.office'])->toBe('Harari office');
});

it('refuses unknown languages', function () {
    $this->artisan('lang:export', ['locales' => ['fr']])->assertExitCode(2);
});
