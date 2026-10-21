<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Telegram\TelegramBot;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/**
 * Messages people send to the portal's Telegram bot (ADR-0055). Only
 * requests carrying the secret given to Telegram are read. "/start CODE"
 * links the chat to the staff account that showed the code; "/stop"
 * unlinks it. Everything else is ignored.
 */
final class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, TelegramBot $bot): Response
    {
        abort_unless(hash_equals(TelegramBot::webhookSecret(), (string) $request->header('X-Telegram-Bot-Api-Secret-Token')), 403);

        $chatId = (string) $request->input('message.chat.id', '');
        $text = trim((string) $request->input('message.text', ''));
        if ($chatId === '' || $text === '') {
            return response()->noContent();
        }

        $reply = null;
        if (preg_match('/^\/start\s+([A-Za-z0-9]{10})$/', $text, $m) === 1) {
            $user = User::query()->where('telegram_link_code', $m[1])->where('telegram_link_expires_at', '>', CarbonImmutable::now())->first();
            if ($user instanceof User) {
                $user->forceFill(['telegram_chat_id' => $chatId, 'telegram_linked_at' => CarbonImmutable::now(), 'telegram_link_code' => null, 'telegram_link_expires_at' => null])->save();
                activity('notifications')->performedOn($user)->causedBy($user)->event('telegram_linked')->log('Telegram linked');
                $reply = __('notifications.telegram.linked', ['name' => $user->name], $user->locale ?? 'en');
            } else {
                $reply = __('notifications.telegram.unknown_code');
            }
        } elseif ($text === '/stop') {
            foreach (User::query()->whereNotNull('telegram_chat_id')->get() as $user) {
                if ($user->telegram_chat_id === $chatId) {
                    $user->forceFill(['telegram_chat_id' => null, 'telegram_linked_at' => null])->save();
                    $reply = __('notifications.telegram.unlinked', [], $user->locale ?? 'en');
                }
            }
        }

        if ($reply !== null) {
            try {
                $bot->send($chatId, $reply);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return response()->noContent();
    }
}
