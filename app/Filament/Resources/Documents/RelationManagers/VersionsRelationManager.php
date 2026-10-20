<?php

namespace App\Filament\Resources\Documents\RelationManagers;

use App\Domain\Documents\Actions\SaveDocument;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentVersion;
use App\Domain\Translation\Support\Locales;
use App\Filament\Support\CalendarDate;
use App\Filament\Support\CurrentUser;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Earlier files of a document, per language (ADR-0026): download one, or
 * make it current again.
 */
class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('documents.admin.versions');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['media', 'replacedBy']))
            ->defaultSort('replaced_at', 'desc')
            ->columns([
                TextColumn::make('locale')->label(__('admin.revisions.field.language'))
                    ->formatStateUsing(fn (string $state): string => Locales::nativeName($state)),
                TextColumn::make('version')->label(__('documents.admin.field.version'))
                    ->formatStateUsing(fn (int $state): string => __('documents.version', ['number' => $state])),
                TextColumn::make('note')->label(__('documents.admin.field.version_note'))->placeholder('—')->wrap(),
                TextColumn::make('media.file_name')->label(__('documents.admin.field.file'))->placeholder('—'),
                TextColumn::make('replaced_at')->label(__('documents.admin.field.replaced_at'))
                    ->formatStateUsing(CalendarDate::format(withTime: true)),
                TextColumn::make('replacedBy.name')->label(__('admin.revisions.field.author'))->placeholder('—'),
            ])
            ->recordActions([
                Action::make('download')
                    ->label(__('documents.download'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->visible(fn (DocumentVersion $record): bool => $record->media !== null)
                    ->url(fn (DocumentVersion $record): ?string => $record->media !== null
                        ? route('media.file', ['uuid' => $record->media->uuid, 'name' => $record->media->file_name])
                        : null, shouldOpenInNewTab: true),
                Action::make('restore')
                    ->label(__('documents.admin.restore'))
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->requiresConfirmation()
                    ->modalDescription(__('documents.admin.restore_help'))
                    ->visible(fn (DocumentVersion $record): bool => $record->media !== null && CurrentUser::get()->can('documents.manage'))
                    ->action(function (DocumentVersion $record): void {
                        abort_unless(CurrentUser::get()->can('documents.manage'), 403);
                        /** @var Document $document */
                        $document = $this->getOwnerRecord();
                        app(SaveDocument::class)->restore($document, $record->id, CurrentUser::get());
                        Notification::make()->success()->title(__('documents.admin.restored'))->send();
                    }),
            ]);
    }
}
