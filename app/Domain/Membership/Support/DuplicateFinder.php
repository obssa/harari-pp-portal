<?php

namespace App\Domain\Membership\Support;

use App\Domain\Membership\Enums\ApplicationStatus;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MembershipApplication;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Possible duplicates of an application among the other applications and
 * the members (PLAN.md §5, ADR-0045): the same ID number, phone or email
 * (through their keyed hashes), or the same name and date of birth. Names
 * are encrypted, so only people with the same birth year and sex are
 * decrypted and compared.
 */
final class DuplicateFinder
{
    /**
     * @return list<array{type: string, id: string, label: string, reasons: list<string>}>
     */
    public static function for(MembershipApplication $application): array
    {
        if (! self::searchable($application)) {
            return [];
        }

        $matches = [];
        $applications = MembershipApplication::query()
            ->whereKeyNot($application->id)
            ->whereNull('erased_at')
            ->where('status', '!=', ApplicationStatus::Started->value)
            ->where(fn (Builder $q) => self::candidates($q, $application))
            ->limit(50)->get();
        foreach ($applications as $other) {
            $reasons = self::reasons($application, $other);
            if ($reasons !== []) {
                $matches[] = ['type' => 'application', 'id' => $other->id, 'label' => $other->reference.' · '.$other->status->label(), 'reasons' => $reasons];
            }
        }

        $members = Member::query()->whereNull('erased_at')
            ->where(fn (Builder $q) => $q->whereNull('application_id')->orWhere('application_id', '!=', $application->id))
            ->where(fn (Builder $q) => self::candidates($q, $application))->limit(50)->get();
        foreach ($members as $member) {
            $reasons = self::reasons($application, $member);
            if ($reasons !== []) {
                $matches[] = ['type' => 'member', 'id' => $member->id, 'label' => $member->member_no.' · '.$member->status->label(), 'reasons' => $reasons];
            }
        }

        return $matches;
    }

    private static function searchable(MembershipApplication $application): bool
    {
        return $application->id_hash !== null || $application->phone_hash !== null || $application->email_hash !== null
            || ($application->birth_year !== null && $application->sex !== null);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     */
    private static function candidates(Builder $query, MembershipApplication $application): void
    {
        foreach (['id_hash', 'phone_hash', 'email_hash'] as $hash) {
            if ($application->{$hash} !== null) {
                $query->orWhere($hash, $application->{$hash});
            }
        }
        if ($application->birth_year !== null && $application->sex !== null) {
            $query->orWhere(fn (Builder $q) => $q->where('birth_year', $application->birth_year)->where('sex', $application->sex));
        }
    }

    /**
     * @return list<string>
     */
    private static function reasons(MembershipApplication $application, MembershipApplication|Member $other): array
    {
        $reasons = [];
        if ($application->id_hash !== null && $application->id_hash === $other->id_hash) {
            $reasons[] = __('membership.duplicate.id');
        }
        if ($application->phone_hash !== null && $application->phone_hash === $other->phone_hash) {
            $reasons[] = __('membership.duplicate.phone');
        }
        if ($application->email_hash !== null && $application->email_hash === $other->email_hash) {
            $reasons[] = __('membership.duplicate.email');
        }
        if ($application->birth_date !== null && $application->birth_date === $other->birth_date
            && self::sameName((string) $application->name, (string) $other->name)) {
            $reasons[] = __('membership.duplicate.name_birth');
        }

        return $reasons;
    }

    private static function sameName(string $a, string $b): bool
    {
        $normal = fn (string $name): string => Str::of($name)->lower()->squish()->toString();

        return $normal($a) !== '' && $normal($a) === $normal($b);
    }
}
