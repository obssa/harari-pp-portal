<?php

namespace App\Filament\Resources\Members\Pages;

use App\Domain\Access\Support\OfficeScope;
use App\Domain\Membership\Actions\ManageMember;
use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Membership\Models\Member;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Support\CurrentUser;
use App\Services\Calendar\EthiopianCalendar;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewMember extends ViewRecord
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        $canUpdate = fn (): bool => OfficeScope::covers(CurrentUser::get(), $this->member()->woreda_id, 'members.update');

        return [
            EditAction::make()->visible($canUpdate),
            Action::make('renew')
                ->label(__('members.admin.renew'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('success')
                ->visible(fn (): bool => $canUpdate() && in_array($this->member()->status, [MemberStatus::Active, MemberStatus::Lapsed], true))
                ->requiresConfirmation()
                ->modalHeading(__('members.admin.renew_heading'))
                ->modalDescription(__('members.admin.renew_help', ['months' => Member::RENEWAL_MONTHS]))
                ->action(function (): void {
                    $due = app(ManageMember::class)->renew($this->member(), CurrentUser::get());
                    Notification::make()->success()->title(__('members.admin.renewed', ['date' => app(EthiopianCalendar::class)->format($due)]))->send();
                    $this->refreshFormData(['status', 'renewed_on', 'renewal_due_on']);
                }),
            Action::make('status')
                ->label(__('members.admin.change_status'))
                ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                ->color('gray')
                ->visible($canUpdate)
                ->fillForm(fn (): array => ['status' => $this->member()->status->value])
                ->schema([
                    Select::make('status')->label(__('membership.admin.status'))->required()
                        ->options(collect(MemberStatus::cases())->mapWithKeys(fn (MemberStatus $s): array => [$s->value => $s->label()])->all()),
                    Textarea::make('reason')->label(__('membership.admin.reason'))->helperText(__('members.admin.status_help'))->required()->rows(3)->maxLength(1000),
                ])
                ->action(function (array $data): void {
                    app(ManageMember::class)->changeStatus($this->member(), MemberStatus::from((string) $data['status']), (string) $data['reason'], CurrentUser::get());
                    Notification::make()->success()->title(__('members.admin.status_changed'))->send();
                    $this->refreshFormData(['status']);
                }),
            Action::make('note')
                ->label(__('membership.admin.note'))
                ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                ->color('gray')
                ->schema([Textarea::make('body')->label(__('membership.admin.note_text'))->helperText(__('members.admin.note_help'))->required()->rows(5)->maxLength(2000)])
                ->action(function (array $data): void {
                    app(ManageMember::class)->note($this->member(), (string) $data['body'], CurrentUser::get());
                    Notification::make()->success()->title(__('membership.admin.noted'))->send();
                }),
            Action::make('reveal')
                ->label(__('membership.admin.reveal'))
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->visible(fn (): bool => $this->member()->id_number !== null && $canUpdate())
                ->requiresConfirmation()
                ->modalDescription(__('membership.admin.reveal_help'))
                ->action(function (): void {
                    $number = app(ManageMember::class)->reveal($this->member(), CurrentUser::get());
                    Notification::make()->info()->title(__('membership.admin.revealed'))->body($number)->persistent()->send();
                }),
        ];
    }

    private function member(): Member
    {
        /** @var Member $member */
        $member = $this->getRecord();

        return $member;
    }
}
