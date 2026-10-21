<?php

namespace App\Domain\Notifications\Channels;

use App\Domain\Notifications\Notifications\StaffNotice;
use App\Models\User;
use App\Services\Telegram\TelegramBot;
use Illuminate\Notifications\Notification;

/**
 * Notices by Telegram (ADR-0055), to staff who linked their account and
 * chose Telegram for that kind of notice.
 */
final class TelegramChannel
{
    public function __construct(private readonly TelegramBot $bot) {}

    public function send(User $notifiable, Notification $notification): void
    {
        if (! $notification instanceof StaffNotice || $notifiable->telegram_chat_id === null || ! $this->bot->isConfigured()) {
            return;
        }
        $this->bot->send((string) $notifiable->telegram_chat_id, $notification->toTelegram($notifiable));
    }
}
