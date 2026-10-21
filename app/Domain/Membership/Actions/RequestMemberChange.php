<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Access\Support\OfficeScope;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\PersonNote;
use App\Domain\Membership\Notifications\MemberChangeRequestNotice;
use App\Domain\Membership\Support\ApplicationOptions;
use App\Domain\Notifications\Support\Recipients;
use App\Domain\Organisation\Enums\OfficeType;
use App\Domain\Organisation\Models\Office;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * A member asks from the portal for their details to be corrected (ADR-0049).
 * Nothing changes by itself: the request goes into the member's history and
 * the officers of the woreda are told; an officer checks it and edits the
 * record. Three requests a day at most.
 */
final class RequestMemberChange
{
    public const PER_DAY = 3;

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(Member $member, array $input): void
    {
        $lines = [];
        $errors = [];

        if (filled($input['phone'] ?? null)) {
            $phone = PhoneNumber::normalise((string) $input['phone']);
            if ($phone === null) {
                $errors['phone'] = __('events.registration.validation.phone');
            } else {
                $lines[] = __('members.portal.request_line.phone', ['value' => $phone]);
            }
        }

        $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
        if ($email !== '' && (mb_strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            $errors['email'] = __('events.registration.validation.email');
        } elseif ($email !== '') {
            $lines[] = __('members.portal.request_line.email', ['value' => $email]);
        }

        foreach (['woreda_id' => OfficeType::Woreda, 'kebele_id' => OfficeType::Kebele] as $field => $type) {
            if (blank($input[$field] ?? null)) {
                continue;
            }
            $office = Office::query()->whereKey((string) $input[$field])->where('type', $type->value)->first();
            if ($office === null) {
                $errors[$field] = __('membership.validation.office');
            } else {
                $lines[] = __("members.portal.request_line.{$field}", ['value' => $office->name()]);
            }
        }

        $education = (string) ($input['education'] ?? '');
        if ($education !== '' && ! in_array($education, ApplicationOptions::EDUCATION, true)) {
            $errors['education'] = __('membership.validation.education');
        } elseif ($education !== '') {
            $lines[] = __('members.portal.request_line.education', ['value' => ApplicationOptions::education()[$education]]);
        }

        foreach (['occupation' => 120, 'address_note' => 200, 'note' => 1000] as $field => $max) {
            $text = trim((string) ($input[$field] ?? ''));
            if (mb_strlen($text) > $max) {
                $errors[$field] = __('forms.validation.too_long', ['max' => $max]);
            } elseif ($text !== '') {
                $lines[] = __("members.portal.request_line.{$field}", ['value' => $text]);
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
        if ($lines === []) {
            throw ValidationException::withMessages(['note' => __('members.portal.request_empty')]);
        }
        $limit = 'member-request:'.$member->id;
        if (RateLimiter::tooManyAttempts($limit, self::PER_DAY)) {
            throw ValidationException::withMessages(['note' => __('members.portal.request_limit')]);
        }
        RateLimiter::hit($limit, 86400);

        $member->notes()->create(['user_id' => null, 'kind' => PersonNote::REQUEST, 'body' => implode("\n", $lines)]);

        try {
            $officers = Recipients::withPermission('members.update')
                ->filter(fn (User $user): bool => OfficeScope::covers($user, $member->woreda_id, 'members.update'));
            Recipients::notify($officers, MemberChangeRequestNotice::for($member));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
