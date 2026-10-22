<?php

namespace App\Filament\Resources\Forms\Pages;

use App\Domain\Forms\Actions\SaveForm;
use App\Domain\Forms\Models\Form;
use App\Filament\Resources\Forms\FormResource;
use App\Filament\Support\CurrentUser;
use App\Filament\Support\DirectoryFields;
use App\Filament\Support\ViewOnSite;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditForm extends EditRecord
{
    protected static string $resource = FormResource::class;

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
        /** @var Form $form */
        $form = $this->getRecord();
        $data['translations'] = DirectoryFields::fill($form, ['title', 'intro', 'success_message', 'autoreply_subject', 'autoreply_body']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Form $record */
        return app(SaveForm::class)->handle($record, $data, CurrentUser::get());
    }
}
