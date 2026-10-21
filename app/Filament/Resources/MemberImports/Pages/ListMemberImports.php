<?php

namespace App\Filament\Resources\MemberImports\Pages;

use App\Domain\Membership\Actions\ImportMembers;
use App\Domain\Membership\Support\ApplicationOptions;
use App\Domain\Membership\Support\ImportColumns;
use App\Filament\Resources\MemberImports\MemberImportResource;
use App\Filament\Support\CurrentUser;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListMemberImports extends ListRecords
{
    protected static string $resource = MemberImportResource::class;

    /** The uploaded file between the two steps; never taken from the browser. */
    #[Locked]
    public ?string $pendingFile = null;

    #[Locked]
    public ?string $pendingName = null;

    /** @var list<string> */
    #[Locked]
    public array $pendingHeadings = [];

    protected function getHeaderActions(): array
    {
        return [
            Action::make('template')
                ->label(__('members.import.template'))
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('gray')
                ->visible(fn (): bool => CurrentUser::get()->can('members.import'))
                ->action(fn (): StreamedResponse => response()->streamDownload(function (): void {
                    $out = fopen('php://output', 'wb');
                    fwrite($out, "\u{FEFF}");
                    fputcsv($out, array_map(fn (string $field): string => __("members.import.field.{$field}"), array_keys(ImportColumns::FIELDS)), escape: '');
                    fclose($out);
                }, 'members-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8'])),
            Action::make('import')
                ->label(__('members.import.start'))
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->visible(fn (): bool => CurrentUser::get()->can('members.import'))
                ->modalDescription(__('members.import.start_help', ['max' => ImportMembers::MAX_ROWS]))
                ->schema([
                    FileUpload::make('file')->label(__('members.import.file'))->required()
                        ->disk('local')->directory(ImportMembers::DIRECTORY)->visibility('private')->storeFileNamesIn('file_name')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->maxSize(5 * 1024),
                ])
                ->action(function (array $data): void {
                    $stored = (string) $data['file'];
                    $name = (string) ($data['file_name'] ?? $stored);
                    try {
                        $headings = app(ImportMembers::class)->headings(Storage::disk('local')->path($stored), $name);
                    } catch (ValidationException $e) {
                        Storage::disk('local')->delete($stored);

                        throw $e;
                    }
                    $this->pendingFile = $stored;
                    $this->pendingName = $name;
                    $this->pendingHeadings = $headings;
                    $this->replaceMountedAction('mapColumns', context: []);
                }),
        ];
    }

    /** The second step: which column holds what, then the check (ADR-0047). */
    public function mapColumnsAction(): Action
    {
        $columns = array_filter($this->pendingHeadings, fn (string $heading): bool => $heading !== '');
        $columns = array_map(fn (int $i): string => ($this->pendingHeadings[$i] ?? '') !== '' ? $this->pendingHeadings[$i] : __('members.import.column', ['number' => $i + 1]), array_keys($columns));
        $options = array_combine(array_keys(array_filter($this->pendingHeadings, fn (string $h): bool => $h !== '')), $columns) ?: [];
        $guess = ImportColumns::guess($this->pendingHeadings);

        return Action::make('mapColumns')
            ->modalHeading(__('members.import.map'))
            ->modalDescription(fn (): string => __('members.import.map_help', ['file' => (string) $this->pendingName]))
            ->modalSubmitActionLabel(__('members.import.check'))
            ->modalWidth('3xl')
            ->fillForm(fn (): array => [
                'mapping' => $guess,
                'id_type' => $guess['id_number'] !== null ? 'fayda' : 'none',
                'calendar' => 'gregorian',
                'joined_on' => now()->toDateString(),
            ])
            ->schema([
                Section::make(__('members.import.columns'))->schema([
                    Grid::make(['default' => 1, 'md' => 2])->schema(array_map(
                        fn (string $field): Select => Select::make("mapping.{$field}")->label(__("members.import.field.{$field}"))->options($options)
                            ->placeholder(__('members.import.not_in_file'))->required($field === 'name'),
                        array_keys(ImportColumns::FIELDS),
                    )),
                ]),
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    Select::make('id_type')->label(__('members.import.id_type'))->options(ApplicationOptions::idTypes())->required(),
                    Radio::make('calendar')->label(__('members.import.calendar'))->required()
                        ->options(['gregorian' => __('membership.calendar.gregorian'), 'ethiopian' => __('membership.calendar.ethiopian')]),
                    DatePicker::make('joined_on')->label(__('members.import.joined_default'))->required()->maxDate(now()),
                ]),
            ])
            ->action(function (array $data): void {
                abort_if($this->pendingFile === null, 404);
                try {
                    $import = app(ImportMembers::class)->check(Storage::disk('local')->path($this->pendingFile), (string) $this->pendingName, $data, CurrentUser::get());
                } finally {
                    Storage::disk('local')->delete($this->pendingFile);
                    $this->pendingFile = null;
                    $this->pendingHeadings = [];
                }
                $this->redirect(MemberImportResource::getUrl('view', ['record' => $import]));
            });
    }
}
