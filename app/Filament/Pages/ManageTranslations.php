<?php

namespace App\Filament\Pages;

use App\Domain\Translation\Actions\EditUiTranslation;
use App\Domain\Translation\Actions\ImportTranslations;
use App\Domain\Translation\Models\TranslationImport;
use App\Domain\Translation\Models\UiTranslationRevision;
use App\Domain\Translation\Support\Glossary;
use App\Domain\Translation\Support\Locales;
use App\Domain\Translation\Support\TranslationFile;
use App\Domain\Translation\Support\TranslationRows;
use App\Filament\Support\CalendarDate;
use App\Filament\Support\CurrentUser;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use UnitEnum;

/**
 * The interface strings of the site in a grid, one language at a time
 * (PLAN.md §6, ADR-0033): filter what is missing or out of date, read the
 * English and its context, and correct a language you may edit.
 */
class ManageTranslations extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLanguage;

    protected static ?string $slug = 'translations';

    protected static ?int $navigationSort = 50;

    #[Url]
    public string $language = '';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('translations.admin.nav');
    }

    public static function getNavigationLabel(): string
    {
        return __('translations.admin.title');
    }

    public function getTitle(): string
    {
        return __('translations.admin.title');
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->can('translations.view');
    }

    public function mount(): void
    {
        if (! in_array($this->language, Locales::all(), true)) {
            $user = CurrentUser::get();
            $this->language = collect(['har', 'am', 'om', 'en'])->first(fn (string $l): bool => $user->canEditTranslationsFor($l)) ?? 'am';
        }
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function (?string $search, array $filters, int $page, int $recordsPerPage): LengthAwarePaginator {
                $rows = app(TranslationRows::class)->for($this->language);
                $group = $filters['group']['value'] ?? null;
                $status = $filters['status']['value'] ?? null;
                if (filled($group)) {
                    $rows = $rows->where('group', $group);
                }
                if (filled($status)) {
                    $rows = $rows->where('status', $status);
                }
                if (filled($search)) {
                    $needle = Str::lower((string) $search);
                    $rows = $rows->filter(fn (array $row): bool => Str::contains(Str::lower($row['key'].' '.$row['english'].' '.$row['text']), $needle));
                }

                return new LengthAwarePaginator($rows->forPage($page, $recordsPerPage), $rows->count(), $recordsPerPage, $page);
            })
            ->defaultPaginationPageOption(50)
            ->paginated([25, 50, 100])
            ->searchable()
            ->heading(fn (): string => __('translations.admin.heading', ['language' => Locales::nativeName($this->language)]))
            ->headerActions([
                Action::make('export')
                    ->label(__('translations.export.action'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->visible(fn (): bool => CurrentUser::get()->can('translations.export'))
                    ->modalDescription(fn (): string => __('translations.export.help', ['language' => Locales::nativeName($this->language)]))
                    ->schema([Select::make('format')->label(__('translations.export.format'))->required()->default('xlsx')
                        ->options(['xlsx' => __('translations.export.xlsx'), 'csv' => __('translations.export.csv'), 'json' => __('translations.export.json')])])
                    ->action(function (array $data): BinaryFileResponse {
                        abort_unless(CurrentUser::get()->can('translations.export'), 403);
                        $path = app(TranslationFile::class)->export($this->language, (string) $data['format']);

                        return response()->download($path, basename($path))->deleteFileAfterSend();
                    }),
                Action::make('import')
                    ->label(__('translations.import.action'))
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->color('gray')
                    ->visible(fn (): bool => CurrentUser::get()->can('translations.import') && CurrentUser::get()->canEditTranslationsFor($this->language))
                    ->modalDescription(fn (): string => __('translations.import.help', ['language' => Locales::nativeName($this->language)]))
                    ->schema([
                        FileUpload::make('file')->label(__('translations.import.file'))->required()
                            ->disk('local')->directory('translations/imports')->storeFileNamesIn('file_name')
                            ->maxSize(TranslationFile::MAX_BYTES / 1024),
                        Toggle::make('clear_empty')->label(__('translations.import.clear_empty'))->helperText(__('translations.import.clear_empty_help')),
                    ])
                    ->action(function (array $data): void {
                        $stored = (string) $data['file'];
                        try {
                            $import = app(ImportTranslations::class)->preview(Storage::disk('local')->path($stored), (string) ($data['file_name'] ?? $stored),
                                $this->language, CurrentUser::get(), (bool) ($data['clear_empty'] ?? false));
                        } finally {
                            Storage::disk('local')->delete($stored);
                        }
                        $this->replaceMountedAction('applyImport', ['import' => $import->id], context: []);
                    }),
                Action::make('language')
                    ->label(__('translations.admin.change_language'))
                    ->icon(Heroicon::OutlinedLanguage)
                    ->color('gray')
                    ->fillForm(fn (): array => ['language' => $this->language])
                    ->schema([Select::make('language')->label(__('admin.revisions.field.language'))->required()
                        ->options(collect(Locales::all())->mapWithKeys(fn (string $l): array => [$l => Locales::nativeName($l)])->all())])
                    ->action(function (array $data): void {
                        $this->language = (string) $data['language'];
                        $this->resetTable();
                    }),
            ])
            ->columns([
                TextColumn::make('key')->label(__('translations.admin.key'))->fontFamily('mono')->size('xs')->wrap(),
                TextColumn::make('english')->label(Locales::nativeName('en'))->wrap()->limit(160),
                TextColumn::make('text')->label(fn (): string => Locales::nativeName($this->language))->wrap()->limit(160)->placeholder('—')
                    ->extraAttributes(fn (): array => ['lang' => $this->language]),
                TextColumn::make('status')->label(__('translations.admin.status'))->badge()
                    ->formatStateUsing(fn (string $state): string => __("translations.status.{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        TranslationRows::MISSING => 'danger',
                        TranslationRows::CHANGED, TranslationRows::GLOSSARY => 'warning',
                        default => 'success',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('translations.admin.status'))->options([
                    TranslationRows::MISSING => __('translations.status.missing'),
                    TranslationRows::CHANGED => __('translations.status.changed'),
                    TranslationRows::GLOSSARY => __('translations.status.glossary'),
                    TranslationRows::TRANSLATED => __('translations.status.translated'),
                ]),
                SelectFilter::make('group')->label(__('translations.admin.group'))->searchable()
                    ->options(fn (): array => app(TranslationRows::class)->for('en')->pluck('group', 'group')->unique()->sort()->all()),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label(fn (): string => CurrentUser::get()->canEditTranslationsFor($this->language) ? __('translations.admin.edit') : __('translations.admin.view'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->modalHeading(fn (array $record): string => $record['key'])
                    ->modalSubmitAction(fn (Action $action) => CurrentUser::get()->canEditTranslationsFor($this->language) ? $action : false)
                    ->fillForm(fn (array $record): array => ['text' => $record['text']])
                    ->schema(fn (array $record): array => [
                        TextEntry::make('english')->label(Locales::nativeName('en'))->state($record['english'])->extraAttributes(['class' => 'whitespace-pre-line']),
                        TextEntry::make('context')->label(__('translations.admin.context'))->state($record['context'] ?? null)->placeholder('—')
                            ->url($record['screenshot'] ?? null, shouldOpenInNewTab: true),
                        TextEntry::make('placeholders')->label(__('translations.admin.placeholders'))->state(EditUiTranslation::placeholders($record['english'])),
                        TextEntry::make('glossary')->label(__('translations.glossary.in_text'))->listWithLineBreaks()->placeholder('—')
                            ->state(self::glossaryLines($record['english'], $record['text'], $this->language)),
                        Textarea::make('text')->label(Locales::nativeName($this->language))->rows(4)
                            ->disabled(! CurrentUser::get()->canEditTranslationsFor($this->language))
                            ->helperText(__('translations.admin.empty_help'))
                            ->extraInputAttributes(['lang' => $this->language]),
                        TextEntry::make('history')->label(__('translations.admin.history'))
                            ->state(UiTranslationRevision::query()->where('key', $record['key'])->where('locale', $this->language)->with('user')->latest('id')->limit(5)->get()
                                ->map(fn (UiTranslationRevision $r): string => CalendarDate::format(withTime: true)($r->created_at).' · '.($r->user->name ?? __("translations.origin.{$r->origin}")).': '.Str::limit((string) ($r->new_text ?? '—'), 80))
                                ->all())
                            ->listWithLineBreaks()->placeholder('—'),
                    ])
                    ->action(function (array $record, array $data): void {
                        app(EditUiTranslation::class)->handle($record['key'], $this->language, $data['text'] ?? null, CurrentUser::get());
                        Notification::make()->success()->title(__('translations.admin.saved'))->send();
                        $this->resetTable();
                    }),
            ]);
    }

    /** The second step of an import: what would change, then save (ADR-0033). */
    public function applyImportAction(): Action
    {
        return Action::make('applyImport')
            ->modalHeading(__('translations.import.preview'))
            ->modalSubmitActionLabel(__('translations.import.apply'))
            ->schema(function (array $arguments): array {
                $import = TranslationImport::query()->findOrFail($arguments['import'] ?? 0);

                return [
                    TextEntry::make('summary')->hiddenLabel()->state(__('translations.import.summary', [
                        'added' => $import->added, 'changed' => $import->changed, 'removed' => $import->removed, 'problems' => count($import->problems ?? []),
                    ])),
                    TextEntry::make('changes')->label(__('translations.import.changes'))->listWithLineBreaks()->limitList(20)->placeholder('—')
                        ->state(self::changeLines($import)),
                    TextEntry::make('problems')->label(__('translations.import.problems'))->listWithLineBreaks()->limitList(20)->placeholder('—')->color('danger')
                        ->state(self::problemLines($import)),
                ];
            })
            ->action(function (array $arguments): void {
                $import = TranslationImport::query()->findOrFail($arguments['import'] ?? 0);
                app(ImportTranslations::class)->apply($import, CurrentUser::get());
                Notification::make()->success()->title(__('translations.import.applied', ['count' => $import->added + $import->changed + $import->removed]))->send();
                $this->resetTable();
            });
    }

    /**
     * The approved terms this English text uses, with the term for the language.
     *
     * @return list<string>
     */
    private static function glossaryLines(string $english, string $text, string $locale): array
    {
        $lines = [];
        foreach (app(Glossary::class)->check($english, $text, $locale) as $use) {
            $lines[] = ($use['followed'] ? '✓ ' : '✗ ').$use['en'].' → '.($use['term'] ?? '—').($use['notes'] ? ' ('.$use['notes'].')' : '');
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private static function changeLines(TranslationImport $import): array
    {
        $lines = [];
        foreach ($import->changes ?? [] as $key => $change) {
            $lines[] = $key.': '.Str::limit($change['new'] !== '' ? $change['new'] : '—', 70);
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private static function problemLines(TranslationImport $import): array
    {
        $lines = [];
        foreach ($import->problems ?? [] as $problem) {
            $lines[] = $problem['key'].': '.__("translations.import.problem.{$problem['problem']}");
        }

        return $lines;
    }
}
