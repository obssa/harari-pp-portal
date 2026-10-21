<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramBot;
use Illuminate\Console\Command;

/**
 * Tells Telegram to deliver messages for the bot to this portal (ADR-0055).
 * Run once after saving the bot token in Settings → Integrations.
 */
final class TelegramWebhookCommand extends Command
{
    protected $signature = 'telegram:webhook';

    protected $description = 'Register the portal as the webhook of the Telegram bot';

    public function handle(TelegramBot $bot): int
    {
        if (! $bot->isConfigured()) {
            $this->components->error('No Telegram bot token is saved in Settings → Integrations.');

            return self::FAILURE;
        }
        if (! $bot->registerWebhook(route('telegram.webhook'))) {
            $this->components->error('Telegram refused the webhook.');

            return self::FAILURE;
        }
        $this->components->info('Webhook registered: '.route('telegram.webhook'));

        return self::SUCCESS;
    }
}
