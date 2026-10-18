<?php

namespace App\Domain\Access\Actions;

use App\Domain\Access\Enums\UserStatus;
use App\Domain\Access\Notifications\SignInLink;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\Password;

/**
 * Account operations staff can run from the Users screen. Authorization is
 * checked by the caller through UserPolicy; every change is audited.
 */
final class ManageAccount
{
    public function __construct(private readonly InvalidateUserSessions $invalidate) {}

    /**
     * Emails a link to choose a password (used as the staff invitation).
     */
    public function sendSignInLink(User $user): void
    {
        $broker = Password::broker(Filament::getAuthPasswordBroker());
        if (! $broker instanceof PasswordBroker) {
            throw new \LogicException('The configured password broker cannot create sign-in tokens.');
        }
        $token = $broker->createToken($user);
        $url = Filament::getResetPasswordUrl($token, $user);

        $user->notify(new SignInLink($url, (int) config('auth.passwords.users.expire', 60)));

        activity('users')->performedOn($user)->event('sign_in_link_sent')->log('sign_in_link_sent');
    }

    public function suspend(User $user): void
    {
        $user->update(['status' => UserStatus::Suspended]);
        ($this->invalidate)($user);
    }

    public function reactivate(User $user): void
    {
        $user->update(['status' => UserStatus::Active]);
    }

    /**
     * Turns off the user's authenticator app; privileged users must set it up
     * again at their next sign-in.
     */
    public function resetMfa(User $user): void
    {
        $user->forceFill([
            'app_authentication_secret' => null,
            'app_authentication_recovery_codes' => null,
        ])->save();

        activity('users')->performedOn($user)->event('mfa_reset')->log('mfa_reset');
        ($this->invalidate)($user);
    }
}
