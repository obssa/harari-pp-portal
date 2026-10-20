<?php

namespace App\Domain\Forms\Actions;

use App\Domain\Forms\Enums\SubmissionStatus;
use App\Domain\Forms\Mail\SubmissionReplyMail;
use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Forms\Models\SubmissionNote;
use App\Domain\Forms\Support\InboxScope;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Working a ticket in the inbox (PLAN.md §7, ADR-0028): hand it to someone,
 * move it on, answer the person, or leave a note for colleagues. Every step
 * is written to the ticket's history.
 */
final class HandleSubmission
{
    public function assign(FormSubmission $submission, ?User $assignee, User $actor): void
    {
        abort_unless(InboxScope::covers($actor, $submission, 'inbox.assign'), 403);
        if ($assignee !== null && ! InboxScope::covers($assignee, $submission)) {
            throw ValidationException::withMessages(['assignee_id' => __('forms.inbox.validation.assignee')]);
        }

        $submission->assignee_id = $assignee?->id;
        if ($assignee !== null && $submission->status === SubmissionStatus::New) {
            $submission->status = SubmissionStatus::Assigned;
        }
        $submission->save();

        $this->log($submission, $actor, SubmissionNote::ASSIGN, $assignee?->name);
    }

    public function status(FormSubmission $submission, SubmissionStatus $status, User $actor): void
    {
        abort_unless(InboxScope::covers($actor, $submission, 'inbox.reply'), 403);
        if ($submission->status === $status) {
            return;
        }

        $submission->status = $status;
        $submission->resolved_at = $status === SubmissionStatus::Resolved ? CarbonImmutable::now() : null;
        $submission->save();

        $this->log($submission, $actor, SubmissionNote::STATUS, $status->value);
    }

    /**
     * Sends the answer to the person (when they gave an email address) and
     * stops the response clock.
     */
    public function reply(FormSubmission $submission, string $text, User $actor, bool $resolve = false): bool
    {
        abort_unless(InboxScope::covers($actor, $submission, 'inbox.reply'), 403);
        $text = trim($text);
        if ($text === '' || mb_strlen($text) > 5000) {
            throw ValidationException::withMessages(['body' => __('forms.inbox.validation.reply')]);
        }

        $sent = false;
        if ($submission->email !== null) {
            Mail::to($submission->email)->locale($submission->locale)->queue(new SubmissionReplyMail($submission, $text));
            $sent = true;
        }

        $submission->first_response_at ??= CarbonImmutable::now();
        if (in_array($submission->status, [SubmissionStatus::New, SubmissionStatus::Assigned], true)) {
            $submission->status = SubmissionStatus::InProgress;
        }
        if ($resolve) {
            $submission->status = SubmissionStatus::Resolved;
            $submission->resolved_at = CarbonImmutable::now();
        }
        $submission->save();

        $this->log($submission, $actor, SubmissionNote::REPLY, $text);

        return $sent;
    }

    public function note(FormSubmission $submission, string $text, User $actor): void
    {
        abort_unless(InboxScope::covers($actor, $submission), 403);
        $text = trim($text);
        if ($text === '' || mb_strlen($text) > 5000) {
            throw ValidationException::withMessages(['body' => __('forms.inbox.validation.reply')]);
        }

        $this->log($submission, $actor, SubmissionNote::NOTE, $text);
    }

    private function log(FormSubmission $submission, User $actor, string $kind, ?string $body): void
    {
        $submission->notes()->create(['user_id' => $actor->id, 'kind' => $kind, 'body' => $body]);
    }
}
