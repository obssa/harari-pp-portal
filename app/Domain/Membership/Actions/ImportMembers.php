<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Access\Support\OfficeScope;
use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Membership\Jobs\ApplyMemberImport;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MemberImport;
use App\Domain\Membership\Models\MembershipApplication;
use App\Domain\Membership\Models\PersonNote;
use App\Domain\Membership\Support\ApplicationOptions;
use App\Domain\Membership\Support\ImportColumns;
use App\Domain\Organisation\Enums\OfficeType;
use App\Domain\Organisation\Models\Office;
use App\Models\User;
use App\Services\Calendar\EthiopianCalendar;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Throwable;

/**
 * Brings an existing membership roll in from Excel or CSV (PLAN.md §5,
 * ADR-0047), in steps: the officer matches the columns; the check reads
 * every row with the rules of the admin form and lists the problems and
 * duplicates without changing anything; applying (queued) adds the rows
 * that passed as members with new numbers; within 30 days the whole import
 * can be rolled back.
 */
final class ImportMembers
{
    /** Where uploaded rolls wait (private disk) between upload and check. */
    public const DIRECTORY = 'imports/members';

    public const MAX_ROWS = 5000;

    public const MAX_PROBLEMS = 1000;

    /** Days a checked import waits to be applied before its rows are dropped. */
    public const STALE_DAYS = 7;

    public const ERROR = 'error';

    public const DUPLICATE = 'duplicate';

    public const WARNING = 'warning';

    /** @var array<string, array<string, list<array{id: string, parent: ?string}>>> type => name => offices */
    private array $offices = [];

    public function __construct(private readonly EthiopianCalendar $calendar) {}

    /**
     * The first row of the file.
     *
     * @return list<string>
     */
    public function headings(string $path, string $fileName): array
    {
        foreach ($this->rows($path, $fileName) as $cells) {
            $headings = array_map(fn (mixed $cell): string => trim(str_replace("\u{FEFF}", '', $this->text($cell))), $cells);
            if (array_filter($headings, fn (string $h): bool => $h !== '') !== []) {
                return $headings;
            }
        }

        throw ValidationException::withMessages(['file' => __('members.import.validation.empty')]);
    }

    /**
     * Reads and checks every row; nothing is added yet.
     *
     * @param  array{id_type?: string, calendar?: string, joined_on?: string, mapping?: array<string, int|string|null>}  $settings
     */
    public function check(string $path, string $fileName, array $settings, User $actor): MemberImport
    {
        abort_unless($actor->can('members.import'), 403);
        $settings = $this->settings($settings);
        $mapping = $settings['mapping'];
        $this->loadOffices();
        $scope = OfficeScope::officeIds($actor, 'members.import');

        $ready = [];
        $problems = [];
        $total = 0;
        $withProblems = 0;
        $first = true;
        foreach ($this->rows($path, $fileName) as $index => $cells) {
            if ($first) {
                $first = false;

                continue;
            }
            $values = [];
            foreach ($mapping as $field => $column) {
                $values[$field] = $column === null ? null : ($cells[$column] ?? null);
            }
            if (array_filter($values, fn (mixed $v): bool => $this->text($v) !== '') === []) {
                continue;
            }
            if (++$total > self::MAX_ROWS) {
                throw ValidationException::withMessages(['file' => __('members.import.validation.too_many', ['max' => self::MAX_ROWS])]);
            }

            $rowNumber = $index + 1;
            [$row, $rowProblems] = $this->row($values, $settings, $scope);
            foreach ($rowProblems as [$field, $problem, $level]) {
                $problems[] = ['row' => $rowNumber, 'field' => $field, 'problem' => $problem, 'level' => $level];
            }
            if (in_array(self::ERROR, array_column($rowProblems, 2), true)) {
                $withProblems++;

                continue;
            }
            $ready[] = ['row' => $rowNumber, ...$row];
        }
        if ($total === 0) {
            throw ValidationException::withMessages(['file' => __('members.import.validation.empty')]);
        }

        [$ready, $duplicateProblems] = $this->withoutDuplicates($ready);
        $problems = [...$problems, ...$duplicateProblems];
        usort($problems, fn (array $a, array $b): int => $a['row'] <=> $b['row']);

        $import = MemberImport::query()->create([
            'user_id' => $actor->id,
            'file_name' => mb_substr(basename($fileName), 0, 190),
            'status' => MemberImport::CHECKED,
            'settings' => $settings,
            'rows' => $ready,
            'problems' => array_slice($problems, 0, self::MAX_PROBLEMS),
            'total' => $total,
            'ready' => count($ready),
            'with_problems' => $withProblems,
            'duplicates' => count(array_filter($duplicateProblems, fn (array $p): bool => $p['level'] === self::DUPLICATE)),
        ]);
        activity('membership')->performedOn($import)->causedBy($actor)->event('checked')->log('Membership roll checked');

        return $import;
    }

