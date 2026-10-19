<?php

namespace App\Filament\Resources\Posts\RelationManagers;

use App\Domain\Content\Actions\RestoreRevision;
use App\Domain\Content\Contracts\TranslatableModel;
use App\Domain\Content\Models\Revision;
use App\Domain\Content\Support\BodyRenderer;
use App\Domain\Content\Support\TextDiff;
use App\Domain\Translation\Support\Locales;
use App\Filament\Support\CalendarDate;
use App\Filament\Support\CurrentUser;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Versions of each language of a post: who saved what and when, a
 * comparison with the current text, and restore (PLAN.md §7).
 */
class RevisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'revisions';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.revisions.title');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('author'))
            ->columns([
                TextColumn::make('locale')
                    ->label(__('admin.revisions.field.language'))
                    ->formatStateUsing(fn (?string $state): string => $state ? Locales::nativeName($state) : '—')
                    ->badge(),
                TextColumn::make('data.title')
                    ->label(__('admin.posts.field.title'))
                    ->limit(60),
                TextColumn::make('note')
                    ->label(__('admin.revisions.field.note'))
                    ->placeholder('—'),
                TextColumn::make('author.name')
                    ->label(__('admin.revisions.field.author'))
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label(__('admin.revisions.field.saved_at'))
                    ->formatStateUsing(CalendarDate::format(withTime: true)),
            ])
            ->filters([
                SelectFilter::make('locale')
                    ->label(__('admin.revisions.field.language'))
                    ->options(collect(Locales::all())->mapWithKeys(fn (string $l): array => [$l => Locales::nativeName($l)])->all()),
            ])
            ->recordActions([
                Action::make('compare')
                    ->label(__('admin.revisions.action.compare'))
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->color('gray')
                    ->modalHeading(__('admin.revisions.compare_heading'))
                    ->modalSubmitAction(false)
                    ->modalContent(fn (Revision $record): View => view('filament.revisions.compare', [
                        'changes' => $this->changes($record),
                    ])),
                Action::make('restore')
                    ->label(__('admin.revisions.action.restore'))
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->requiresConfirmation()
                    ->modalDescription(__('admin.revisions.restore_help'))
                    ->authorize(fn (): bool => CurrentUser::get()->can('update', $this->getOwnerRecord()))
                    ->action(function (Revision $record): void {
                        app(RestoreRevision::class)->handle($record);
                        Notification::make()->success()->title(__('admin.revisions.restored'))->send();
                        $this->redirect(request()->header('Referer') ?? url()->current());
                    }),
            ]);
    }

    /**
     * Field-by-field differences between a version and the current text.
     *
     * @return list<array{label: string, lines: list<array{type: string, text: string}>}>
     */
    private function changes(Revision $revision): array
    {
        $owner = $this->getOwnerRecord();
        $current = $owner instanceof TranslatableModel && $revision->locale !== null
            ? $owner->translation($revision->locale, fallback: false)
            : null;

        $text = function (string $field, mixed $value): string {
            return $field === 'body' ? BodyRenderer::toText(is_array($value) ? $value : null) : (string) $value;
        };

        $changes = [];
        foreach (['title', 'kicker', 'excerpt', 'body', 'seo_title', 'seo_description'] as $field) {
            $before = $text($field, $revision->data[$field] ?? null);
            $after = $text($field, $current?->getAttribute($field));
            if (TextDiff::changed($before, $after)) {
                $changes[] = [
                    'label' => __("admin.posts.field.{$field}"),
                    'lines' => TextDiff::lines($before, $after),
                ];
            }
        }

        return $changes;
    }
}
