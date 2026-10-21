<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MembershipApplication;
use App\Domain\Membership\Models\PersonNote;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Which member a Fayda sign-in belongs to (ADR-0058). eSignet gives the
 * portal its own identifier for the person, not the Fayda number, so a
 * member is linked once: from the portal while signed in, or on the first
 * Fayda sign-in through the phone Fayda holds when it is the phone of
 * exactly one member. Later sign-ins use the link.
 */
final class SignInWithFayda
{
    public static function subjectHash(string $sub): string
    {
        return MembershipApplication::hash('fayda:'.$sub);
    }

    /**
     * @param  array{sub: string, phone_number: ?string, phone_number_verified: ?bool}  $claims
     */
    public function member(array $claims): ?Member
    {
        $linked = Member::query()->where('fayda_sub_hash', self::subjectHash($claims['sub']))->whereNull('erased_at')->first();
        if ($linked !== null) {
            return $linked->canUsePortal() ? $linked : null;
        }

        $phone = $claims['phone_number'] !== null && $claims['phone_number_verified'] !== false
            ? PhoneNumber::normalise($claims['phone_number'])
            : null;
        if ($phone === null) {
            return null;
        }

        $candidates = Member::query()
            ->where('phone_hash', MembershipApplication::hash($phone))
            ->whereNull('fayda_sub_hash')
            ->whereNull('erased_at')
            ->get()
            ->filter(fn (Member $member): bool => $member->canUsePortal());
        if ($candidates->count() !== 1) {
            return null;
        }

        $member = $candidates->sole();
        $this->link($member, $claims['sub']);

        return $member;
    }

    /** Links the Fayda identity to the member; one identity belongs to one member. */
    public function link(Member $member, string $sub): void
    {
        $hash = self::subjectHash($sub);
        if (Member::query()->where('fayda_sub_hash', $hash)->whereKeyNot($member->id)->exists()) {
            throw ValidationException::withMessages(['fayda' => __('members.fayda.taken')]);
        }
        if ($member->fayda_sub_hash === $hash) {
            return;
        }

        $member->forceFill(['fayda_sub_hash' => $hash, 'fayda_linked_at' => CarbonImmutable::now()])->save();
        $member->notes()->create(['user_id' => null, 'kind' => PersonNote::UPDATE, 'body' => __('members.note.fayda_linked')]);
        activity('membership')->performedOn($member)->event('fayda_linked')->log('Fayda ID linked');
    }

    public function unlink(Member $member): void
    {
        if ($member->fayda_sub_hash === null) {
            return;
        }

        $member->forceFill(['fayda_sub_hash' => null, 'fayda_linked_at' => null])->save();
        $member->notes()->create(['user_id' => null, 'kind' => PersonNote::UPDATE, 'body' => __('members.note.fayda_unlinked')]);
        activity('membership')->performedOn($member)->event('fayda_unlinked')->log('Fayda ID unlinked');
    }
}
