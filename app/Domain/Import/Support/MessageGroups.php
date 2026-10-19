<?php

namespace App\Domain\Import\Support;

/**
 * Channels usually post a story as a text message followed within seconds
 * by a photo album (or a video), sometimes the album first. Each text
 * message becomes one story; media-only messages join the text posted
 * within two minutes of them; the rest are left out.
 */
final class MessageGroups
{
    public const WINDOW_SECONDS = 120;

    /**
     * @param  list<TelegramMessage>  $messages  oldest first
     * @return array{stories: list<array{text: TelegramMessage, media: list<TelegramMessage>}>, leftovers: list<TelegramMessage>}
     */
    public static function build(array $messages): array
    {
        $stories = [];
        $index = [];
        foreach ($messages as $message) {
            if ($message->hasText()) {
                $index[$message->id] = count($stories);
                $stories[] = ['text' => $message, 'media' => []];
            }
        }

        $leftovers = [];
        foreach ($messages as $message) {
            if ($message->hasText()) {
                continue;
            }

            $nearest = null;
            $distance = PHP_INT_MAX;
            foreach ($stories as $position => $story) {
                $gap = abs($story['text']->at->getTimestamp() - $message->at->getTimestamp());
                // Prefer the text just before the album when both are close.
                $gap += $story['text']->id > $message->id ? 1 : 0;
                if ($gap <= self::WINDOW_SECONDS && $gap < $distance) {
                    $nearest = $position;
                    $distance = $gap;
                }
            }

            if ($nearest === null) {
                $leftovers[] = $message;
            } else {
                $stories[$nearest]['media'][] = $message;
            }
        }

        return ['stories' => $stories, 'leftovers' => $leftovers];
    }
}
