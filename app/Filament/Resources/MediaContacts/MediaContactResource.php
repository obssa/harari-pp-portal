<?php

namespace App\Filament\Resources\MediaContacts;

use App\Domain\Press\Models\MediaContact;
use App\Domain\Translation\Support\Locales;
use App\Filament\Resources\MediaContacts\Pages\CreateMediaContact;
use App\Filament\Resources\MediaContacts\Pages\EditMediaContact;
use App\Filament\Resources\MediaContacts\Pages\ListMediaContacts;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Media contacts (PLAN.md §5, ADR-0054): journalists and newsrooms that
 * receive press releases, statements and speeches in their languages.
 */
class MediaContactResource extends Resource
{
    protected static ?string $model = MediaContact::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'media-contacts';

    public static function getModelLabel(): string
    {
        return __('press.media.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('press.media.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.communication');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('press.media.section'))->columns(2)->schema([
                TextInput::make('name')->label(__('membership.field.name'))->required()->maxLength(120),
                TextInput::make('outlet')->label(__('press.media.outlet'))->required()->maxLength(120),
                TextInput::make('position')->label(__('press.media.position'))->maxLength(120),
                TextInput::make('email')->label(__('newsletter.field.email'))->email()->required()->maxLength(190),
                TextInput::make('phone')->label(__('newsletter.field.phone'))->tel()->maxLength(20),
                // A list in the order chosen, which the checkbox list would not keep.
                Select::make('languages')->label(__('press.media.languages'))->helperText(__('press.media.languages_help'))->required()->multiple()
                    ->options(collect(Locales::all())->mapWithKeys(fn (string $l): array => [$l => Locales::nativeName($l)])->all()),
                Textarea::make('notes')->label(__('press.media.notes'))->rows(3)->maxLength(2000)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('outlet')
            ->columns([
                TextColumn::make('outlet')->label(__('press.media.outlet'))->searchable()->sortable(),
                TextColumn::make('contact_name')->label(__('membership.field.name'))->state(fn (MediaContact $record): string => $record->name),
                TextColumn::make('contact_email')->label(__('newsletter.field.email'))->state(fn (MediaContact $record): string => $record->email)->copyable(),
                TextColumn::make('languages')->label(__('press.media.languages'))->badge()->formatStateUsing(fn (string $state): string => Locales::nativeName($state)),
                TextColumn::make('opted_out_at')->label(__('press.media.status'))->badge()
                    ->state(fn (MediaContact $record): string => $record->opted_out_at === null ? __('press.media.active') : __('press.media.opted_out'))
                    ->color(fn (MediaContact $record): string => $record->opted_out_at === null ? 'success' : 'gray'),
            ])
            ->filters([
                TernaryFilter::make('active')->label(__('press.media.status'))->trueLabel(__('press.media.active'))->falseLabel(__('press.media.opted_out'))
                    ->queries(true: fn (Builder $q) => $q->whereNull('opted_out_at'), false: fn (Builder $q) => $q->whereNotNull('opted_out_at')),
                SelectFilter::make('language')->label(__('press.media.languages'))
                    ->options(collect(Locales::all())->mapWithKeys(fn (string $l): array => [$l => Locales::nativeName($l)])->all())
                    ->query(fn (Builder $q, array $data): Builder => filled($data['value'] ?? null) ? $q->whereJsonContains('languages', (string) $data['value']) : $q),
            ])
            ->recordActions([EditAction::make()])
            ->emptyStateHeading(__('press.media.empty'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMediaContacts::route('/'),
            'create' => CreateMediaContact::route('/create'),
            'edit' => EditMediaContact::route('/{record}/edit'),
        ];
    }
}
