<?php

namespace App\Domain\Social\Fetchers;

use App\Domain\Import\Support\TelegramChannelReader;
use App\Domain\Social\Models\SocialSource;
use App\Domain\Social\Support\FetchedPost;

/**
 * Telegram channels from their public preview pages, as the story import
 * reads them (ADR-0022); no bot or login needed.
 */
final class TelegramFetcher implements SocialFetcher
{
    public function __construct(private readonly TelegramChannelReader $reader) {}

    public function fetch(SocialSource $source): array
    {
        $posts = [];
        foreach (array_reverse($this->reader->page($source->handle)) as $message) {
            if (! $message->hasText() && $message->photos === [] && ! $message->hasVideo) {
                continue;
            }
            $posts[] = new FetchedPost(
                externalId: (string) $message->id,
                postedAt: $message->at,
                text: $message->text,
                permalink: $message->url(),
                imageUrls: array_slice($message->photos, 0, 1),
                hasVideo: $message->hasVideo,
            );
        }

        return $posts;
    }
}
