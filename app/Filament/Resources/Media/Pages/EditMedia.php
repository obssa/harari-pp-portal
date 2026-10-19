<?php

namespace App\Filament\Resources\Media\Pages;

use App\Domain\Media\Actions\DeleteMedia;
use App\Domain\Media\Models\Media;
use App\Domain\Translation\Support\Locales;
use App\Filament\Resources\Media\MediaResource;
use App\Filament\Resources\Media\Tables\MediaTable;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditMedia extends EditRecord
{
    protected static string $resource = MediaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('open')
                ->label(__('admin.media.action.open'))
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (Media $record): string => $record->getUrl(), shouldOpenInNewTab: true),
            DeleteAction::make()
                ->using(function (Media $record): bool {
                    app(DeleteMedia::class)->handle($record);

                    return true;
                }),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('alt', $data)) {
            $alt = [];
            foreach (Locales::all() as $locale) {
                $text = trim((string) ($data['alt'][$locale] ?? ''));
                if ($text !== '') {
                    $alt[$locale] = $text;
                }
            }
            $data['alt'] = $alt === [] ? null : $alt;
        }

        if (array_key_exists('tags', $data)) {
            $tags = MediaTable::cleanTags((array) $data['tags']);
            $data['tags'] = $tags === [] ? null : $tags;
        }

        $data['credit'] = filled($data['credit'] ?? null) ? trim((string) $data['credit']) : null;

        return $data;
    }
}
