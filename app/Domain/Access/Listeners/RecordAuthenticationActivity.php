<?php

namespace App\Domain\Access\Listeners;

use App\Domain\Access\Notifications\NewSignInNotice;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Events\Dispatcher;
use Spatie\Activitylog\Models\Activity;

/**
 * Login history for the audit log (PLAN.md §7: every login is logged).
 * Failed attempts record the email tried, never the password. A sign-in
 * from a network and a browser both unseen in 90 days alerts the person
 * (PLAN.md §11, ADR-0038); the very first sign-in does not.
 */
final class RecordAuthenticationActivity
{
    public function login(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $ip = request()->ip();
        $agent = mb_substr((string) request()->userAgent(), 0, 255);
        $unfamiliar = $ip !== null && self::unfamiliar($event->user, $ip, $agent);

        $event->user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ])->saveQuietly();

        activity('auth')->causedBy($event->user)->performedOn($event->user)->event('login')->log('login');

        if ($unfamiliar) {
            $event->user->notify(new NewSignInNotice((string) $ip, $agent, now()->toIso8601String()));
        }
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

    private static function unfamiliar(User $user, string $ip, string $agent): bool
    {
        $previous = Activity::query()
            ->where('log_name', 'auth')
            ->where('event', 'login')
            ->where('causer_type', $user->getMorphClass())
            ->where('causer_id', $user->getKey())
            ->where('created_at', '>=', now()->subDays(90))
            ->latest('id')
            ->limit(50)
            ->get();
        if ($previous->isEmpty()) {
            return false;
        }

        $network = self::network($ip);

        return $previous->doesntContain(fn (Activity $login): bool => self::network((string) $login->properties->get('ip')) === $network
            || $login->properties->get('user_agent') === $agent);
    }

    /** The /24 of an IPv4 address or the /48 of an IPv6 one: mobile networks change the rest often. */
    private static function network(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return implode('.', array_slice(explode('.', $ip), 0, 3));
        }
        $packed = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? inet_pton($ip) : false;

        return $packed === false ? $ip : bin2hex(substr($packed, 0, 6));
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
