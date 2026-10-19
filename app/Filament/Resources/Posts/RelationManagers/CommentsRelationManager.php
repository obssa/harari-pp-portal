<?php

namespace App\Filament\Resources\Posts\RelationManagers;

use App\Domain\Content\Models\Comment;
use App\Domain\Translation\Support\Locales;
use App\Filament\Support\CalendarDate;
use App\Filament\Support\CurrentUser;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Internal notes on a post (PLAN.md §7), including the reasons a language
 * was sent back. Never shown on the public site.
 */
class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.comments.title');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('locale')
                ->label(__('admin.revisions.field.language'))
                ->options(collect(Locales::all())->mapWithKeys(fn (string $l): array => [$l => Locales::nativeName($l)])->all())
                ->placeholder(__('admin.comments.all_languages')),
            Textarea::make('body')
                ->label(__('admin.comments.field.body'))
                ->required()
                ->rows(3)
                ->maxLength(2000),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('author'))
            ->columns([
                TextColumn::make('body')
                    ->label(__('admin.comments.field.body'))
                    ->wrap(),
                TextColumn::make('kind')
                    ->label(__('admin.comments.field.kind'))
                    ->formatStateUsing(fn (string $state): string => __("admin.comments.kind.{$state}"))
                    ->badge()
                    ->color(fn (string $state): string => $state === Comment::KIND_SEND_BACK ? 'danger' : 'gray'),
                TextColumn::make('locale')
                    ->label(__('admin.revisions.field.language'))
                    ->formatStateUsing(fn (?string $state): string => $state ? Locales::nativeName($state) : '—'),
                TextColumn::make('author.name')
                    ->label(__('admin.revisions.field.author'))
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label(__('admin.revisions.field.saved_at'))
                    ->formatStateUsing(CalendarDate::format(withTime: true)),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('admin.comments.action.add'))
                    ->authorize(fn (): bool => CurrentUser::get()->can('update', $this->getOwnerRecord()))
                    ->mutateDataUsing(fn (array $data): array => [
                        ...$data,
                        'kind' => Comment::KIND_COMMENT,
                    ])
                    ->after(fn (Comment $record) => $record->author()->associate(CurrentUser::get())->save()),
            ]);
    }
}
