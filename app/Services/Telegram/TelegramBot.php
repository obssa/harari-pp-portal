<?php

namespace App\Services\Telegram;

use App\Settings\IntegrationSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The portal's Telegram bot (ADR-0055), with the token saved under
 * Settings → Integrations: sends notices to staff who linked their account
 * and receives the /start message that links it.
 */
class TelegramBot
{
    private const API = 'https://api.telegram.org/bot';

    public function __construct(private readonly IntegrationSettings $settings) {}

    public function isConfigured(): bool
    {
        return $this->settings->isConfigured('telegram_bot_token');
    }

    /** The bot's @name for the link people open, asked from Telegram once a day. */
    public function username(): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        return Cache::remember('telegram-bot-username', 86400, function (): ?string {
            $response = Http::timeout(10)->get($this->endpoint('getMe'));

            return $response->successful() ? (string) $response->json('result.username') : null;
        });
    }

    public function send(string $chatId, string $text): void
    {
        $response = Http::timeout(10)->asJson()->post($this->endpoint('sendMessage'), [
            'chat_id' => $chatId,
            'text' => mb_substr($text, 0, 4000),
            'disable_web_page_preview' => true,
        ]);
        if (! $response->successful()) {
            throw new RuntimeException('Telegram sendMessage failed: '.$response->status());
        }
    }

    /**
     * Posts to a channel the bot is an administrator of, with the link's
     * preview (ADR-0059); returns the message id.
     */
    public function publish(string $chatId, string $text): int
    {
        $response = Http::timeout(15)->asJson()->post($this->endpoint('sendMessage'), [
            'chat_id' => $chatId,
            'text' => mb_substr($text, 0, 4000),
        ]);
        $id = $response->json('result.message_id');
        if (! $response->successful() || ! is_int($id)) {
            throw new RuntimeException('Telegram: '.mb_substr((string) ($response->json('description') ?? $response->status()), 0, 150));
        }

        return $id;
    }

    /** Tells Telegram where to deliver messages sent to the bot, with the secret it must send back. */
    public function registerWebhook(string $url): bool
    {
        return Http::timeout(10)->asJson()->post($this->endpoint('setWebhook'), [
            'url' => $url,
            'secret_token' => self::webhookSecret(),
            'allowed_updates' => ['message'],
        ])->successful();
    }

    /** The secret Telegram sends with every update, derived from the app key. */
    public static function webhookSecret(): string
    {
        return substr(hash_hmac('sha256', 'telegram-webhook', (string) config('app.key')), 0, 48);
    }

    private function endpoint(string $method): string
    {
        return self::API.$this->settings->telegram_bot_token.'/'.$method;
    }
}
