<?php

namespace App\Filament\Resources\GlossaryTerms;

use App\Domain\Translation\Models\GlossaryTerm;
use App\Domain\Translation\Support\Locales;
use App\Filament\Resources\GlossaryTerms\Pages\ManageGlossaryTerms;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Approved terms per language (ADR-0034).
 */
class GlossaryTermResource extends Resource
{
    protected static ?string $model = GlossaryTerm::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?int $navigationSort = 52;

    public static function getModelLabel(): string
    {
        return __('translations.glossary.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('translations.glossary.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('translations.admin.nav');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            ...array_map(fn (string $locale): TextInput => TextInput::make("terms.{$locale}")
                ->label(Locales::nativeName($locale))
                ->required($locale === 'en')
                ->maxLength(200)
                ->extraInputAttributes(['lang' => $locale]), Locales::all()),
            Textarea::make('notes')->label(__('translations.glossary.notes'))->helperText(__('translations.glossary.notes_help'))->rows(3)->maxLength(1000)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->columns([
                ...array_map(fn (string $locale): TextColumn => TextColumn::make("terms.{$locale}")
                    ->label(Locales::nativeName($locale))->placeholder('—')->searchable()->wrap(), Locales::all()),
                TextColumn::make('notes')->label(__('translations.glossary.notes'))->limit(80)->placeholder('—')->wrap(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageGlossaryTerms::route('/')];
    }
}
