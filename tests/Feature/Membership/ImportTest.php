<?php

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Enums\SystemRole;
use App\Domain\Membership\Actions\ImportMembers;
use App\Domain\Membership\Actions\SaveMember;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberImport;
use App\Domain\Membership\Models\MembershipApplication;
use App\Domain\Membership\Models\PersonNote;
use App\Domain\Membership\Support\ImportColumns;
use App\Domain\Organisation\Actions\SaveOffice;
use App\Domain\Organisation\Models\Office;
use App\Filament\Resources\MemberImports\MemberImportResource;
use App\Filament\Resources\MemberImports\Pages\ListMemberImports;
use App\Filament\Resources\MemberImports\Pages\ViewMemberImport;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

use function Pest\Livewire\livewire;

function rollOffice(string $type, string $name, ?Office $parent = null): Office
{
    return app(SaveOffice::class)->handle(new Office, ['type' => $type, 'parent_id' => $parent?->id, 'translations' => ['en' => ['name' => $name, 'published' => true]]], staff(SystemRole::Publisher));
}

/** A roll as a CSV file in the temp folder. */
function rollCsv(array $rows, string $separator = ','): string
{
    $path = sys_get_temp_dir().'/roll-'.bin2hex(random_bytes(6)).'.csv';
    $out = fopen($path, 'wb');
    foreach ($rows as $row) {
        fputcsv($out, $row, $separator, escape: '');
    }
    fclose($out);

    return $path;
}

const ROLL_HEADINGS = ['No', 'Full name', 'Sex', 'Date of birth', 'ID number', 'Phone', 'Woreda', 'Kebele', 'Date joined'];

/** @return array{id_type: string, calendar: string, joined_on: string, mapping: array<string, int|null>} */
function rollSettings(array $overrides = []): array
{
    return ['id_type' => 'kebele', 'calendar' => 'gregorian', 'joined_on' => '2020-01-01', 'mapping' => ImportColumns::guess(ROLL_HEADINGS), ...$overrides];
}

it('guesses the columns from English, Amharic and Afaan Oromo headings', function () {
    expect(ImportColumns::guess(ROLL_HEADINGS))->toMatchArray(['old_number' => 0, 'name' => 1, 'sex' => 2, 'birth_date' => 3, 'id_number' => 4, 'phone' => 5, 'woreda' => 6, 'kebele' => 7, 'joined_on' => 8, 'email' => null])
        ->and(ImportColumns::guess(['ሙሉ ስም', 'ፆታ', 'ስልክ ቁጥር', 'ወረዳ']))->toMatchArray(['name' => 0, 'sex' => 1, 'phone' => 2, 'woreda' => 3])
        ->and(ImportColumns::guess(['Maqaa guutuu', 'Saala', 'Bilbila', 'Aanaa']))->toMatchArray(['name' => 0, 'sex' => 1, 'phone' => 2, 'woreda' => 3]);
});

it('checks a roll without adding anyone: problems, duplicates and office names', function () {
    CarbonImmutable::setTestNow('2026-10-21 10:00');
    $north = rollOffice('woreda', 'Sample Woreda North');
    $south = rollOffice('woreda', 'Sample Woreda South');
    $northKebele = rollOffice('kebele', 'Kebele 01', $north);
    rollOffice('kebele', 'Kebele 01', $south);
    app(SaveMember::class)->handle(new Member, ['name' => 'Sample Existing Member', 'sex' => 'male', 'id_type' => 'kebele', 'id_number' => 'KB111111', 'joined_on' => '2024-01-01', 'woreda_id' => $north->id], staff(SystemRole::Admin));

    $path = rollCsv([
        ROLL_HEADINGS,
        ['A-1', 'Sample Roll One', 'M', '14/03/1990', 'KB200001', '0911000001', 'Sample North', 'Kebele 01', '2019-05-01'],
        ['A-2', 'Sample Roll Two', 'ሴት', '1985', '', '0911000001', 'sample woreda north', '', ''],
        ['A-3', 'Sample Roll Three', 'X', '1990-01-01', '', '12345', 'Unknown Woreda', '', ''],
        ['A-4', 'Sample Roll Four', 'F', '01/01/1980', 'KB111111', '', 'Sample North', '', ''],
        ['A-5', 'Sample Roll Five', 'F', '1990-01-01', 'KB200001', '', 'Sample North', '', ''],
        ['A-6', 'Sample Roll Six', 'F', '2015-01-01', '', '', 'Sample North', '', ''],
        ['', '', '', '', '', '', '', '', ''],
    ]);

    $import = app(ImportMembers::class)->check($path, 'sample-roll.csv', rollSettings(), staff(SystemRole::Admin));
    @unlink($path);

    expect($import->status)->toBe(MemberImport::CHECKED)
        ->and([$import->total, $import->ready, $import->with_problems, $import->duplicates])->toBe([6, 2, 2, 2])
        ->and(Member::query()->count())->toBe(1)
        ->and(collect($import->problems)->map(fn (array $p): string => "{$p['row']}:{$p['field']}:{$p['problem']}")->all())->toBe([
            '3:phone:shared_phone',
            '4:sex:sex', '4:phone:phone', '4:woreda:office',
            '5:id_number:duplicate_member',
            '6:id_number:duplicate_row',
            '7:birth_date:age',
        ]);

    $rows = collect($import->rows)->keyBy('row');
    expect($rows[2])->toMatchArray(['name' => 'Sample Roll One', 'sex' => 'male', 'birth_date' => '1990-03-14', 'birth_year' => 1990, 'woreda_id' => $north->id,
        'kebele_id' => $northKebele->id, 'phone' => '+251911000001', 'joined_on' => '2019-05-01', 'old_number' => 'A-1',
        'id_hash' => MembershipApplication::hash('kebele:KB200001')])
        ->and($rows[3])->toMatchArray(['sex' => 'female', 'birth_date' => null, 'birth_year' => 1985, 'joined_on' => '2020-01-01']);

    expect(json_encode((array) DB::table('member_imports')->first()))->not->toContain('Sample Roll')->not->toContain('KB200001');
    CarbonImmutable::setTestNow();
});

