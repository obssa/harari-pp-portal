<?php

namespace App\Domain\Privacy\Actions;

use App\Domain\Events\Models\EventRegistration;
use App\Domain\Forms\Actions\SubmitForm;
use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Forms\Models\SubmissionNote;
use App\Domain\Privacy\Models\PrivacyRequest;
use App\Domain\Privacy\Support\PersonalDataSearch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Erases what the portal holds about a person (PLAN.md §11 deletion,
 * ADR-0041). Registrations and tickets stay as anonymous records, so event
 * numbers and inbox statistics still add up, but their name, contact
 * details, answers, attachments and the text of notes are removed, and
 * ticket links stop working. Failed sign-ins forget the email tried. Staff
 * accounts are not touched here. Backups age out within their retention.
 */
final class ErasePersonalData
{
    public const ERASED = '—';

    /**
     * @return array{registrations: int, submissions: int, failed_sign_ins: int, staff_account: int}
     */
    public function handle(PersonalDataSearch $search, User $actor): array
    {
        $counts = $search->counts();
        $registrations = $search->registrations();
        $submissions = $search->submissions();
        $failed = $search->failedSignIns();

        DB::transaction(function () use ($registrations, $submissions, $failed): void {
            foreach ($registrations as $registration) {
                $this->registration($registration);
            }
            foreach ($submissions as $submission) {
                $this->submission($submission);
            }
            foreach ($failed as $activity) {
                $activity->properties = $activity->properties->except('email');
                $activity->save();
            }
        });

        PrivacyRequest::query()->create([
            'kind' => PrivacyRequest::ERASE,
            'subject_hash' => $search->subjectHash(),
            'found' => $counts,
            'handled_by' => $actor->id,
        ]);
        activity('privacy')->causedBy($actor)->event('erase')->withProperties(['found' => $counts])->log('Personal data erased');

        return $counts;
    }

    private function registration(EventRegistration $registration): void
    {
        $token = Str::random(40);
        $registration->forceFill([
            'name' => self::ERASED,
            'email' => null,
            'phone' => null,
            'email_hash' => null,
            'phone_hash' => null,
            'token' => $token,
            'token_hash' => EventRegistration::hash($token),
            'erased_at' => now(),
        ])->saveQuietly();
    }

    private function submission(FormSubmission $submission): void
    {
        foreach ($submission->files ?? [] as $file) {
            Storage::disk(SubmitForm::FILE_DISK)->delete($file['path']);
        }
        $submission->notes()->whereIn('kind', [SubmissionNote::REPLY, SubmissionNote::NOTE])->each(
            fn (SubmissionNote $note) => $note->forceFill(['body' => self::ERASED])->saveQuietly(),
        );
        $submission->forceFill([
            'name' => null,
            'email' => null,
            'phone' => null,
            'email_hash' => null,
            'phone_hash' => null,
            'answers' => [],
            'files' => null,
            'erased_at' => now(),
        ])->saveQuietly();
    }
}
