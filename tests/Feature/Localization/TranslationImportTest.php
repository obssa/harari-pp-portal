<?php

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Enums\SystemRole;
use App\Domain\Translation\Actions\ImportTranslations;
use App\Domain\Translation\Models\TranslationImport;
use App\Domain\Translation\Support\TranslationCatalog;
use App\Domain\Translation\Support\TranslationFile;
use App\Filament\Pages\ManageTranslations;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Livewire\livewire;

function importer(string $locale = 'har'): User
{
    $user = staff(SystemRole::Translator);
    $user->assignScopedRole(SystemRole::Translator, ScopeType::Language, $locale);

    return $user->refresh();
}

/**
 * @return list<list<string>>
 */
function readSheet(string $path): array
{
    $reader = new Reader;
    $reader->open($path);
    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = array_map(fn (mixed $cell): string => (string) $cell, $row->toArray());
        }
    }
    $reader->close();

    return $rows;
}

/**
 * @param  list<list<string>>  $rows
 */
function writeSheet(array $rows): string
{
    $path = storage_path('app/translations/test-'.uniqid().'.xlsx');
    @mkdir(dirname($path), 0775, true);
    $writer = new Writer;
    $writer->openToFile($path);
    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }
    $writer->close();

    return $path;
}

it('round-trips Harari through Excel without losing a string', function () {
    $translator = importer();
    $exported = app(TranslationFile::class)->export('har', 'xlsx');
    $rows = readSheet($exported);
    $total = count(app(TranslationCatalog::class)->sourceKeys());

    expect($rows[0])->toBe(['key', 'en', 'har', 'context'])->and(count($rows) - 1)->toBe($total);

    // A translator fills in two strings in Excel: one with a placeholder, one over two lines.
    foreach ($rows as $i => $row) {
        if ($row[0] === 'events.registration.closes') {
            $rows[$i][2] = 'Sample Harari :date';
        }
        if ($row[0] === 'nav.home') {
            $rows[$i][2] = "Sample Harari\nhome";
        }
    }
    $edited = writeSheet($rows);

    $import = app(ImportTranslations::class)->preview($edited, 'har.xlsx', 'har', $translator);
    expect($import->added)->toBe(2)->and($import->changed)->toBe(0)->and($import->problems)->toBe([]);

    app(ImportTranslations::class)->apply($import, $translator);
    expect(__('nav.home', [], 'har'))->toBe("Sample Harari\nhome")
        ->and(__('events.registration.closes', ['date' => 'X'], 'har'))->toBe('Sample Harari X');

    // Exporting again gives back exactly the edited sheet: nothing lost, nothing added.
    $again = readSheet(app(TranslationFile::class)->export('har', 'xlsx'));
    expect($again)->toBe($rows);

    app(ImportTranslations::class)->rollback($import->refresh(), $translator);
    app('translator')->setLoaded([]);
    expect(__('nav.home', [], 'har'))->not->toBe("Sample Harari\nhome")
        ->and($import->refresh()->status)->toBe(TranslationImport::ROLLED_BACK);
});

it('round-trips CSV and JSON files too', function () {
    $translator = importer();

    $csv = app(TranslationFile::class)->export('har', 'csv');
    expect((string) file_get_contents($csv))->toStartWith("\u{FEFF}key,en,har,context");
    $content = str_replace("\nnav.home,Home,,", "\nnav.home,Home,ሐረሪ ናሙና,", (string) file_get_contents($csv));
    file_put_contents($csv, $content);
    $import = app(ImportTranslations::class)->preview($csv, 'har.csv', 'har', $translator);
    expect(array_keys($import->changes ?? []))->toBe(['nav.home']);

    $json = app(TranslationFile::class)->export('har', 'json');
    $data = json_decode((string) file_get_contents($json), true);
    $data['nav.news'] = 'ሐረሪ ዜና ናሙና';
    file_put_contents($json, json_encode($data, JSON_UNESCAPED_UNICODE));
    expect(app(ImportTranslations::class)->preview($json, 'har.json', 'har', $translator)->changes)->toBe(['nav.news' => ['old' => '', 'new' => 'ሐረሪ ዜና ናሙና']]);
});

it('refuses unknown keys, changed placeholders and files that are not UTF-8', function () {
    $translator = importer();
    $path = writeSheet([['key', 'har'], ['no.such.key', 'x'], ['events.registration.closes', 'without the date'], ['nav.home', 'Sample']]);

    $import = app(ImportTranslations::class)->preview($path, 'har.xlsx', 'har', $translator);
    expect($import->added)->toBe(1)
        ->and(collect($import->problems)->pluck('problem', 'key')->all())->toBe(['no.such.key' => 'unknown_key', 'events.registration.closes' => 'placeholders']);

    $legacy = storage_path('app/translations/legacy.csv');
    file_put_contents($legacy, "key,har\nnav.home,".mb_convert_encoding('Café', 'Windows-1252', 'UTF-8')."\n");
    expect(fn () => app(ImportTranslations::class)->preview($legacy, 'har.csv', 'har', $translator))->toThrow(ValidationException::class)
        ->and(fn () => app(ImportTranslations::class)->preview(writeSheet([['key', 'am'], ['nav.home', 'x']]), 'x.xlsx', 'har', $translator))->toThrow(ValidationException::class)
        ->and(fn () => app(ImportTranslations::class)->preview($path, 'har.xlsx', 'am', $translator))->toThrow(HttpException::class);
});

it('leaves texts alone for empty cells unless asked to clear them', function () {
    $translator = importer();
    app(ImportTranslations::class)->apply(app(ImportTranslations::class)->preview(writeSheet([['key', 'har'], ['nav.home', 'Sample']]), 'a.xlsx', 'har', $translator), $translator);

    $empty = writeSheet([['key', 'har'], ['nav.home', '']]);
    expect(app(ImportTranslations::class)->preview($empty, 'b.xlsx', 'har', $translator)->changes)->toBe([])
        ->and(app(ImportTranslations::class)->preview($empty, 'b.xlsx', 'har', $translator, clearEmpty: true)->removed)->toBe(1);
});

it('imports from the translations page after showing the changes', function () {
    $translator = importer();
    $translator->givePermissionTo('translations.export');
    $this->actingAs($translator);
    $file = UploadedFile::fake()->createWithContent('har.json', json_encode(['nav.home' => 'Sample from the page'], JSON_UNESCAPED_UNICODE));

    livewire(ManageTranslations::class)
        ->callAction(TestAction::make('import')->table(), data: ['file' => $file])
        ->assertActionMounted('applyImport')
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect(__('nav.home', [], 'har'))->toBe('Sample from the page')
        ->and(TranslationImport::query()->firstOrFail()->status)->toBe(TranslationImport::APPLIED);

    livewire(ManageTranslations::class)->callAction(TestAction::make('export')->table(), data: ['format' => 'json'])->assertFileDownloaded();
});
