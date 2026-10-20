<?php

namespace App\Filament\Resources\Events\Pages;

use App\Domain\Events\Models\Event;
use App\Filament\Resources\Events\EventResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * An event for staff who may see it but not change it, such as membership
 * officers who look after the registrations (ADR-0025).
 */
class ViewEvent extends ViewRecord
{
    protected static string $resource = EventResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Event $event */
        $event = $this->getRecord();

        return EditEvent::formData($event, $data);
    }
}