    /** Hands the import to the queue; the rows are added there. */
    public function apply(MemberImport $import, User $actor): void
    {
        abort_unless($actor->can('members.import'), 403);
        if ($import->status !== MemberImport::CHECKED || $import->ready === 0) {
            throw ValidationException::withMessages(['import' => __('members.import.validation.not_checked')]);
        }

        $import->forceFill(['status' => MemberImport::APPLYING])->save();
        activity('membership')->performedOn($import)->causedBy($actor)->event('applied')->log('Membership roll applied');
        ApplyMemberImport::dispatch($import->id);
    }

    /** The queued part: adds the members, checking again for anyone added since the check. */
    public function run(MemberImport $import): void
    {
        if ($import->status !== MemberImport::APPLYING) {
            return;
        }

        $created = 0;
        foreach (array_chunk($import->rows ?? [], 200) as $chunk) {
            DB::transaction(function () use ($import, $chunk, &$created): void {
                foreach ($chunk as $row) {
                    if ($row['id_hash'] !== null && Member::query()->withoutGlobalScopes()->where('id_hash', $row['id_hash'])->whereNull('erased_at')->exists()) {
                        continue;
                    }
                    $joined = CarbonImmutable::parse((string) $row['joined_on']);
                    $member = new Member([
                        ...array_intersect_key($row, array_flip(['name', 'sex', 'birth_date', 'birth_year', 'id_type', 'id_number', 'id_hash', 'education', 'occupation',
                            'woreda_id', 'kebele_id', 'basic_org_id', 'address_note', 'phone', 'phone_hash', 'email', 'email_hash'])),
                        'import_id' => $import->id,
                        'status' => MemberStatus::Active,
                        'source' => Member::SOURCE_IMPORT,
                        'joined_on' => $joined->toDateString(),
                        'renewal_due_on' => SaveMember::nextDue($joined)->toDateString(),
                    ]);
                    $member->site_id = $import->site_id;
                    $member->member_no = Member::nextNumber();
                    $member->save();
                    $member->notes()->create(['user_id' => $import->user_id, 'kind' => PersonNote::NOTE, 'body' => __('members.import.note', [
                        'row' => $row['row'], 'file' => $import->file_name, 'number' => $row['old_number'] ?? '—',
                    ])]);
                    $created++;
                }
            });
        }

        // The rows were personal details waiting to be added; they are no longer needed.
        $import->forceFill(['status' => MemberImport::APPLIED, 'applied_at' => CarbonImmutable::now(), 'created_count' => $created, 'rows' => null])->save();
    }

    public function failed(MemberImport $import): void
    {
        $import->forceFill(['status' => MemberImport::FAILED, 'applied_at' => CarbonImmutable::now(),
            'created_count' => $import->members()->count(), 'rows' => null])->save();
    }

