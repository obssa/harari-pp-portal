<?php

namespace App\Domain\Media\Support;

use App\Domain\Content\Blocks\VideoBlock;

/**
 * Understands a video address an editor pastes (ADR-0024): YouTube (watch,
 * youtu.be, shorts, live, embed), a Facebook video or a Telegram post.
 */
final readonly class VideoLink
{
    public const YOUTUBE = 'youtube';

    public const FACEBOOK = 'facebook';

    public const TELEGRAM = 'telegram';

    private function __construct(
        public string $provider,
        public string $url,
        public ?string $youtubeId,
    ) {}

    public static function parse(string $url): ?self
    {
        $url = trim($url);
        $parts = parse_url($url);
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || ! isset($parts['host'])) {
            return null;
        }

        $youtubeId = VideoBlock::youtubeId($url);
        if ($youtubeId !== null) {
            return new self(self::YOUTUBE, 'https://www.youtube.com/watch?v='.$youtubeId, $youtubeId);
        }

        $host = strtolower((string) preg_replace('/^(www|m)\./', '', $parts['host']));
        $path = (string) ($parts['path'] ?? '');

        if (in_array($host, ['facebook.com', 'fb.watch'], true)) {
            return new self(self::FACEBOOK, $url, null);
        }

        if ($host === 't.me' && preg_match('#^/[A-Za-z0-9_]{5,32}/\d+$#', $path) === 1) {
            return new self(self::TELEGRAM, 'https://t.me'.$path, null);
        }

        return null;
    }

    /** Privacy-enhanced YouTube player address (no cookies until play). */
    public function embedUrl(): ?string
    {
        return $this->youtubeId !== null ? 'https://www.youtube-nocookie.com/embed/'.$this->youtubeId.'?rel=0' : null;
    }

    public function thumbnailUrl(): ?string
    {
        return $this->youtubeId !== null ? 'https://i.ytimg.com/vi/'.$this->youtubeId.'/hqdefault.jpg' : null;
    }
}
