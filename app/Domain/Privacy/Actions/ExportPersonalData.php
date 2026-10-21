<?php

namespace App\Domain\Privacy\Actions;

use App\Domain\Donations\Models\Donation;
use App\Domain\Engagement\Models\Subscriber;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Forms\Models\SubmissionNote;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MembershipApplication;
use App\Domain\Membership\Models\PersonNote;
use App\Domain\Press\Models\MediaContact;
use App\Domain\Privacy\Models\PrivacyRequest;
use App\Domain\Privacy\Support\PersonalDataSearch;
use App\Domain\Volunteers\Models\Volunteer;
use App\Domain\Volunteers\Models\VolunteerAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
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
        $applications = $search->applications();
        $members = $search->members();
        $volunteers = $search->volunteers();
        $subscribers = $search->subscribers();
        $mediaContacts = $search->mediaContacts();
        $donations = $search->donations();

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
            'membership_applications' => $applications->map(fn (MembershipApplication $a): array => [
                'reference' => $a->reference,
                'status' => $a->status->value,
                'submitted_at' => $a->submitted_at?->toIso8601String(),
                'decided_at' => $a->decided_at?->toIso8601String(),
                'reason' => $a->reason,
                ...self::person($a),
                'language' => $a->locale,
                'history' => self::history($a->notes),
            ])->values()->all(),
            'membership' => $members->map(fn (Member $m): array => [
                'member_no' => $m->member_no,
                'status' => $m->status->value,
                'joined_on' => $m->joined_on->toDateString(),
                'renewed_on' => $m->renewed_on?->toDateString(),
                'renewal_due_on' => $m->renewal_due_on?->toDateString(),
                ...self::person($m),
                'history' => self::history($m->notes),
            ])->values()->all(),
            'volunteering' => $volunteers->map(fn (Volunteer $v): array => [
                'signed_up_at' => $v->created_at->toIso8601String(),
                'status' => $v->status->value,
                'name' => $v->name,
                'phone' => $v->phone,
                'email' => $v->email,
                'woreda' => $v->woreda?->name(),
                'skills' => $v->skills,
                'availability' => $v->availability,
                'languages' => $v->languages,
                'message' => $v->message,
                'assignments' => $v->assignments->map(fn (VolunteerAssignment $a): array => [
                    'task' => $a->task, 'event' => $a->event?->title(), 'on' => $a->on_date?->toDateString(), 'status' => $a->status->value, 'hours' => $a->hours,
                ])->values()->all(),
                'history' => self::history($v->notes),
            ])->values()->all(),
            'newsletter' => $subscribers->map(fn (Subscriber $s): array => [
                'email' => $s->email,
                'phone' => $s->phone,
                'channels' => $s->channels,
                'topics' => $s->topics,
                'language' => $s->locale,
                'signed_up_at' => $s->created_at->toIso8601String(),
                'email_confirmed_at' => $s->email_confirmed_at?->toIso8601String(),
                'phone_confirmed_at' => $s->phone_confirmed_at?->toIso8601String(),
                'unsubscribed_at' => $s->unsubscribed_at?->toIso8601String(),
            ])->values()->all(),
            'media_contact' => $mediaContacts->map(fn (MediaContact $c): array => [
                'name' => $c->name, 'outlet' => $c->outlet, 'position' => $c->position, 'email' => $c->email, 'phone' => $c->phone,
                'languages' => $c->languages, 'notes' => $c->notes, 'opted_out_at' => $c->opted_out_at?->toIso8601String(),
                'press_releases_received' => $c->distributions()->where('status', 'sent')->count(),
            ])->values()->all(),
            'donations' => $donations->map(fn (Donation $d): array => [
                'reference' => $d->reference, 'name' => $d->name, 'email' => $d->email, 'phone' => $d->phone,
                'amount' => $d->amount, 'currency' => $d->currency, 'purpose' => $d->purpose, 'anonymous' => $d->anonymous,
                'status' => $d->status->value, 'received_amount' => $d->received_amount, 'received_on' => $d->received_on?->toDateString(),
                'receipt_no' => $d->receipt_no, 'pledged_at' => $d->created_at->toIso8601String(),
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

    /**
     * @return array<string, mixed>
     */
    private static function person(MembershipApplication|Member $person): array
    {
        return [
            'name' => $person->name,
            'sex' => $person->sex,
            'birth_date' => $person->birth_date,
            'id_type' => $person->id_type,
            'id_number' => $person->id_number,
            'education' => $person->education,
            'occupation' => $person->occupation,
            'woreda' => $person->woreda?->name(),
            'kebele' => $person->kebele?->name(),
            'basic_organisation' => $person->basicOrganisation?->name(),
            'address' => $person->address_note,
            'phone' => $person->phone,
            'email' => $person->email,
        ];
    }

    /**
     * @param  Collection<int, PersonNote>  $notes
     * @return list<array{kind: string, at: string, text: ?string}>
     */
    private static function history(Collection $notes): array
    {
        return $notes->sortBy('id')->map(fn (PersonNote $n): array => ['kind' => $n->kind, 'at' => $n->created_at->toIso8601String(), 'text' => $n->body])->values()->all();
    }
}
