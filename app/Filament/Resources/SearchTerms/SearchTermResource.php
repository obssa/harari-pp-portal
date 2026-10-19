<?php

namespace App\Filament\Resources\SearchTerms;

use App\Domain\Search\Models\SearchTerm;
use App\Domain\Translation\Support\Locales;
use App\Filament\Resources\SearchTerms\Pages\ListSearchTerms;
use App\Filament\Support\CalendarDate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * What visitors search for (PLAN.md §7 search insights): most searched
 * terms and searches that found nothing (content gaps). Read only.
 */
class SearchTermResource extends Resource
{
    protected static ?string $model = SearchTerm::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static ?int $navigationSort = 60;

    public static function getModelLabel(): string
    {
        return __('admin.search_terms.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.search_terms.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.content');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('searches', 'desc')
            ->columns([
                TextColumn::make('term')->label(__('admin.search_terms.field.term'))->searchable(),
                TextColumn::make('locale')->label(__('admin.search_terms.field.locale'))
                    ->formatStateUsing(fn (string $state): string => Locales::nativeName($state))
                    ->badge(),
                TextColumn::make('searches')->label(__('admin.search_terms.field.searches'))->numeric()->sortable(),
                TextColumn::make('last_results')->label(__('admin.search_terms.field.last_results'))->numeric()->sortable()
                    ->color(fn (int $state): string => $state === 0 ? 'danger' : 'gray')
                    ->badge(),
                TextColumn::make('last_searched_at')->label(__('admin.search_terms.field.last_searched_at'))
                    ->formatStateUsing(CalendarDate::format(withTime: true))->sortable(),
            ])
            ->filters([
                Filter::make('no_results')->label(__('admin.search_terms.filter.no_results'))
                    ->query(fn (Builder $query): Builder => $query->where('last_results', 0)),
                SelectFilter::make('locale')->label(__('admin.search_terms.field.locale'))
                    ->options(collect(Locales::all())->mapWithKeys(fn (string $l): array => [$l => Locales::nativeName($l)])->all()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSearchTerms::route('/'),
        ];
    }
}
