<?php

namespace App\Domain\Import\Support;

use Carbon\CarbonImmutable;

/**
 * One message from a channel's public preview page.
 */
final readonly class TelegramMessage
{
    /**
     * @param  list<string>  $photos  image addresses on Telegram's CDN
     */
    public function __construct(
        public string $channel,
        public int $id,
        public CarbonImmutable $at,
        public string $text,
        public array $photos,
        public bool $hasVideo,
    ) {}

    public function externalId(): string
    {
        return $this->channel.'/'.$this->id;
    }

    public function url(): string
    {
        return 'https://t.me/'.$this->channel.'/'.$this->id;
    }

    public function hasText(): bool
    {
        return trim($this->text) !== '';
    }
}
