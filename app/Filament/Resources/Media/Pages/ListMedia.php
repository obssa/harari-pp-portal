<?php

namespace App\Filament\Resources\Media\Pages;

use App\Domain\Media\Actions\UploadMedia;
use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Exceptions\RejectedUpload;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Models\MediaFolder;
use App\Filament\Resources\Media\MediaResource;
use App\Filament\Resources\Media\Widgets\MediaStorageOverview;
use App\Filament\Resources\MediaFolders\MediaFolderResource;
use App\Filament\Support\CurrentUser;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Http\UploadedFile;

class ListMedia extends ListRecords
{
    protected static string $resource = MediaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('folders')
                ->label(__('admin.media_folders.plural'))
                ->icon(Heroicon::OutlinedFolder)
                ->color('gray')
                ->url(fn (): string => MediaFolderResource::getUrl())
                ->visible(fn (): bool => MediaFolderResource::canViewAny()),
            self::uploadAction(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [MediaStorageOverview::class];
    }

    public static function uploadAction(): Action
    {
        return Action::make('upload')
            ->label(__('admin.media.action.upload'))
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->authorize(fn (): bool => CurrentUser::get()->can('create', Media::class))
            ->modalSubmitActionLabel(__('admin.media.action.upload'))
            ->schema([
                FileUpload::make('files')
                    ->label(__('admin.media.field.files'))
                    ->helperText(__('admin.media.help.upload', [
                        'image' => MediaKind::Image->maxBytes() / 1024 / 1024,
                        'document' => MediaKind::Document->maxBytes() / 1024 / 1024,
                        'video' => MediaKind::Video->maxBytes() / 1024 / 1024,
                    ]))
                    ->multiple()
                    ->maxFiles(20)
                    ->storeFiles(false)
                    ->acceptedFileTypes(MediaKind::acceptedMimeTypes())
                    ->maxSize(MediaKind::largestUploadKb())
                    ->required(),
                Select::make('folder_id')
                    ->label(__('admin.media.field.folder'))
                    ->options(fn (): array => MediaFolder::options())
                    ->placeholder(__('admin.media.folder.none')),
            ])
            ->action(function (array $data): void {
                $folder = filled($data['folder_id'] ?? null) ? MediaFolder::query()->find($data['folder_id']) : null;
                $uploader = CurrentUser::get();

                $added = 0;
                $refused = [];
                foreach ((array) ($data['files'] ?? []) as $file) {
                    if (! $file instanceof UploadedFile) {
                        continue;
                    }

                    try {
                        app(UploadMedia::class)->handle($file->getRealPath(), $file->getClientOriginalName(), $uploader, $folder);
                        $added++;
                    } catch (RejectedUpload $e) {
                        $refused[] = $e->userMessage($file->getClientOriginalName());
                    }
                }

                $notification = Notification::make()
                    ->title(__('admin.media.uploaded', ['count' => $added]))
                    ->body($refused === [] ? null : implode("\n", $refused))
                    ->status($refused === [] ? 'success' : ($added > 0 ? 'warning' : 'danger'));

                // Refusals stay on screen until read.
                ($refused === [] ? $notification : $notification->persistent())->send();
            });
    }
}
