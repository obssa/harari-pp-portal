<?php

namespace App\Filament\Resources\Forms\Pages;

use App\Domain\Forms\Actions\SaveForm;
use App\Domain\Forms\Models\Form;
use App\Filament\Resources\Forms\FormResource;
use App\Filament\Support\CurrentUser;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateForm extends CreateRecord
{
    protected static string $resource = FormResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveForm::class)->handle(new Form, $data, CurrentUser::get());
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
