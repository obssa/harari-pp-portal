<?php

namespace App\Domain\Membership\Actions;

use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Support\ApplicationOptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * A signed-in member changes their portal password (ADR-0080): the current
 * one first, then the new one twice. The member app is signed out everywhere.
 */
final class ChangeMemberPassword
{
    public function handle(Member $member, string $current, string $password, string $confirmation): void
    {
        if (! $member->hasPassword() || ! Hash::check($current, (string) $member->password)) {
            throw ValidationException::withMessages(['current_password' => __('members.portal.current_wrong')]);
        }
        $error = ApplicationOptions::passwordError($password, $confirmation);
        if ($error !== null) {
            throw ValidationException::withMessages(['password' => $error]);
        }

        $member->forceFill(['password' => $password])->save();
        app(IssueMemberToken::class)->revokeAll($member);
        activity('membership')->performedOn($member)->event('password_changed')->log('Member changed the portal password');
    }
}
