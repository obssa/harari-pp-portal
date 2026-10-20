<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Domain\Documents\Actions\SaveDocument;
use App\Domain\Documents\Models\Document;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Support\CurrentUser;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateDocument extends CreateRecord
{
    protected static string $resource = DocumentResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveDocument::class)->handle(new Document, $data, CurrentUser::get());
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
