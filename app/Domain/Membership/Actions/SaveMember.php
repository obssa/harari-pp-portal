<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Access\Support\OfficeScope;
use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MembershipApplication;
use App\Domain\Membership\Models\PersonNote;
use App\Domain\Membership\Support\ApplicationOptions;
use App\Domain\Organisation\Enums\OfficeType;
use App\Domain\Organisation\Models\Office;
use App\Models\User;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Adds a member by hand or corrects a member's details (PLAN.md §5,
 * ADR-0046). The same checks as the application form; an officer scoped to
 * woredas can only place members in those woredas. The history records
 * which details changed, never the old or new values.
 */
final class SaveMember
{
    /** Fields whose change is named in the history. */
    private const TRACKED = ['name', 'sex', 'birth_date', 'id_type', 'id_number', 'education', 'occupation',
        'woreda_id', 'kebele_id', 'basic_org_id', 'address_note', 'phone', 'email', 'joined_on', 'renewal_due_on'];

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Member $member, array $data, User $actor): Member
    {
        $creating = ! $member->exists;
        abort_unless($creating ? $actor->can('members.create') : OfficeScope::covers($actor, $member->woreda_id, 'members.update'), 403);

        $values = $this->validate($member, $data, $actor);

        return DB::transaction(function () use ($member, $values, $actor, $creating): Member {
            $member->fill($values);
            $changed = array_values(array_intersect(self::TRACKED, array_keys($member->getDirty())));
            if ($member->isDirty('renewal_due_on')) {
                // A new due date gets its own reminder.
                $member->renewal_reminded_on = null;
            }
            if ($creating) {
                $member->member_no = Member::nextNumber();
                $member->status = MemberStatus::Active;
                $member->source = Member::SOURCE_MANUAL;
                $member->renewal_due_on ??= self::nextDue($member->joined_on);
            }
            $member->save();

            if (! $creating && $changed !== []) {
                $member->notes()->create(['user_id' => $actor->id, 'kind' => PersonNote::UPDATE,
                    'body' => implode(', ', array_map(fn (string $field): string => __("members.changed.{$field}"), $changed))]);
            }

            return $member;
        });
    }

    /** The first renewal date after today on the anniversary of joining. */
    public static function nextDue(CarbonImmutable $joined): CarbonImmutable
    {
        $due = $joined->addMonthsNoOverflow(Member::RENEWAL_MONTHS);
        while ($due->isPast() && ! $due->isToday()) {
            $due = $due->addMonthsNoOverflow(Member::RENEWAL_MONTHS);
        }

        return $due;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validate(Member $member, array $data, User $actor): array
    {
        $errors = [];
        $name = trim((string) preg_replace('/\s+/u', ' ', (string) ($data['name'] ?? '')));
        if ($name === '' || mb_strlen($name) > 120) {
            $errors['data.name'] = __('membership.validation.name');
        }
        $sex = (string) ($data['sex'] ?? '');
        if (! in_array($sex, ApplicationOptions::SEXES, true)) {
            $errors['data.sex'] = __('membership.validation.sex');
        }
        $birth = $this->date($data['birth_date'] ?? null);
        if ($birth === false || ($birth !== null && $birth->diffInYears(CarbonImmutable::now()) < ApplicationOptions::MINIMUM_AGE)) {
            $errors['data.birth_date'] = __('membership.validation.age', ['age' => ApplicationOptions::MINIMUM_AGE]);
        }

        $idType = (string) ($data['id_type'] ?? 'none');
        if (! in_array($idType, ApplicationOptions::ID_TYPES, true)) {
            $errors['data.id_type'] = __('membership.validation.id_type');
        }
        $typed = strtoupper((string) preg_replace('/[\s-]/', '', (string) ($data['id_number'] ?? '')));
        // On an edit an empty number keeps the one on file (the form never shows it in full).
        $idNumber = $idType === 'none' ? null : ($typed !== '' ? $typed : $member->id_number);
        if ($idType !== 'none' && ($idNumber === null || preg_match('/^[A-Z0-9]{4,30}$/', $idNumber) !== 1)) {
            $errors['data.id_number'] = __('membership.validation.id_number');
        }
        $idHash = $idNumber !== null ? MembershipApplication::hash($idType.':'.$idNumber) : null;
        if ($idHash !== null && ($other = Member::query()->where('id_hash', $idHash)->whereKeyNot($member->id)->whereNull('erased_at')->value('member_no')) !== null) {
            $errors['data.id_number'] = __('members.validation.duplicate_id', ['number' => $other]);
        }

        $education = filled($data['education'] ?? null) ? (string) $data['education'] : null;
        if ($education !== null && ! in_array($education, ApplicationOptions::EDUCATION, true)) {
            $errors['data.education'] = __('membership.validation.education');
        }
        $occupation = trim((string) ($data['occupation'] ?? ''));
        if (mb_strlen($occupation) > 120) {
            $errors['data.occupation'] = __('forms.validation.too_long', ['max' => 120]);
        }

        $offices = [];
        foreach (['woreda_id' => OfficeType::Woreda, 'kebele_id' => OfficeType::Kebele, 'basic_org_id' => OfficeType::BasicOrganisation] as $field => $type) {
            $id = filled($data[$field] ?? null) ? (string) $data[$field] : null;
            if ($id !== null && ! Office::query()->whereKey($id)->where('type', $type->value)->exists()) {
                $errors["data.{$field}"] = __('membership.validation.office');
            }
            $offices[$field] = $id;
        }
        if (! OfficeScope::covers($actor, $offices['woreda_id'], $member->exists ? 'members.update' : 'members.create')) {
            $errors['data.woreda_id'] = __('members.validation.outside_scope');
        }
        $note = trim((string) ($data['address_note'] ?? ''));
        if (mb_strlen($note) > 200) {
            $errors['data.address_note'] = __('forms.validation.too_long', ['max' => 200]);
        }

        $phone = null;
        if (filled($data['phone'] ?? null) && ($phone = PhoneNumber::normalise((string) $data['phone'])) === null) {
            $errors['data.phone'] = __('events.registration.validation.phone');
        }
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        if ($email !== '' && (mb_strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            $errors['data.email'] = __('events.registration.validation.email');
        }

        $joined = $this->date($data['joined_on'] ?? null);
        if (! $joined instanceof CarbonImmutable || $joined->isFuture()) {
            $errors['data.joined_on'] = __('members.validation.joined_on');
        }
        $due = $this->date($data['renewal_due_on'] ?? null);
        if ($due === false) {
            $errors['data.renewal_due_on'] = __('membership.validation.birth_date');
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        /** @var CarbonImmutable $joined */
        return [
            'name' => $name,
            'sex' => $sex,
            'birth_date' => $birth?->toDateString(),
            'birth_year' => $birth?->year,
            'id_type' => $idType,
            'id_number' => $idNumber,
            'id_hash' => $idHash,
            'education' => $education,
            'occupation' => $occupation !== '' ? $occupation : null,
            ...$offices,
            'address_note' => $note !== '' ? $note : null,
            'phone' => $phone,
            'phone_hash' => $phone !== null ? MembershipApplication::hash($phone) : null,
            'email' => $email !== '' ? $email : null,
            'email_hash' => $email !== '' ? MembershipApplication::hash($email) : null,
            'joined_on' => $joined->toDateString(),
            ...($due instanceof CarbonImmutable ? ['renewal_due_on' => $due->toDateString()] : []),
        ];
    }

    /** null when empty, false when not a date. */
    private function date(mixed $value): CarbonImmutable|false|null
    {
        if (blank($value)) {
            return null;
        }
        try {
            return CarbonImmutable::parse((string) $value)->startOfDay();
        } catch (\Throwable) {
            return false;
        }
    }
}
