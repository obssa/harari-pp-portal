<?php

namespace App\Filament\Resources\MembershipApplications\Pages;

use App\Domain\Access\Support\OfficeScope;
use App\Domain\Membership\Actions\ReviewApplication;
use App\Domain\Membership\Enums\ApplicationStatus;
use App\Domain\Membership\Models\MembershipApplication;
use App\Domain\Membership\Support\DuplicateFinder;
use App\Domain\Organisation\Enums\OfficeType;
use App\Domain\Organisation\Models\Office;
use App\Filament\Resources\MembershipApplications\MembershipApplicationResource;
use App\Filament\Support\CurrentUser;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewMembershipApplication extends ViewRecord
{
    protected static string $resource = MembershipApplicationResource::class;

    protected function getHeaderActions(): array
    {
        $canReview = fn (): bool => OfficeScope::covers(CurrentUser::get(), $this->application()->woreda_id, 'applications.review');
        $open = fn (): bool => $this->application()->status === ApplicationStatus::Submitted;

        return [
            Action::make('approve')
                ->label(__('membership.admin.approve'))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->visible(fn (): bool => $open() && $canReview())
                ->requiresConfirmation()
                ->modalHeading(__('membership.admin.approve_heading'))
                ->modalDescription(fn (): string => DuplicateFinder::for($this->application()) === []
                    ? __('membership.admin.approve_help')
                    : __('membership.admin.approve_duplicates'))
                ->action(function (): void {
                    $member = app(ReviewApplication::class)->approve($this->application(), CurrentUser::get());
                    Notification::make()->success()->title(__('membership.admin.approved', ['number' => $member->member_no]))->send();
                    $this->refreshFormData(['status', 'decided_at', 'reason']);
                }),
            Action::make('reject')
                ->label(__('membership.admin.reject'))
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible(fn (): bool => $open() && $canReview())
                ->modalHeading(__('membership.admin.reject_heading'))
                ->schema([
                    Textarea::make('reason')->label(__('membership.admin.reason'))->helperText(__('membership.admin.reason_help'))
                        ->required()->rows(4)->maxLength(1000),
                ])
                ->action(function (array $data): void {
                    app(ReviewApplication::class)->reject($this->application(), (string) $data['reason'], CurrentUser::get());
                    Notification::make()->success()->title(__('membership.admin.rejected'))->send();
                    $this->refreshFormData(['status', 'decided_at', 'reason']);
                }),
            Action::make('assign')
                ->label(__('membership.admin.assign'))
                ->icon(Heroicon::OutlinedMapPin)
                ->color('gray')
                ->visible(fn (): bool => $open() && $canReview())
                ->fillForm(fn (): array => ['woreda_id' => $this->application()->woreda_id])
                ->modalDescription(__('membership.admin.assign_help'))
                ->schema([
                    Select::make('woreda_id')->label(__('membership.field.woreda_id'))->searchable()
                        ->options(fn (): array => Office::query()->where('type', OfficeType::Woreda->value)->with('translations')->get()
                            ->mapWithKeys(fn (Office $office): array => [$office->id => $office->name()])->all()),
                ])
                ->action(function (array $data): void {
                    $woreda = filled($data['woreda_id'] ?? null) ? (string) $data['woreda_id'] : null;
                    app(ReviewApplication::class)->assign($this->application(), $woreda, CurrentUser::get());
                    Notification::make()->success()->title(__('membership.admin.assigned'))->send();
                    // Handed to a woreda this officer does not cover: back to the queue.
                    if (! CurrentUser::get()->can('view', $this->application())) {
                        $this->redirect(MembershipApplicationResource::getUrl('index'));

                        return;
                    }
                    $this->refreshFormData(['woreda_id']);
                }),
            Action::make('note')
                ->label(__('membership.admin.note'))
                ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                ->color('gray')
                ->schema([Textarea::make('body')->label(__('membership.admin.note_text'))->helperText(__('membership.admin.note_help'))->required()->rows(5)->maxLength(2000)])
                ->action(function (array $data): void {
                    app(ReviewApplication::class)->note($this->application(), (string) $data['body'], CurrentUser::get());
                    Notification::make()->success()->title(__('membership.admin.noted'))->send();
                }),
            Action::make('reveal')
                ->label(__('membership.admin.reveal'))
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->visible(fn (): bool => $this->application()->id_number !== null && $canReview())
                ->requiresConfirmation()
                ->modalDescription(__('membership.admin.reveal_help'))
                ->action(function (): void {
                    $number = app(ReviewApplication::class)->reveal($this->application(), CurrentUser::get());
                    Notification::make()->info()->title(__('membership.admin.revealed'))->body($number)->persistent()->send();
                }),
        ];
    }

    private function application(): MembershipApplication
    {
        /** @var MembershipApplication $application */
        $application = $this->getRecord();

        return $application;
    }
}
