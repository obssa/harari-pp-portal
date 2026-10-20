<?php

namespace App\Domain\Privacy\Actions;

use App\Domain\Events\Models\EventRegistration;
use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Forms\Models\SubmissionNote;
use App\Domain\Privacy\Models\PrivacyRequest;
use App\Domain\Privacy\Support\PersonalDataSearch;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

/**
 * A copy of what the portal holds about a person, as JSON for them
 * (PLAN.md §11 data export, ADR-0041). Uploaded attachments are listed by
 * name; the request is logged without the person's details.
 */
final class ExportPersonalData
{
    /**
     * @return array<string, mixed>
     */
    public function handle(PersonalDataSearch $search, User $actor): array
    {
        $registrations = $search->registrations();
        $submissions = $search->submissions();
        $failed = $search->failedSignIns();

        $export = [
            'exported_at' => now()->toIso8601String(),
            'searched_for' => array_filter(['email' => $search->email, 'phone' => $search->phone]),
            'event_registrations' => $registrations->map(fn (EventRegistration $r): array => [
                'event' => $r->event->title(),
                'event_starts_at' => $r->event->starts_at->toIso8601String(),
                'name' => $r->name,
                'email' => $r->email,
                'phone' => $r->phone,
                'language' => $r->locale,
                'status' => $r->status->value,
                'registered_at' => $r->created_at->toIso8601String(),
                'checked_in_at' => $r->checked_in_at?->toIso8601String(),
                'cancelled_at' => $r->cancelled_at?->toIso8601String(),
            ])->values()->all(),
            'messages_and_tickets' => $submissions->map(fn (FormSubmission $s): array => [
                'reference' => $s->reference,
                'form' => $s->form->title(),
                'sent_at' => $s->created_at->toIso8601String(),
                'status' => $s->status->value,
                'name' => $s->name,
                'email' => $s->email,
                'phone' => $s->phone,
                'answers' => $s->answers,
                'attachments' => array_map(fn (array $file): string => $file['name'], $s->files ?? []),
                'history' => $s->notes
                    ->filter(fn (SubmissionNote $n): bool => in_array($n->kind, [SubmissionNote::REPLY, SubmissionNote::NOTE], true))
                    ->map(fn (SubmissionNote $n): array => ['kind' => $n->kind, 'at' => $n->created_at->toIso8601String(), 'text' => $n->body])
                    ->values()->all(),
            ])->values()->all(),
            'failed_sign_ins' => $failed->map(fn (Activity $a): array => ['at' => $a->created_at?->toIso8601String()])->values()->all(),
            'staff_account' => $search->staffAccount() !== null,
        ];

        PrivacyRequest::query()->create([
            'kind' => PrivacyRequest::EXPORT,
            'subject_hash' => $search->subjectHash(),
            'found' => $search->counts(),
            'handled_by' => $actor->id,
        ]);
        activity('privacy')->causedBy($actor)->event('export')->withProperties(['found' => $search->counts()])->log('Personal data exported');

        return $export;
    }
}
