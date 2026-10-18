<?php

namespace App\Domain\Access\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Events\Dispatcher;

/**
 * Login history for the audit log (PLAN.md §7: every login is logged).
 * Failed attempts record the email tried, never the password.
 */
final class RecordAuthenticationActivity
{
    public function login(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ])->saveQuietly();

        activity('auth')->causedBy($event->user)->performedOn($event->user)->event('login')->log('login');
    }

    public function logout(Logout $event): void
    {
        if ($event->user instanceof User) {
            activity('auth')->causedBy($event->user)->performedOn($event->user)->event('logout')->log('logout');
        }
    }

    public function failed(Failed $event): void
    {
        $email = is_string($event->credentials['email'] ?? null) ? mb_strtolower($event->credentials['email']) : null;

        activity('auth')
            ->event('login_failed')
            ->withProperties(['email' => $email])
            ->log('login_failed');
    }

    public function passwordReset(PasswordReset $event): void
    {
        if ($event->user instanceof User) {
            activity('auth')->causedBy($event->user)->performedOn($event->user)->event('password_reset')->log('password_reset');
        }
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'login',
            Logout::class => 'logout',
            Failed::class => 'failed',
            PasswordReset::class => 'passwordReset',
        ];
    }
}
