<?php

namespace App\Services\Sms;

use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Log;

/**
 * Writes text messages to their own log (storage/logs/sms-*.log, kept a
 * week) instead of sending them, with the number partly hidden.
 */
final class LogSmsGateway implements SmsGateway
{
    public function name(): string
    {
        return 'log';
    }

    public function send(string $to, string $text): void
    {
        Log::channel('sms')->info('SMS not sent (log driver)', ['to' => PhoneNumber::mask($to), 'text' => $text]);
    }
}
