<?php

namespace App\Filament\Resources\Members\Pages;

use App\Domain\Access\Support\OfficeScope;
use App\Domain\Membership\Actions\ManageMember;
use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Support\ApplicationOptions;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Support\CurrentUser;
use App\Services\Calendar\EthiopianCalendar;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
            // ADR-0080: members sign in with their phone number and a password; officers set one when it is missing or forgotten.
            Action::make('setPassword')
                ->label(fn (): string => $this->member()->hasPassword() ? __('members.admin.reset_password') : __('members.admin.set_password'))
                ->icon(Heroicon::OutlinedKey)
                ->color('gray')
                ->visible(fn (): bool => $canUpdate() && $this->member()->phone !== null)
                ->modalDescription(__('members.admin.password_help'))
                ->schema([
                    TextInput::make('password')->label(__('membership.field.password'))->password()->revealable()->required()->maxLength(120)
                        ->helperText(__('membership.help.password', ['min' => ApplicationOptions::PASSWORD_MIN])),
                    TextInput::make('password_confirmation')->label(__('membership.field.password_confirm'))->password()->revealable()->required()->maxLength(120),
                ])
                ->action(function (array $data): void {
                    app(ManageMember::class)->setPassword($this->member(), (string) $data['password'], (string) $data['password_confirmation'], CurrentUser::get());
                    Notification::make()->success()->title(__('members.admin.password_saved'))->send();
                }),
            Action::make('signOutApps')
                ->label(__('members.admin.sign_out_apps'))
                ->icon(Heroicon::OutlinedDevicePhoneMobile)
                ->color('gray')
                ->visible(fn (): bool => $canUpdate() && $this->member()->appTokens()->exists())
                ->requiresConfirmation()
                ->modalDescription(fn (): string => __('members.admin.sign_out_apps_help', ['count' => $this->member()->appTokens()->count()]))
                ->action(function (): void {
                    app(ManageMember::class)->signOutApps($this->member(), CurrentUser::get());
                    Notification::make()->success()->title(__('members.admin.apps_signed_out'))->send();
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