    /** Removes the members the import added, with their history. Their numbers are not given out again. */
    public function rollback(MemberImport $import, User $actor): int
    {
        abort_unless($actor->can('members.import'), 403);
        $failedRecently = $import->status === MemberImport::FAILED && $import->applied_at?->greaterThan(CarbonImmutable::now()->subDays(MemberImport::ROLLBACK_DAYS));
        if (! $import->canRollBack() && ! $failedRecently) {
            throw ValidationException::withMessages(['import' => __('members.import.validation.cannot_roll_back', ['days' => MemberImport::ROLLBACK_DAYS])]);
        }

        $removed = DB::transaction(function () use ($import): int {
            $ids = $import->members()->withoutGlobalScopes()->pluck('id')->all();
            foreach (array_chunk($ids, 500) as $chunk) {
                PersonNote::query()->where('notable_type', Member::class)->whereIn('notable_id', $chunk)->delete();
                Member::query()->withoutGlobalScopes()->whereKey($chunk)->delete();
            }
            $import->forceFill(['status' => MemberImport::ROLLED_BACK, 'rolled_back_at' => CarbonImmutable::now()])->save();

            return count($ids);
        });
        activity('membership')->performedOn($import)->causedBy($actor)->event('rolled_back')->withProperties(['removed' => $removed])->log('Membership roll rolled back');

        return $removed;
    }

    public function discard(MemberImport $import, User $actor): void
    {
        abort_unless($actor->can('members.import'), 403);
        if ($import->status === MemberImport::CHECKED) {
            $import->forceFill(['status' => MemberImport::DISCARDED, 'rows' => null])->save();
        }
    }

    /**
     * Checked imports nobody applied within a week lose their rows; uploads
     * left behind by an import that was never checked go after a day.
     */
    public static function purgeStale(): int
    {
        $disk = Storage::disk('local');
        foreach ($disk->files(self::DIRECTORY) as $file) {
            if ($disk->lastModified($file) < CarbonImmutable::now()->subDay()->getTimestamp()) {
                $disk->delete($file);
            }
        }

        return MemberImport::query()->withoutGlobalScopes()
            ->where('status', MemberImport::CHECKED)
            ->where('created_at', '<', CarbonImmutable::now()->subDays(self::STALE_DAYS))
            ->update(['status' => MemberImport::DISCARDED, 'rows' => null]);
    }