it('reads Excel files, semicolon CSV files and dates in the Ethiopian calendar', function () {
    rollOffice('woreda', 'Sample Woreda North');
    $xlsx = sys_get_temp_dir().'/roll-'.bin2hex(random_bytes(6)).'.xlsx';
    $writer = new XlsxWriter;
    $writer->openToFile($xlsx);
    $writer->addRow(Row::fromValues(['ሙሉ ስም', 'ፆታ', 'የትውልድ ዓመት', 'ወረዳ', 'የተቀላቀለበት ቀን']));
    $writer->addRow(Row::fromValues(['የናሙና አባል', 'ወንድ', 1980, 'Sample North', new DateTimeImmutable('2021-02-03')]));
    $writer->close();

    $fromExcel = app(ImportMembers::class)->check($xlsx, 'roll.xlsx', rollSettings(['id_type' => 'none', 'mapping' => ImportColumns::guess(['ሙሉ ስም', 'ፆታ', 'የትውልድ ዓመት', 'ወረዳ', 'የተቀላቀለበት ቀን'])]), staff(SystemRole::Admin));
    @unlink($xlsx);
    expect($fromExcel->problems)->toBe([])->and($fromExcel->rows[0])->toMatchArray(['name' => 'የናሙና አባል', 'sex' => 'male', 'birth_year' => 1980, 'joined_on' => '2021-02-03']);

    $csv = rollCsv([['Name', 'Sex', 'Year of birth', 'Woreda', 'Joined'], ['Sample Ethiopian Dates', 'M', '1980', 'Sample North', '1/1/2015']], ';');
    $ethiopian = app(ImportMembers::class)->check($csv, 'roll.csv', rollSettings(['id_type' => 'none', 'calendar' => 'ethiopian', 'mapping' => ImportColumns::guess(['Name', 'Sex', 'Year of birth', 'Woreda', 'Joined'])]), staff(SystemRole::Admin));
    @unlink($csv);
    expect($ethiopian->rows[0])->toMatchArray(['birth_year' => 1988, 'joined_on' => '2022-09-11']);

    expect(fn () => app(ImportMembers::class)->check(rollCsv([['Name']]), 'roll.csv', rollSettings(['id_type' => 'none', 'mapping' => ['name' => 0]]), staff(SystemRole::Admin)))
        ->toThrow(ValidationException::class, __('members.import.validation.empty'))
        ->and(fn () => app(ImportMembers::class)->check(rollCsv([['Name'], ['x']]), 'roll.txt', rollSettings(['id_type' => 'none', 'mapping' => ['name' => 0]]), staff(SystemRole::Admin)))
        ->toThrow(ValidationException::class, __('members.import.validation.type'))
        ->and(fn () => app(ImportMembers::class)->check(rollCsv([['Name'], ['x']]), 'roll.csv', rollSettings(['id_type' => 'none', 'mapping' => ['name' => null]]), staff(SystemRole::Admin)))
        ->toThrow(ValidationException::class);
});

