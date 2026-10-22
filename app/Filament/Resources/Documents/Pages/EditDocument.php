<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Domain\Documents\Actions\SaveDocument;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentTranslation;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Support\CurrentUser;
use App\Filament\Support\DirectoryFields;
use App\Filament\Support\ViewOnSite;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditDocument extends EditRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [...ViewOnSite::header(), DeleteAction::make(), RestoreAction::make()];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Document $document */
        $document = $this->getRecord();
        $translations = DirectoryFields::fill($document, ['title', 'summary']);
        foreach ($document->translations()->get() as $translation) {
            /** @var DocumentTranslation $translation */
            $translations[$translation->locale]['file_id'] = $translation->file_id;
            $translations[$translation->locale]['version_note'] = $translation->version_note;
        }
        $data['translations'] = $translations;
        $data['related'] = $document->related()->pluck('documents.id')->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Document $record */
        return app(SaveDocument::class)->handle($record, $data, CurrentUser::get());
    }
}
