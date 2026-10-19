<?php

namespace App\Filament\Resources\Posts\Tables;

use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Enums\PostType;
use App\Domain\Content\Models\Category;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostTranslation;
use App\Domain\Translation\Support\Locales;
use App\Filament\Support\CalendarDate;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['translations', 'creator']))
            ->columns([
                TextColumn::make('title')
                    ->label(__('admin.posts.field.title'))
                    ->state(fn (Post $record): string => $record->title())
                    ->description(fn (Post $record): string => $record->type->label())
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'translations',
                        fn (Builder $q) => $q->where('title', 'like', "%{$search}%"),
                    ))
                    ->wrap(),
                TextColumn::make('languages')
                    ->label(__('admin.posts.field.languages'))
                    ->state(fn (Post $record): array => $record->translations
                        ->sortBy(fn (PostTranslation $t): int => (int) array_search($t->locale, Locales::all(), true))
                        ->map(fn (PostTranslation $t): string => $t->locale.':'.$t->status->value)
                        ->values()->all())
                    ->formatStateUsing(function (string $state): string {
                        [$locale, $status] = explode(':', $state);

                        return strtoupper($locale).' · '.(ContentStatus::tryFrom($status)?->label() ?? $status);
                    })
                    ->color(fn (string $state): string => ContentStatus::tryFrom(explode(':', $state)[1] ?? '')?->color() ?? 'gray')
                    ->badge(),
                IconColumn::make('is_featured')
                    ->label(__('admin.posts.field.is_featured'))
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('creator.name')
                    ->label(__('admin.posts.field.author'))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label(__('admin.posts.field.updated_at'))
                    ->formatStateUsing(CalendarDate::format(withTime: true))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('admin.posts.field.type'))
                    ->options(PostType::options()),
                SelectFilter::make('status')
                    ->label(__('admin.posts.field.status'))
                    ->options(ContentStatus::options(true))
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('translations', fn (Builder $q) => $q->where('status', $data['value']))
                        : $query),
                SelectFilter::make('missing')
                    ->label(__('admin.posts.filter.missing_language'))
                    ->options(collect(Locales::all())->mapWithKeys(fn (string $l): array => [$l => Locales::nativeName($l)])->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereDoesntHave('translations', fn (Builder $q) => $q->where('locale', $data['value']))
                        : $query),
                SelectFilter::make('category')
                    ->label(__('admin.categories.singular'))
                    ->options(fn (): array => Category::query()->with('translations')->get()
                        ->mapWithKeys(fn (Category $c): array => [$c->id => $c->name()])->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('categories', fn (Builder $q) => $q->whereKey($data['value']))
                        : $query),
                Filter::make('imported')
                    ->label(__('import.filter.imported'))
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('source_url')),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
