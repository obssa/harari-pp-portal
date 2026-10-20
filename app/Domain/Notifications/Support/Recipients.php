<?php

namespace App\Domain\Notifications\Support;

use App\Domain\Access\Enums\UserStatus;
use App\Domain\Notifications\Notifications\StaffNotice;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Who hears about something (ADR-0036): active staff, never the person who
 * caused it.
 */
final class Recipients
{
    /**
     * Active staff holding the permission directly, through a role, or as Super Admin.
     *
     * @return Collection<int, User>
     */
    public static function withPermission(string $permission): Collection
    {
        return User::query()
            ->where('status', UserStatus::Active->value)
            ->where(fn ($query) => $query->permission($permission)->orWhereHas('roles', fn ($q) => $q->where('name', 'super_admin')))
            ->get();
    }

    /**
     * @param  iterable<User>  $users
     */
    public static function notify(iterable $users, StaffNotice $notice, ?User $except = null): void
    {
        $recipients = collect($users)
            ->filter(fn (User $user): bool => $user->status === UserStatus::Active && $user->id !== $except?->id)
            ->unique('id')
            ->values();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, $notice);
        }
    }
}