it('adds the ready rows as members, then rolls the import back without freeing their numbers', function () {
    $north = rollOffice('woreda', 'Sample Woreda North');
    $admin = staff(SystemRole::Admin);
    $path = rollCsv([ROLL_HEADINGS,
        ['A-1', 'Sample Roll One', 'M', '1990-03-14', 'KB300001', '0911000011', 'Sample North', '', '2019-05-01'],
        ['A-2', 'Sample Roll Two', 'F', '1991-03-14', 'KB300002', '0911000012', 'Sample North', '', ''],
    ]);
    $import = app(ImportMembers::class)->check($path, 'sample-roll.csv', rollSettings(), $admin);
    @unlink($path);

    app(ImportMembers::class)->apply($import, $admin);
    $import->refresh();

    $members = Member::query()->orderBy('member_no')->get();
    expect($import->status)->toBe(MemberImport::APPLIED)
        ->and($import->created_count)->toBe(2)
        ->and($import->rows)->toBeNull()
        ->and($members->pluck('member_no')->all())->toBe(['HPP-000001', 'HPP-000002'])
        ->and($members->pluck('source')->unique()->all())->toBe([Member::SOURCE_IMPORT])
        ->and($members[0]->import_id)->toBe($import->id)
        ->and($members[0]->notes()->sole()->body)->toContain('A-1')->toContain('sample-roll.csv')
        ->and(fn () => app(ImportMembers::class)->apply($import, $admin))->toThrow(ValidationException::class);

    expect(app(ImportMembers::class)->rollback($import, $admin))->toBe(2)
        ->and(Member::query()->count())->toBe(0)
        ->and(PersonNote::query()->count())->toBe(0)
        ->and($import->refresh()->status)->toBe(MemberImport::ROLLED_BACK);

    $next = app(SaveMember::class)->handle(new Member, ['name' => 'Sample After Rollback', 'sex' => 'male', 'id_type' => 'none', 'joined_on' => '2026-01-01', 'woreda_id' => $north->id], $admin);
    expect($next->member_no)->toBe('HPP-000003');

    $import->forceFill(['status' => MemberImport::APPLIED, 'applied_at' => now()->subDays(MemberImport::ROLLBACK_DAYS + 1)])->save();
    expect(fn () => app(ImportMembers::class)->rollback($import, $admin))->toThrow(ValidationException::class);
});

it('keeps officers to the woredas they look after', function () {
    $north = rollOffice('woreda', 'Sample Woreda North');
    rollOffice('woreda', 'Sample Woreda South');
    $officer = staff(SystemRole::MembershipOfficer);
    $officer->assignScopedRole(SystemRole::MembershipOfficer, ScopeType::Office, $north->id);
    $path = rollCsv([ROLL_HEADINGS,
        ['1', 'Sample North Member', 'M', '', '', '', 'Sample North', '', ''],
        ['2', 'Sample South Member', 'M', '', '', '', 'Sample South', '', ''],
        ['3', 'Sample Nowhere Member', 'M', '', '', '', '', '', ''],
    ]);

    $import = app(ImportMembers::class)->check($path, 'roll.csv', rollSettings(['id_type' => 'none', 'mapping' => [...ImportColumns::guess(ROLL_HEADINGS), 'id_number' => null]]), $officer->refresh());
    @unlink($path);

    expect($import->ready)->toBe(1)
        ->and(collect($import->problems)->pluck('problem', 'row')->all())->toBe([3 => 'outside_scope', 4 => 'outside_scope']);
    $this->actingAs(staff(SystemRole::Editor))->get(MemberImportResource::getUrl('index'))->assertForbidden();
});

it('walks through upload, column matching, check and apply on the admin screens', function () {
    Storage::fake('local');
    rollOffice('woreda', 'Sample Woreda North');
    $this->actingAs(staff(SystemRole::Admin));
    $csv = implode("\n", ['Full name,Sex,Woreda', 'Sample Screen Member,F,Sample North']);

    $page = livewire(ListMemberImports::class)
        ->callAction('import', ['file' => UploadedFile::fake()->createWithContent('screen-roll.csv', $csv)])
        ->assertHasNoActionErrors()
        ->assertSet('pendingHeadings', ['Full name', 'Sex', 'Woreda']);
    $page->callMountedAction()->assertHasNoActionErrors();

    $import = MemberImport::query()->sole();
    $page->assertRedirect(MemberImportResource::getUrl('view', ['record' => $import]));
    expect($import->ready)->toBe(1)->and(Storage::disk('local')->allFiles(ImportMembers::DIRECTORY))->toBe([]);

    livewire(ViewMemberImport::class, ['record' => $import->getRouteKey()])
        ->assertSee(__('members.import.no_problems'))
        ->callAction('apply')->assertHasNoActionErrors();
    expect(Member::query()->sole()->name)->toBe('Sample Screen Member');
});

it('drops the rows of imports nobody applied within a week, and uploads left behind', function () {
    Storage::fake('local');
    $old = MemberImport::query()->create(['file_name' => 'old.csv', 'status' => MemberImport::CHECKED, 'settings' => rollSettings(), 'rows' => [['name' => 'Sample']], 'problems' => []]);
    $old->forceFill(['created_at' => now()->subDays(ImportMembers::STALE_DAYS + 1)])->save();
    $fresh = MemberImport::query()->create(['file_name' => 'new.csv', 'status' => MemberImport::CHECKED, 'settings' => rollSettings(), 'rows' => [['name' => 'Sample']], 'problems' => []]);
    Storage::disk('local')->put(ImportMembers::DIRECTORY.'/left.csv', 'x');
    touch(Storage::disk('local')->path(ImportMembers::DIRECTORY.'/left.csv'), now()->subDays(2)->getTimestamp());

    $this->artisan('membership:purge-applications')->assertSuccessful();

    expect($old->refresh()->status)->toBe(MemberImport::DISCARDED)->and($old->rows)->toBeNull()
        ->and($fresh->refresh()->status)->toBe(MemberImport::CHECKED)
        ->and(Storage::disk('local')->exists(ImportMembers::DIRECTORY.'/left.csv'))->toBeFalse();
});