    /**
     * @param  array{id_type?: string, calendar?: string, joined_on?: string, mapping?: array<string, int|string|null>}  $settings
     * @return array{id_type: string, calendar: string, joined_on: string, mapping: array<string, int|null>}
     */
    private function settings(array $settings): array
    {
        $mapping = [];
        foreach (array_keys(ImportColumns::FIELDS) as $field) {
            $column = $settings['mapping'][$field] ?? null;
            $mapping[$field] = $column === null || $column === '' ? null : (int) $column;
        }
        $idType = (string) ($settings['id_type'] ?? 'none');
        $calendar = (string) ($settings['calendar'] ?? 'gregorian');
        $errors = [];
        if ($mapping['name'] === null) {
            $errors['mapping.name'] = __('members.import.validation.name_column');
        }
        if (! in_array($idType, ApplicationOptions::ID_TYPES, true) || ($mapping['id_number'] !== null && $idType === 'none')) {
            $errors['id_type'] = __('members.import.validation.id_type');
        }
        if (! in_array($calendar, ['gregorian', 'ethiopian'], true)) {
            $errors['calendar'] = __('members.import.validation.calendar');
        }
        try {
            $joined = CarbonImmutable::parse((string) ($settings['joined_on'] ?? 'today'))->startOfDay();
        } catch (Throwable) {
            $joined = null;
        }
        if ($joined === null || $joined->isFuture()) {
            $errors['joined_on'] = __('members.validation.joined_on');
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        /** @var CarbonImmutable $joined */
        return ['id_type' => $idType, 'calendar' => $calendar, 'joined_on' => $joined->toDateString(), 'mapping' => $mapping];
    }

    /**
     * One row as member details, and what is wrong with it.
     *
     * @param  array<string, mixed>  $values
     * @param  array{id_type: string, calendar: string, joined_on: string, mapping: array<string, int|null>}  $settings
     * @param  list<string>|null  $scope
     * @return array{0: array<string, mixed>, 1: list<array{0: string, 1: string, 2: string}>}
     */
    private function row(array $values, array $settings, ?array $scope): array
    {
        $problems = [];
        $text = fn (string $field): string => trim((string) preg_replace('/\s+/u', ' ', $this->text($values[$field] ?? null)));

        $name = $text('name');
        if ($name === '' || mb_strlen($name) > 120) {
            $problems[] = ['name', 'name', self::ERROR];
        }

        $sex = $this->sex($text('sex'));
        if ($sex === false) {
            $problems[] = ['sex', 'sex', self::ERROR];
        }

        [$birthDate, $birthYear] = $this->birth($values['birth_date'] ?? null, $settings['calendar']);
        if ($birthDate === false) {
            $problems[] = ['birth_date', 'birth_date', self::ERROR];
        } elseif ($birthYear !== null && CarbonImmutable::now()->year - $birthYear < ApplicationOptions::MINIMUM_AGE) {
            $problems[] = ['birth_date', 'age', self::ERROR];
        }

        $idNumber = strtoupper((string) preg_replace('/[\s-]/', '', $text('id_number')));
        if ($idNumber !== '' && preg_match('/^[A-Z0-9]{4,30}$/', $idNumber) !== 1) {
            $problems[] = ['id_number', 'id_number', self::ERROR];
        }
        $hasId = $idNumber !== '' && $settings['id_type'] !== 'none';

        $phone = null;
        if ($text('phone') !== '' && ($phone = PhoneNumber::normalise($text('phone'))) === null) {
            $problems[] = ['phone', 'phone', self::ERROR];
        }
        $email = mb_strtolower($text('email'));
        if ($email !== '' && (mb_strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            $problems[] = ['email', 'email', self::ERROR];
        }

        $woreda = $this->office(OfficeType::Woreda, $text('woreda'), null);
        if ($woreda === false) {
            $problems[] = ['woreda', 'office', self::ERROR];
        }
        $woredaId = is_string($woreda) ? $woreda : null;
        if ($scope !== null && ($woredaId === null || ! in_array($woredaId, $scope, true))) {
            $problems[] = ['woreda', 'outside_scope', self::ERROR];
        }
        $kebele = $this->office(OfficeType::Kebele, $text('kebele'), $woredaId);
        if ($kebele === false) {
            $problems[] = ['kebele', 'office', self::ERROR];
        }
        $basic = $this->office(OfficeType::BasicOrganisation, $text('basic_org'), is_string($kebele) ? $kebele : $woredaId);
        if ($basic === false) {
            $problems[] = ['basic_org', 'office', self::ERROR];
        }

        $joined = $text('joined_on') === '' ? CarbonImmutable::parse($settings['joined_on']) : $this->date($values['joined_on'], $settings['calendar']);
        if ($joined === false || $joined->isFuture()) {
            $problems[] = ['joined_on', 'joined_on', self::ERROR];
        }

        $education = $this->education($text('education'));
        if ($education === false) {
            $problems[] = ['education', 'education', self::WARNING];
        }

        return [[
            'name' => $name,
            'sex' => is_string($sex) ? $sex : null,
            'birth_date' => is_string($birthDate) ? $birthDate : null,
            'birth_year' => $birthYear,
            'id_type' => $hasId ? $settings['id_type'] : 'none',
            'id_number' => $hasId ? $idNumber : null,
            'id_hash' => $hasId ? MembershipApplication::hash($settings['id_type'].':'.$idNumber) : null,
            'education' => is_string($education) ? $education : null,
            'occupation' => Str::limit($text('occupation'), 120, '') ?: null,
            'woreda_id' => $woredaId,
            'kebele_id' => is_string($kebele) ? $kebele : null,
            'basic_org_id' => is_string($basic) ? $basic : null,
            'address_note' => Str::limit($text('address_note'), 200, '') ?: null,
            'phone' => $phone,
            'phone_hash' => $phone !== null ? MembershipApplication::hash($phone) : null,
            'email' => $email !== '' ? $email : null,
            'email_hash' => $email !== '' ? MembershipApplication::hash($email) : null,
            'joined_on' => $joined instanceof CarbonImmutable ? $joined->toDateString() : null,
            'old_number' => Str::limit($text('old_number'), 40, '') ?: null,
        ], $problems];
    }

    /**
     * Rows whose ID number is already a member's, or appears twice in the
     * file, are left out; a phone already on file only warns (families share
     * phones).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{0: list<array<string, mixed>>, 1: list<array{row: int, field: string, problem: string, level: string}>}
     */
    private function withoutDuplicates(array $rows): array
    {
        $ids = array_values(array_filter(array_column($rows, 'id_hash')));
        $phones = array_values(array_filter(array_column($rows, 'phone_hash')));
        $knownIds = [];
        $knownPhones = [];
        foreach (array_chunk($ids, 1000) as $chunk) {
            $knownIds += array_flip(Member::query()->withoutGlobalScopes()->whereNull('erased_at')->whereIn('id_hash', $chunk)->pluck('id_hash')->all());
        }
        foreach (array_chunk($phones, 1000) as $chunk) {
            $knownPhones += array_flip(Member::query()->withoutGlobalScopes()->whereNull('erased_at')->whereIn('phone_hash', $chunk)->pluck('phone_hash')->all());
        }

        $kept = [];
        $problems = [];
        $seenIds = [];
        $seenPhones = [];
        foreach ($rows as $row) {
            if ($row['id_hash'] !== null && (isset($knownIds[$row['id_hash']]) || isset($seenIds[$row['id_hash']]))) {
                $problems[] = ['row' => $row['row'], 'field' => 'id_number', 'problem' => isset($knownIds[$row['id_hash']]) ? 'duplicate_member' : 'duplicate_row', 'level' => self::DUPLICATE];

                continue;
            }
            if ($row['phone_hash'] !== null && (isset($knownPhones[$row['phone_hash']]) || isset($seenPhones[$row['phone_hash']]))) {
                $problems[] = ['row' => $row['row'], 'field' => 'phone', 'problem' => 'shared_phone', 'level' => self::WARNING];
            }
            if ($row['id_hash'] !== null) {
                $seenIds[$row['id_hash']] = true;
            }
            if ($row['phone_hash'] !== null) {
                $seenPhones[$row['phone_hash']] = true;
            }
            $kept[] = $row;
        }

        return [$kept, $problems];
    }

    /** null when empty, false when not understood. */
    private function sex(string $value): string|false|null
    {
        $value = mb_strtolower($value);

        return match (true) {
            $value === '' => null,
            in_array($value, ['m', 'male', 'man', 'ወ', 'ወንድ', 'dhiira', 'dh'], true) => 'male',
            in_array($value, ['f', 'female', 'woman', 'ሴ', 'ሴት', 'dhalaa', 'dubartii'], true) => 'female',
            default => false,
        };
    }

    /**
     * A full date, or a year alone.
     *
     * @return array{0: string|false|null, 1: ?int}
     */
    private function birth(mixed $value, string $calendar): array
    {
        $text = $this->text($value);
        if ($text === '') {
            return [null, null];
        }
        if (! $value instanceof DateTimeInterface && preg_match('/^\d{4}$/', $text) === 1) {
            $year = (int) $text;
            // Most of an Ethiopian year falls in the Gregorian year eight later.
            $year = $calendar === 'ethiopian' ? $year + 8 : $year;

            return $year >= 1900 && $year <= CarbonImmutable::now()->year ? [null, $year] : [false, null];
        }
        $date = $this->date($value, $calendar);

        return $date instanceof CarbonImmutable && $date->year >= 1900 && ! $date->isFuture() ? [$date->toDateString(), $date->year] : [false, null];
    }

    /**
     * Spreadsheet dates are always Gregorian (a date cell, or a cell formatted
     * as a number holding Excel's day count); typed dates are day/month/year or
     * year-month-day in the chosen calendar.
     */
    private function date(mixed $value, string $calendar): CarbonImmutable|false
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->startOfDay();
        }
        if ((is_int($value) || is_float($value)) && $value >= 1 && $value < 2958466) {
            return CarbonImmutable::create(1899, 12, 30)?->addDays((int) floor($value)) ?? false;
        }
        $text = $this->text($value);
        if (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})$/', $text, $m) === 1) {
            [$year, $month, $day] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})$/', $text, $m) === 1) {
            [$day, $month, $year] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            return false;
        }
        try {
            if ($calendar === 'ethiopian') {
                return $this->calendar->toGregorian($year, $month, $day)->startOfDay();
            }

            return checkdate($month, $day, $year) ? CarbonImmutable::create($year, $month, $day) ?? false : false;
        } catch (Throwable) {
            return false;
        }
    }

    private function education(string $value): string|false|null
    {
        if ($value === '') {
            return null;
        }
        $value = mb_strtolower($value);
        foreach (ApplicationOptions::EDUCATION as $key) {
            $labels = [$key, ...array_map(fn (string $locale): string => mb_strtolower(__("membership.education.{$key}", [], $locale)), ['en', 'am', 'om'])];
            if (in_array($value, $labels, true)) {
                return $key;
            }
        }

        return false;
    }

    /**
     * An office by its name in any language, with or without the word for its
     * kind; among offices of the same name the one under the given parent.
     * null when empty, false when unknown or not clear.
     */
    private function office(OfficeType $type, string $name, ?string $parent): string|false|null
    {
        if ($name === '') {
            return null;
        }
        $matches = $this->offices[$type->value][self::officeName($name)] ?? [];
        if (count($matches) > 1 && $parent !== null) {
            $matches = array_values(array_filter($matches, fn (array $office): bool => $office['parent'] === $parent));
        }

        return count($matches) === 1 ? $matches[0]['id'] : false;
    }

    private function loadOffices(): void
    {
        $this->offices = [];
        $types = [OfficeType::Woreda->value, OfficeType::Kebele->value, OfficeType::BasicOrganisation->value];
        foreach (Office::query()->whereIn('type', $types)->with('translations')->get() as $office) {
            $names = [];
            foreach ($office->translations as $translation) {
                $names[self::officeName((string) $translation->getAttribute('name'))] = true;
            }
            foreach (array_keys($names) as $name) {
                if ($name !== '') {
                    $this->offices[$office->type->value][$name][] = ['id' => $office->id, 'parent' => $office->parent_id];
                }
            }
        }
    }

    private static function officeName(string $name): string
    {
        $name = ImportColumns::normalise($name);

        return trim((string) preg_replace('/(^|\s)(woreda|wereda|district|kebele|ወረዳ|ቀበሌ|aanaa|ganda)(\s|$)/u', ' ', $name));
    }

    private function text(mixed $value): string
    {
        return match (true) {
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            is_float($value) && floor($value) === $value => (string) (int) $value,
            is_scalar($value) => trim((string) $value),
            default => '',
        };
    }

    /**
     * The rows of the first sheet, or of the CSV file (comma or semicolon).
     *
     * @return \Generator<int, list<mixed>>
     */
    private function rows(string $path, string $fileName): \Generator
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if ($extension === 'xlsx') {
            $reader = new XlsxReader;
            try {
                $reader->open($path);
            } catch (Throwable) {
                throw ValidationException::withMessages(['file' => __('members.import.validation.unreadable')]);
            }
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $index => $row) {
                    yield $index - 1 => $row->toArray();
                }

                break;
            }
            $reader->close();

            return;
        }
        if ($extension !== 'csv') {
            throw ValidationException::withMessages(['file' => __('members.import.validation.type')]);
        }

        $in = fopen($path, 'rb');
        if ($in === false) {
            throw ValidationException::withMessages(['file' => __('members.import.validation.unreadable')]);
        }
        $first = (string) fgets($in);
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        rewind($in);
        $index = 0;
        while (($row = fgetcsv($in, separator: $delimiter, escape: '')) !== false) {
            $cells = array_map(fn (mixed $cell): string => (string) $cell, $row);
            if (! mb_check_encoding(implode('', $cells), 'UTF-8')) {
                fclose($in);

                throw ValidationException::withMessages(['file' => __('members.import.validation.encoding')]);
            }
            yield $index++ => $cells;
        }
        fclose($in);
    }
}
