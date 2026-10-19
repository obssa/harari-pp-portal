<?php

namespace App\Filament\Resources\Redirects;

use App\Domain\Content\Models\Redirect;
use App\Filament\Resources\Redirects\Pages\ManageRedirects;
use App\Filament\Support\CalendarDate;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Redirect manager (PLAN.md §7 Redirects & SEO): automatic redirects made
 * when a published address changes, plus hand-made ones.
 */
class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnRight;

    protected static ?int $navigationSort = 40;

    public static function getModelLabel(): string
    {
        return __('admin.redirects.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.redirects.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.content');
    }

    public static function form(Schema $schema): Schema
    {
        $path = fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || ! str_starts_with($value, '/') || str_starts_with($value, '//')) {
                $fail(__('admin.redirects.path_invalid'));
            }
        };

        return $schema->components([
            TextInput::make('from_path')
                ->label(__('admin.redirects.field.from'))
                ->helperText(__('admin.redirects.help.from'))
                ->required()
                ->maxLength(300)
                ->rules([$path])
                ->dehydrateStateUsing(fn (string $state): string => Redirect::normalise($state)),
            TextInput::make('to_path')
                ->label(__('admin.redirects.field.to'))
                ->helperText(__('admin.redirects.help.to'))
                ->required()
                ->maxLength(500)
                ->rules([$path])
                ->dehydrateStateUsing(fn (string $state): string => Redirect::normalise($state)),
            Radio::make('status')
                ->label(__('admin.redirects.field.status'))
                ->options([301 => __('admin.redirects.status.permanent'), 302 => __('admin.redirects.status.temporary')])
                ->default(301)
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('from_path')->label(__('admin.redirects.field.from'))->searchable()->limit(60),
                TextColumn::make('to_path')->label(__('admin.redirects.field.to'))->searchable()->limit(60),
                TextColumn::make('status')->label(__('admin.redirects.field.status'))->badge(),
                IconColumn::make('automatic')->label(__('admin.redirects.field.automatic'))->boolean(),
                TextColumn::make('hits')->label(__('admin.redirects.field.hits'))->numeric()->sortable(),
                TextColumn::make('last_hit_at')
                    ->label(__('admin.redirects.field.last_hit'))
                    ->formatStateUsing(CalendarDate::format(withTime: true))
                    ->placeholder('—'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRedirects::route('/')];
    }
}
