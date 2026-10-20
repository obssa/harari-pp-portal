<?php

namespace App\Domain\Forms\Notifications;

use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Notifications\Enums\NoticeKind;
use App\Domain\Notifications\Notifications\StaffNotice;
use App\Filament\Resources\Submissions\SubmissionResource;
use App\Services\Calendar\EthiopianCalendar;
use Carbon\CarbonImmutable;

/**
 * A message or ticket arrived for an office the person handles (ADR-0036).
 * It names the form and the reference only; the sender's details stay in
 * the inbox.
 */
final class NewTicketNotice extends StaffNotice
{
    /**
     * @param  array<string, string>  $forms  the form's title per language
     * @param  array<string, string>  $offices  the office's name per language, empty without an office
     */
    public function __construct(
        public readonly string $submissionId,
        public readonly string $reference,
        public readonly array $forms,
        public readonly array $offices,
        public readonly ?string $dueAt,
    ) {}

    public static function for(FormSubmission $submission): self
    {
        $form = $submission->form;
        $office = $submission->office;

        return new self(
            $submission->id,
            $submission->reference,
            self::perLanguage(fn (string $locale): string => $form->title($locale)),
            $office === null ? [] : self::perLanguage(fn (string $locale): string => $office->name($locale)),
            $submission->due_at?->toIso8601String(),
        );
    }

    public function kind(): NoticeKind
    {
        return NoticeKind::Tickets;
    }

    protected function title(string $locale): string
    {
        return __('notifications.ticket.title', ['form' => self::pick($this->forms, $locale)], $locale);
    }

    protected function lines(string $locale): array
    {
        $lines = [__('notifications.ticket.reference', ['reference' => $this->reference], $locale)];
        if ($this->offices !== []) {
            $lines[] = __('notifications.ticket.office', ['office' => self::pick($this->offices, $locale)], $locale);
        }
        if ($this->dueAt !== null) {
            $lines[] = __('notifications.ticket.due', [
                'date' => app(EthiopianCalendar::class)->format(CarbonImmutable::parse($this->dueAt), $locale),
            ], $locale);
        }

        return $lines;
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
        return 'heroicon-o-inbox';
    }
}
