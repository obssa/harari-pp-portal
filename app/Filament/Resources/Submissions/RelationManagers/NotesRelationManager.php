<?php

namespace App\Filament\Resources\Submissions\RelationManagers;

use App\Domain\Forms\Enums\SubmissionStatus;
use App\Domain\Forms\Models\SubmissionNote;
use App\Filament\Support\CalendarDate;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A ticket's history: notes, replies, assignments and status changes.
 */
class NotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('forms.inbox.history');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->defaultSort('id', 'desc')
            ->paginated(false)
            ->columns([
                TextColumn::make('kind')->label(__('forms.inbox.kind'))->badge()
                    ->formatStateUsing(fn (string $state): string => __("forms.inbox.kind_{$state}"))
                    ->color(fn (string $state): string => match ($state) {
                        SubmissionNote::REPLY => 'success',
                        SubmissionNote::NOTE => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('body')->label(__('forms.inbox.text'))->wrap()
                    ->formatStateUsing(fn (?string $state, SubmissionNote $record): string => match ($record->kind) {
                        SubmissionNote::STATUS => SubmissionStatus::tryFrom((string) $state)?->label() ?? (string) $state,
                        SubmissionNote::ASSIGN => $state ?? __('forms.inbox.unassigned'),
                        default => (string) $state,
                    })
                    ->placeholder('—'),
                TextColumn::make('user.name')->label(__('admin.revisions.field.author'))->placeholder('—'),
                TextColumn::make('created_at')->label(__('admin.revisions.field.saved_at'))->formatStateUsing(CalendarDate::format(withTime: true)),
            ]);
    }
}
