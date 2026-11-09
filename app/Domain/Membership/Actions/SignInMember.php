<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Membership\Enums\ApplicationStatus;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MembershipApplication;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Signing in to the member portal and the member app (ADR-0080, replacing
 * the SMS code of ADR-0049): the phone number on the member's record and the
 * password chosen on the application or set by an officer. Only members who
 * may use the portal get in; a wrong number and a wrong password get the same
 * answer, and five failures for a number stop it for fifteen minutes.
 */
final class SignInMember
{
    public const ATTEMPTS = 5;

    public const LOCK_MINUTES = 15;

    /** The member the phone and password belong to, or a validation error on 'phone'. */
    public function attempt(string $typedPhone, string $password): Member
    {
        $phone = PhoneNumber::normalise($typedPhone);
        if ($phone === null) {
            throw ValidationException::withMessages(['phone' => __('membership.validation.phone_invalid')]);
        }
        $hash = MembershipApplication::hash($phone);
        $limit = 'member-sign-in:'.$hash;
        if (RateLimiter::tooManyAttempts($limit, self::ATTEMPTS)) {
            throw ValidationException::withMessages(['phone' => __('members.portal.too_many', ['minutes' => self::LOCK_MINUTES])]);
        }

        if ($password !== '') {
            // Several members may share one phone (a family); the password says which.
            $member = Member::query()->where('phone_hash', $hash)->whereNull('erased_at')->get()
                ->first(fn (Member $member): bool => $member->canUsePortal() && $member->hasPassword() && Hash::check($password, (string) $member->password));
            if ($member !== null) {
                RateLimiter::clear($limit);
                activity('membership')->performedOn($member)->event('portal_sign_in')->log('Member signed in to the portal');

                return $member;
            }
        }
        RateLimiter::hit($limit, self::LOCK_MINUTES * 60);

        // Only someone who knows the password learns that the application is still with the officers.
        $pending = $password !== '' && MembershipApplication::query()->where('phone_hash', $hash)
            ->where('status', ApplicationStatus::Submitted->value)->whereNull('erased_at')->whereNotNull('password')->get()
            ->contains(fn (MembershipApplication $application): bool => Hash::check($password, (string) $application->password));

        throw ValidationException::withMessages(['phone' => __($pending ? 'members.portal.pending' : 'members.portal.wrong')]);
    }
}
