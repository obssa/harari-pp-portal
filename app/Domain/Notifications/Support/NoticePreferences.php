<?php

namespace App\Domain\Notifications\Support;

use App\Domain\Notifications\Channels\GroupedDatabaseChannel;
use App\Domain\Notifications\Enums\NoticeKind;
use App\Models\User;

/**
 * Where a person receives each kind of notice (ADR-0036): in the portal
 * (the bell and the notifications page), by email, both or neither. Kinds
 * a person never chose keep their defaults.
 */
final class NoticePreferences
{
    public const IN_APP = 'in_app';

    public const EMAIL = 'email';

    public const CHANNELS = [self::IN_APP, self::EMAIL];

    /**
     * @return list<string>
     */
    public static function channels(User $user, NoticeKind $kind): array
    {
        $saved = $user->notification_preferences[$kind->value] ?? null;
        if (! is_array($saved)) {
            return $kind->defaults();
        }

        return array_values(array_intersect(self::CHANNELS, $saved));
    }

    /**
     * The notification channels for Laravel.
     *
     * @return list<string>
     */
    public static function via(User $user, NoticeKind $kind): array
    {
        $channels = self::channels($user, $kind);
        $via = [];
        if (in_array(self::IN_APP, $channels, true)) {
            $via[] = GroupedDatabaseChannel::class;
        }
        if (in_array(self::EMAIL, $channels, true) && filled($user->email)) {
            $via[] = 'mail';
        }

        return $via;
    }

    /**
     * @return array<string, list<string>> every kind offered to the person
     */
    public static function all(User $user): array
    {
        $choices = [];
        foreach (NoticeKind::for($user) as $kind) {
            $choices[$kind->value] = self::channels($user, $kind);
        }

        return $choices;
    }

    /**
     * Stores the choices for the kinds offered to the person; unknown kinds
     * and channels are dropped, kinds not offered keep what they had.
     *
     * @param  array<string, mixed>  $choices
     */
    public static function save(User $user, array $choices): void
    {
        $stored = is_array($user->notification_preferences) ? $user->notification_preferences : [];
        foreach (NoticeKind::for($user) as $kind) {
            $picked = $choices[$kind->value] ?? [];
            $stored[$kind->value] = array_values(array_intersect(self::CHANNELS, is_array($picked) ? $picked : []));
        }

        $user->forceFill(['notification_preferences' => $stored])->save();
    }
}
