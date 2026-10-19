<?php

namespace App\Filament\Resources\Events\Pages;

use App\Domain\Events\Actions\SaveEvent;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventTranslation;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Support\CurrentUser;
use App\Filament\Support\DirectoryFields;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make(), RestoreAction::make()];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Event $event */
        $event = $this->getRecord();

        return self::formData($event, $data);
    }

    /**
     * The form's languages, agendas and speakers, also for the view page.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function formData(Event $event, array $data): array
    {
        $translations = DirectoryFields::fill($event, ['title', 'summary', 'venue', 'address']);
        foreach ($event->translations()->get() as $translation) {
            /** @var EventTranslation $translation */
            $translations[$translation->locale]['agenda'] = $translation->agenda ?? [];
        }
        $data['translations'] = $translations;
        $data['speakers'] = $event->speakers()->pluck('leaders.id')->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Event $record */
        return app(SaveEvent::class)->handle($record, $data, CurrentUser::get());
    }
}
