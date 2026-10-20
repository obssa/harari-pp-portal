<?php

namespace App\Services\Sms;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * One text message, sent from the queue so a slow provider never holds up
 * the visitor. The queued copy is encrypted: it holds a phone number.
 */
final class SendSms implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(
        public readonly string $to,
        public readonly string $text,
    ) {}

    public function handle(SmsGateway $gateway): void
    {
        $gateway->send($this->to, $this->text);
    }
}
