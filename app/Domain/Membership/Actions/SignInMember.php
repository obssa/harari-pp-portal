<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MembershipApplication;
use App\Services\Sms\SendSms;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Signing in to the member portal with the phone on the member's record
 * (PLAN.md §4, ADR-0049): a six-digit code by SMS, valid ten minutes, five
 * tries. The person is never told whether a number belongs to a member;
 * codes are only sent to members who may use the portal.
 */
final class SignInMember
{
    public const CODE_MINUTES = 10;

    public const ATTEMPTS = 5;

    public const CODES_PER_DAY = 5;

    public const RESEND_SECONDS = 60;

    /** Sends a code when the number is a member's; returns the keyed hash to remember in the session. */
    public function requestCode(string $typed, string $locale): string
    {
        $phone = PhoneNumber::normalise($typed);
        if ($phone === null) {
            throw ValidationException::withMessages(['phone' => __('events.registration.validation.phone')]);
        }
        $hash = MembershipApplication::hash($phone);
        $limit = 'member-sign-in:'.$hash;
        if (RateLimiter::tooManyAttempts($limit, self::CODES_PER_DAY)) {
            throw ValidationException::withMessages(['phone' => __('membership.validation.too_many_codes')]);
        }
        RateLimiter::hit($limit, 86400);

        $member = Member::query()->where('phone_hash', $hash)->whereNull('erased_at')->get()
            ->first(fn (Member $member): bool => $member->canUsePortal());
        if ($member === null) {
            return $hash;
        }

        $last = DB::table('member_sign_in_codes')->where('member_id', $member->id)->max('created_at');
        if ($last !== null && CarbonImmutable::parse((string) $last)->diffInSeconds(CarbonImmutable::now()) < self::RESEND_SECONDS) {
            throw ValidationException::withMessages(['phone' => __('membership.validation.wait', ['seconds' => self::RESEND_SECONDS])]);
        }

        $code = (string) random_int(100000, 999999);
        DB::table('member_sign_in_codes')->where('member_id', $member->id)->delete();
        DB::table('member_sign_in_codes')->insert([
            'member_id' => $member->id,
            'code_hash' => MembershipApplication::hash('sign-in:'.$member->id.':'.$code),
            'expires_at' => CarbonImmutable::now()->addMinutes(self::CODE_MINUTES),
            'attempts' => 0,
            'created_at' => CarbonImmutable::now(),
        ]);
        // Sent at once: the person is waiting for it.
        SendSms::dispatchSync((string) $member->phone, __('members.portal.sms', ['code' => $code, 'minutes' => self::CODE_MINUTES], $locale));

        return $hash;
    }

    /** The member the code belongs to, or a validation error. */
    public function verify(string $phoneHash, string $typed): Member
    {
        $code = (string) preg_replace('/\D/', '', $typed);
        $fail = fn () => ValidationException::withMessages(['code' => __('membership.validation.code')]);

        $members = Member::query()->where('phone_hash', $phoneHash)->whereNull('erased_at')->get()
            ->filter(fn (Member $member): bool => $member->canUsePortal());
        foreach ($members as $member) {
            $row = DB::table('member_sign_in_codes')->where('member_id', $member->id)->where('expires_at', '>', CarbonImmutable::now())->first();
            if ($row === null || $row->attempts >= self::ATTEMPTS) {
                continue;
            }
            if (hash_equals((string) $row->code_hash, MembershipApplication::hash('sign-in:'.$member->id.':'.$code))) {
                DB::table('member_sign_in_codes')->where('member_id', $member->id)->delete();
                activity('membership')->performedOn($member)->event('portal_sign_in')->log('Member signed in to the portal');

                return $member;
            }
            DB::table('member_sign_in_codes')->where('id', $row->id)->increment('attempts');
        }

        throw $fail();
    }

    /** Codes are only useful for ten minutes; anything older than a day is removed. */
    public static function purge(): int
    {
        return DB::table('member_sign_in_codes')->where('created_at', '<', CarbonImmutable::now()->subDay())->delete();
    }
}
