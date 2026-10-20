<?php

namespace App\Services\Sms;

/**
 * Sends text messages (PLAN.md §9: Ethio Telecom, Africa's Talking or
 * Twilio). Until a provider is chosen, the log driver records what would be
 * sent (ADR-0025).
 */
interface SmsGateway
{
    /** The driver name shown in the admin. */
    public function name(): string;

    /**
     * @param  string  $to  an E.164 number such as +2519…
     */
    public function send(string $to, string $text): void;
}
