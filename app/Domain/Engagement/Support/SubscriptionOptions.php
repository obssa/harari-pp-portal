<?php

namespace App\Domain\Engagement\Support;

/**
 * What subscribers can choose (PLAN.md §4 preference centre, ADR-0051).
 * Keys are stored; labels come from the language files.
 */
final class SubscriptionOptions
{
    public const CHANNELS = ['email', 'sms'];

    public const TOPICS = ['news', 'statements', 'events', 'opportunities'];

    /**
     * @return array<string, string>
     */
    public static function channels(): array
    {
        return array_combine(self::CHANNELS, array_map(fn (string $key): string => __("newsletter.channel.{$key}"), self::CHANNELS));
    }

    /**
     * @return array<string, string>
     */
    public static function topics(): array
    {
        return array_combine(self::TOPICS, array_map(fn (string $key): string => __("newsletter.topic.{$key}"), self::TOPICS));
    }
}
