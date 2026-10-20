<?php

namespace App\Filament\Resources\Submissions\Pages;

use App\Domain\Forms\Actions\HandleSubmission;
use App\Domain\Forms\Actions\SubmitForm;
use App\Domain\Forms\Enums\SubmissionStatus;
use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Forms\Support\InboxScope;
use App\Filament\Resources\Submissions\SubmissionResource;
use App\Filament\Support\CurrentUser;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViewSubmission extends ViewRecord
{
    protected static string $resource = SubmissionResource::class;

    protected function getHeaderActions(): array
    {
        $submission = $this->submission();
        $actor = CurrentUser::get();

        $files = [];
        foreach ($submission->files ?? [] as $index => $file) {
            $files[] = Action::make("file{$index}")
                ->label($file['name'])
                ->icon(Heroicon::OutlinedPaperClip)
                ->action(fn (): StreamedResponse => $this->download($index));
        }

        return [
            Action::make('reply')
                ->label(__('forms.inbox.reply'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->visible(fn (): bool => InboxScope::covers($actor, $submission, 'inbox.reply'))
                ->modalDescription($submission->email !== null ? __('forms.inbox.reply_help', ['email' => $submission->email]) : __('forms.inbox.reply_no_email'))
                ->schema([
                    Textarea::make('body')->label(__('forms.inbox.reply_text'))->required()->rows(8)->maxLength(5000),
                    Toggle::make('resolve')->label(__('forms.inbox.reply_resolve')),
                ])
                ->action(function (array $data) use ($submission): void {
                    $sent = app(HandleSubmission::class)->reply($submission, (string) $data['body'], CurrentUser::get(), (bool) ($data['resolve'] ?? false));
                    Notification::make()->success()->title($sent ? __('forms.inbox.replied') : __('forms.inbox.recorded'))->send();
                    $this->refreshFormData(['status', 'resolved_at']);
                }),
            Action::make('note')
                ->label(__('forms.inbox.note'))
                ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                ->color('gray')
                ->schema([Textarea::make('body')->label(__('forms.inbox.note_text'))->helperText(__('forms.inbox.note_help'))->required()->rows(5)->maxLength(5000)])
                ->action(function (array $data) use ($submission): void {
                    app(HandleSubmission::class)->note($submission, (string) $data['body'], CurrentUser::get());
                    Notification::make()->success()->title(__('forms.inbox.noted'))->send();
                }),
            Action::make('assign')
                ->label(__('forms.inbox.assign'))
                ->icon(Heroicon::OutlinedUserPlus)
                ->color('gray')
                ->visible(fn (): bool => InboxScope::covers($actor, $submission, 'inbox.assign'))
                ->fillForm(['assignee_id' => $submission->assignee_id])
                ->schema([
                    Select::make('assignee_id')->label(__('forms.inbox.assignee'))->searchable()
                        ->options(fn (): array => User::permission('inbox.view')->orderBy('name')->get()
                            ->filter(fn (User $user): bool => InboxScope::covers($user, $submission))
                            ->mapWithKeys(fn (User $user): array => [$user->id => $user->name])->all()),
                ])
                ->action(function (array $data) use ($submission): void {
                    $assignee = filled($data['assignee_id'] ?? null) ? User::query()->find($data['assignee_id']) : null;
                    app(HandleSubmission::class)->assign($submission, $assignee, CurrentUser::get());
                    Notification::make()->success()->title(__('forms.inbox.assigned'))->send();
                    $this->refreshFormData(['status', 'assignee_id']);
                }),
            Action::make('status')
                ->label(__('forms.inbox.change_status'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->visible(fn (): bool => InboxScope::covers($actor, $submission, 'inbox.reply'))
                ->fillForm(['status' => $submission->status->value])
                ->schema([Select::make('status')->label(__('forms.status_label'))->options(SubmissionStatus::options())->required()])
                ->action(function (array $data) use ($submission): void {
                    app(HandleSubmission::class)->status($submission, SubmissionStatus::from((string) $data['status']), CurrentUser::get());
                    Notification::make()->success()->title(__('forms.inbox.status_changed'))->send();
                    $this->refreshFormData(['status', 'resolved_at']);
                }),
            ...($files === [] ? [] : [ActionGroup::make($files)->label(__('forms.inbox.attachments'))->icon(Heroicon::OutlinedPaperClip)->button()->color('gray')]),
        ];
    }

    private function submission(): FormSubmission
    {
        /** @var FormSubmission $submission */
        $submission = $this->getRecord();

        return $submission;
    }

    private function download(int $index): StreamedResponse
    {
        $submission = $this->submission();
        abort_unless(InboxScope::covers(CurrentUser::get(), $submission), 403);
        $file = ($submission->files ?? [])[$index] ?? null;
        abort_if($file === null || ! Storage::disk(SubmitForm::FILE_DISK)->exists($file['path']), 404);

        activity('forms')->performedOn($submission)->causedBy(CurrentUser::get())->event('downloaded')->log('Attachment downloaded');

        return Storage::disk(SubmitForm::FILE_DISK)->download($file['path'], $file['name'], [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
