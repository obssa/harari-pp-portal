<?php

namespace App\Domain\Forms\Notifications;

use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Notifications\Enums\NoticeKind;
use App\Domain\Notifications\Notifications\StaffNotice;
use App\Filament\Resources\Submissions\SubmissionResource;
use App\Models\User;

/** A colleague gave the person a ticket to handle (ADR-0036). */
final class TicketAssignedNotice extends StaffNotice
{
    /**
     * @param  array<string, string>  $forms  the form's title per language
     */
    public function __construct(
        public readonly string $submissionId,
        public readonly string $reference,
        public readonly array $forms,
        public readonly string $actorName,
    ) {}

    public static function for(FormSubmission $submission, User $actor): self
    {
        $form = $submission->form;

        return new self(
            $submission->id,
            $submission->reference,
            self::perLanguage(fn (string $locale): string => $form->title($locale)),
            $actor->name,
        );
    }

    public function kind(): NoticeKind
    {
        return NoticeKind::Tickets;
    }

    protected function title(string $locale): string
    {
        return __('notifications.ticket.assigned_title', ['reference' => $this->reference], $locale);
    }

    protected function lines(string $locale): array
    {
        return [__('notifications.ticket.assigned_body', [
            'name' => $this->actorName,
            'reference' => $this->reference,
            'form' => self::pick($this->forms, $locale),
        ], $locale)];
    }

    protected function url(): string
    {
        return SubmissionResource::getUrl('view', ['record' => $this->submissionId], panel: 'admin');
    }

    protected function actionLabel(string $locale): string
    {
        return __('notifications.ticket.open', [], $locale);
    }

    protected function icon(): string
    {
        return 'heroicon-o-inbox-arrow-down';
    }
}
