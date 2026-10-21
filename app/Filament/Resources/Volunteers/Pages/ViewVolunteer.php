<?php

namespace App\Filament\Resources\Volunteers\Pages;

use App\Domain\Access\Support\OfficeScope;
use App\Domain\Volunteers\Actions\ManageVolunteer;
use App\Domain\Volunteers\Enums\VolunteerStatus;
use App\Domain\Volunteers\Models\Volunteer;
use App\Filament\Resources\Volunteers\VolunteerResource;
use App\Filament\Support\CurrentUser;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewVolunteer extends ViewRecord
{
    protected static string $resource = VolunteerResource::class;

    protected function getHeaderActions(): array
    {
        $canManage = fn (): bool => OfficeScope::covers(CurrentUser::get(), $this->volunteer()->woreda_id, 'volunteers.manage');

        return [
            EditAction::make()->visible($canManage),
            Action::make('status')
                ->label(__('members.admin.change_status'))
                ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                ->color('gray')
                ->visible($canManage)
                ->fillForm(fn (): array => ['status' => $this->volunteer()->status->value])
                ->schema([
                    Select::make('status')->label(__('membership.admin.status'))->options(VolunteerStatus::options())->required(),
                    Textarea::make('reason')->label(__('volunteers.admin.status_note'))->rows(3)->maxLength(1000),
                ])
                ->action(function (array $data): void {
                    app(ManageVolunteer::class)->changeStatus($this->volunteer(), VolunteerStatus::from((string) $data['status']), (string) ($data['reason'] ?? ''), CurrentUser::get());
                    Notification::make()->success()->title(__('members.admin.status_changed'))->send();
                    $this->refreshFormData(['status']);
                }),
            Action::make('note')
                ->label(__('membership.admin.note'))
                ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                ->color('gray')
                ->schema([Textarea::make('body')->label(__('membership.admin.note_text'))->helperText(__('volunteers.admin.note_help'))->required()->rows(5)->maxLength(2000)])
                ->action(function (array $data): void {
                    app(ManageVolunteer::class)->note($this->volunteer(), (string) $data['body'], CurrentUser::get());
                    Notification::make()->success()->title(__('membership.admin.noted'))->send();
                }),
        ];
    }

    private function volunteer(): Volunteer
    {
        /** @var Volunteer $volunteer */
        $volunteer = $this->getRecord();

        return $volunteer;
    }
}
