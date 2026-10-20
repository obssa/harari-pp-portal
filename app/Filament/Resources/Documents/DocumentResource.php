<?php

namespace App\Filament\Resources\Documents;

use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Models\Document;
use App\Domain\Media\Enums\MediaKind;
use App\Domain\Organisation\Models\League;
use App\Domain\Organisation\Models\Office;
use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Filament\Resources\Documents\Pages\EditDocument;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Filament\Resources\Documents\RelationManagers\VersionsRelationManager;
use App\Filament\Support\CalendarDate;
use App\Filament\Support\DirectoryFields;
use App\Filament\Support\MediaPicker;
use App\Filament\Support\Translatable;
use App\Services\Calendar\EthiopianCalendar;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 22;

    public static function getModelLabel(): string
    {
        return __('documents.admin.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('documents.admin.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.content');
    }

    public static function form(Schema $schema): Schema
    {
        $isNotice = fn (Get $get): bool => DocumentType::tryFrom((string) $get('type'))?->isNotice() ?? false;

        return $schema->columns(3)->components([
            Grid::make(1)->columnSpan(['default' => 3, 'lg' => 2])->schema([
                Translatable::tabs(fn (string $locale): array => [
                    Translatable::mainField($locale, 'title', 250)->label(__('admin.posts.field.title')),
                    Translatable::slugField($locale, 'document_translations'),
                    Textarea::make("translations.{$locale}.summary")->label(__('documents.admin.field.summary'))->rows(2)->maxLength(500),
                    DirectoryFields::body($locale, __('documents.admin.field.body')),
                    MediaPicker::make("translations.{$locale}.file_id", [MediaKind::Document])
                        ->label(__('documents.admin.field.file'))
                        ->helperText(__('documents.admin.help.file')),
                    TextInput::make("translations.{$locale}.version_note")->label(__('documents.admin.field.version_note'))
                        ->helperText(__('documents.admin.help.version_note'))->maxLength(300),
                    DirectoryFields::published($locale, 'documents.manage'),
                ]),
            ]),
            Grid::make(1)->columnSpan(['default' => 3, 'lg' => 1])->schema([
                Section::make(__('admin.posts.section.details'))->schema([
                    Select::make('type')->label(__('documents.admin.field.type'))->required()->live()
                        ->options([
                            __('documents.admin.group.library') => DocumentType::options(DocumentType::library()),
                            __('documents.admin.group.notices') => DocumentType::options(DocumentType::notices()),
                        ]),
                    TextInput::make('year')->label(__('documents.admin.field.year'))->numeric()->integer()->minValue(1900)->maxValue(2100),
                    DatePicker::make('issued_on')->label(__('documents.admin.field.issued_on')),
                    DateTimePicker::make('deadline_at')->label(__('documents.admin.field.deadline_at'))->seconds(false)
                        ->timezone(EthiopianCalendar::timezone())
                        ->helperText(__('documents.admin.help.deadline_at'))
                        ->visible($isNotice),
                    Select::make('office_id')->label(__('admin.leaders.field.office'))->searchable()
                        ->options(fn (): array => Office::query()->with('translations')->orderBy('sort')->get()
                            ->mapWithKeys(fn (Office $o): array => [$o->id => $o->name()])->all()),
                    Select::make('league_id')->label(__('admin.leaders.field.league'))
                        ->options(fn (): array => League::query()->with('translations')->orderBy('sort')->get()
                            ->mapWithKeys(fn (League $l): array => [$l->id => $l->name()])->all()),
                    Select::make('related')->label(__('documents.admin.field.related'))->multiple()->searchable()
                        ->maxItems(8)
                        ->options(fn (?Document $record): array => Document::query()->with('translations')
                            ->when($record, fn (Builder $q) => $q->whereKeyNot($record?->getKey()))
                            ->latest()->limit(300)->get()
                            ->mapWithKeys(fn (Document $d): array => [$d->id => $d->title()])->all()),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('translations'))
            ->columns([
                TextColumn::make('title')->label(__('admin.posts.field.title'))
                    ->state(fn (Document $record): string => $record->title())->wrap(),
                TextColumn::make('type')->label(__('documents.admin.field.type'))->badge()
                    ->formatStateUsing(fn (DocumentType $state): string => $state->label()),
                TextColumn::make('year')->label(__('documents.admin.field.year'))->placeholder('—')->sortable(),
                TextColumn::make('deadline_at')->label(__('documents.admin.field.deadline_at'))
                    ->formatStateUsing(CalendarDate::format(withTime: true))->placeholder('—')->sortable()->toggleable(),
                TextColumn::make('download_count')->label(__('documents.admin.field.downloads'))->numeric()->sortable(),
                DirectoryFields::languagesColumn(),
            ])
            ->filters([
                SelectFilter::make('type')->label(__('documents.admin.field.type'))
                    ->options(DocumentType::options(DocumentType::cases())),
                TrashedFilter::make(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make(), RestoreAction::make()]);
    }

    public static function getRelations(): array
    {
        return [VersionsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocuments::route('/'),
            'create' => CreateDocument::route('/create'),
            'edit' => EditDocument::route('/{record}/edit'),
        ];
    }

    /**
     * @return Builder<Document>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Document> */
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
