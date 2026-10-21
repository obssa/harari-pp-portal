<?php

namespace App\Domain\Volunteers\Actions;

use App\Domain\Access\Support\OfficeScope;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MembershipApplication;
use App\Domain\Membership\Models\PersonNote;
use App\Domain\Notifications\Support\Recipients;
use App\Domain\Organisation\Enums\OfficeType;
use App\Domain\Organisation\Models\Office;
use App\Domain\Volunteers\Enums\VolunteerStatus;
use App\Domain\Volunteers\Models\Volunteer;
use App\Domain\Volunteers\Notifications\NewVolunteerNotice;
use App\Domain\Volunteers\Support\VolunteerOptions;
use App\Models\User;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Volunteer sign-ups from the public form and corrections by coordinators
 * (PLAN.md §4, §5; ADR-0048). A sign-up from a phone that is already a
 * member's is linked to that member. Coordinators of the woreda hear about
 * each sign-up.
 */
final class SaveVolunteer
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function signUp(array $input, string $locale): Volunteer
    {
        $values = $this->validate($input, '', true);
        if (! filter_var($input['consent'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            throw ValidationException::withMessages(['consent' => __('events.registration.validation.consent')]);
        }

        $volunteer = new Volunteer([...$values, 'locale' => $locale, 'status' => VolunteerStatus::New, 'source' => Volunteer::SOURCE_FORM,
            'consented_at' => CarbonImmutable::now(), 'member_id' => $this->memberId($values['phone_hash'])]);
        $volunteer->save();

        try {
            $coordinators = Recipients::withPermission('volunteers.view')
                ->filter(fn (User $user): bool => OfficeScope::covers($user, $volunteer->woreda_id, 'volunteers.view'));
            Recipients::notify($coordinators, NewVolunteerNotice::for($volunteer));
        } catch (Throwable $e) {
            report($e);
        }

        return $volunteer;
    }

    /**
     * Adds a volunteer by hand or corrects one; the history names the changed details, not their values.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Volunteer $volunteer, array $data, User $actor): Volunteer
    {
        $creating = ! $volunteer->exists;
        abort_unless($creating ? $actor->can('volunteers.manage') : OfficeScope::covers($actor, $volunteer->woreda_id, 'volunteers.manage'), 403);

        $values = $this->validate($data, 'data.', false);
        if (! OfficeScope::covers($actor, $values['woreda_id'], 'volunteers.manage')) {
            throw ValidationException::withMessages(['data.woreda_id' => __('members.validation.outside_scope')]);
        }

        $volunteer->fill($values);
        if ($creating) {
            $volunteer->forceFill(['status' => VolunteerStatus::Active, 'source' => Volunteer::SOURCE_STAFF, 'locale' => app()->getLocale()]);
        }
        if ($volunteer->isDirty('phone_hash')) {
            $volunteer->member_id = $this->memberId($values['phone_hash']);
        }
        $changed = array_values(array_intersect(['name', 'phone', 'email', 'woreda_id', 'skills', 'availability', 'languages'], array_keys($volunteer->getDirty())));
        $volunteer->save();

        if (! $creating && $changed !== []) {
            $volunteer->notes()->create(['user_id' => $actor->id, 'kind' => PersonNote::UPDATE,
                'body' => implode(', ', array_map(fn (string $field): string => __("volunteers.changed.{$field}"), $changed))]);
        }

        return $volunteer;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{name: string, phone: ?string, phone_hash: ?string, email: ?string, email_hash: ?string, woreda_id: ?string, skills: list<string>, availability: list<string>, languages: list<string>, message?: ?string}
     */
    private function validate(array $input, string $prefix, bool $public): array
    {
        $errors = [];
        $name = trim((string) preg_replace('/\s+/u', ' ', (string) ($input['name'] ?? '')));
        if ($name === '' || mb_strlen($name) > 120) {
            $errors["{$prefix}name"] = __('membership.validation.name');
        }

        $phone = null;
        if (filled($input['phone'] ?? null) && ($phone = PhoneNumber::normalise((string) $input['phone'])) === null) {
            $errors["{$prefix}phone"] = __('events.registration.validation.phone');
        }
        $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
        if ($email !== '' && (mb_strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            $errors["{$prefix}email"] = __('events.registration.validation.email');
        }
        if ($phone === null && ! isset($errors["{$prefix}phone"]) && ($public || $email === '')) {
            $errors["{$prefix}phone"] = __('volunteers.validation.contact');
        }

        $woreda = filled($input['woreda_id'] ?? null) ? (string) $input['woreda_id'] : null;
        if ($woreda !== null && ! Office::query()->whereKey($woreda)->where('type', OfficeType::Woreda->value)->exists()) {
            $errors["{$prefix}woreda_id"] = __('membership.validation.office');
        }

        $lists = [];
        foreach (['skills' => VolunteerOptions::SKILLS, 'availability' => VolunteerOptions::AVAILABILITY, 'languages' => VolunteerOptions::LANGUAGES] as $field => $allowed) {
            $chosen = array_values(array_unique(array_map('strval', (array) ($input[$field] ?? []))));
            if (array_diff($chosen, $allowed) !== [] || ($public && $chosen === [])) {
                $errors["{$prefix}{$field}"] = __("volunteers.validation.{$field}");
            }
            $lists[$field] = array_values(array_intersect($allowed, $chosen));
        }

        $message = trim((string) ($input['message'] ?? ''));
        if (mb_strlen($message) > 1000) {
            $errors["{$prefix}message"] = __('forms.validation.too_long', ['max' => 1000]);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'name' => $name,
            'phone' => $phone,
            'phone_hash' => $phone !== null ? MembershipApplication::hash($phone) : null,
            'email' => $email !== '' ? $email : null,
            'email_hash' => $email !== '' ? MembershipApplication::hash($email) : null,
            'woreda_id' => $woreda,
            ...$lists,
            ...($public ? ['message' => $message !== '' ? $message : null] : []),
        ];
    }

    private function memberId(?string $phoneHash): ?string
    {
        return $phoneHash === null ? null : Member::query()->where('phone_hash', $phoneHash)->whereNull('erased_at')->value('id');
    }
}
