<?php

namespace App\Domain\Privacy\Actions;

use App\Domain\Engagement\Models\Subscriber;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Forms\Actions\SubmitForm;
use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Forms\Models\SubmissionNote;
use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MembershipApplication;
use App\Domain\Membership\Models\PersonNote;
use App\Domain\Privacy\Models\PrivacyRequest;
use App\Domain\Privacy\Support\PersonalDataSearch;
use App\Domain\Volunteers\Enums\VolunteerStatus;
use App\Domain\Volunteers\Models\Volunteer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Erases what the portal holds about a person (PLAN.md §11 deletion,
 * ADR-0041). Registrations and tickets stay as anonymous records, so event
 * numbers and inbox statistics still add up, but their name, contact
 * details, answers, attachments and the text of notes are removed, and
 * ticket links stop working. Membership applications and member records keep
 * only their reference or number, status, woreda and dates (ADR-0046); an
 * active member becomes resigned. Volunteers keep their woreda, status
 * (inactive) and the hours of their tasks. Newsletter subscriptions and
 * media contacts are deleted. Failed sign-ins forget the email tried.
 * Staff accounts are not touched here. Backups age out within their retention.
 */
final class ErasePersonalData
{
    public const ERASED = '—';

    /**
     * @return array{registrations: int, submissions: int, applications: int, members: int, volunteers: int, subscribers: int, media_contacts: int, failed_sign_ins: int, staff_account: int}
     */
    public function handle(PersonalDataSearch $search, User $actor): array
    {
        $counts = $search->counts();
        $registrations = $search->registrations();
        $submissions = $search->submissions();
        $failed = $search->failedSignIns();
        $applications = $search->applications();
        $members = $search->members();
        $volunteers = $search->volunteers();
        $subscribers = $search->subscribers();
        $mediaContacts = $search->mediaContacts();

        DB::transaction(function () use ($registrations, $submissions, $failed, $applications, $members, $volunteers, $subscribers, $mediaContacts): void {
            foreach ($registrations as $registration) {
                $this->registration($registration);
            }
            foreach ($submissions as $submission) {
                $this->submission($submission);
            }
            foreach ($applications as $application) {
                $this->person($application);
            }
            foreach ($members as $member) {
                $this->person($member);
            }
            foreach ($volunteers as $volunteer) {
                $this->volunteer($volunteer);
            }
            // Subscriptions are not records anyone counts: they go entirely.
            $subscribers->each(fn (Subscriber $subscriber) => $subscriber->delete());
            // Media contacts are business contacts: removed with the record of what they were sent.
            $mediaContacts->each(fn ($contact) => $contact->delete());
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

    private function person(MembershipApplication|Member $person): void
    {
        $person->notes()->whereNotNull('body')->each(
            fn (PersonNote $note) => $note->forceFill(['body' => self::ERASED])->saveQuietly(),
        );
        $person->forceFill([
            'name' => self::ERASED,
            'sex' => null,
            'birth_date' => null,
            'birth_year' => null,
            'id_type' => null,
            'id_number' => null,
            'id_hash' => null,
            'education' => null,
            'occupation' => null,
            'kebele_id' => null,
            'basic_org_id' => null,
            'address_note' => null,
            'phone' => null,
            'phone_hash' => null,
            'email' => null,
            'email_hash' => null,
            'erased_at' => now(),
        ]);
        if ($person instanceof MembershipApplication) {
            $person->forceFill(['reason' => null]);
        } elseif ($person->status === MemberStatus::Active || $person->status === MemberStatus::Lapsed) {
            $person->forceFill(['status' => MemberStatus::Resigned]);
        }
        $person->saveQuietly();
    }

    private function volunteer(Volunteer $volunteer): void
    {
        $volunteer->notes()->whereNotNull('body')->each(
            fn (PersonNote $note) => $note->forceFill(['body' => self::ERASED])->saveQuietly(),
        );
        $volunteer->forceFill([
            'name' => self::ERASED,
            'phone' => null,
            'phone_hash' => null,
            'email' => null,
            'email_hash' => null,
            'message' => null,
            'member_id' => null,
            'status' => VolunteerStatus::Inactive,
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
