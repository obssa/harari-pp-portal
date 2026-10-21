<?php

namespace App\Filament\Resources\MediaContacts\Pages;

use App\Domain\Press\Actions\SaveMediaContact;
use App\Domain\Press\Models\MediaContact;
use App\Filament\Resources\MediaContacts\MediaContactResource;
use App\Filament\Support\CurrentUser;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditMediaContact extends EditRecord
{
    protected static string $resource = MediaContactResource::class;

    /**
     * Contact details are hidden from the model's array form; the form gets them here.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var MediaContact $contact */
        $contact = $this->getRecord();

        return [...$data, 'name' => $contact->name, 'email' => $contact->email, 'phone' => $contact->phone, 'notes' => $contact->notes];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var MediaContact $record */
        return app(SaveMediaContact::class)->handle($record, $data, CurrentUser::get());
    }

    protected function getRedirectUrl(): string
    {
        return MediaContactResource::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
