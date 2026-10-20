<?php

namespace App\Domain\Notifications\Channels;

use App\Domain\Notifications\Notifications\StaffNotice;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;

/**
 * The database channel, except that a notice with a group replaces the
 * person's unread notice of the same group (ADR-0036): fifty registrations
 * for one event make one up-to-date notice, not fifty.
 */
final class GroupedDatabaseChannel extends DatabaseChannel
{
    public function send($notifiable, Notification $notification)
    {
        if ($notification instanceof StaffNotice && ($group = $notification->group()) !== null && method_exists($notifiable, 'unreadNotifications')) {
            $notifiable->unreadNotifications()->where('data->group', $group)->delete();
        }

        return parent::send($notifiable, $notification);
    }
}
