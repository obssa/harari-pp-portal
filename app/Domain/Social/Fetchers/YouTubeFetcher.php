<?php

namespace App\Domain\Social\Fetchers;

use App\Domain\Import\Support\TelegramChannelReader;
use App\Domain\Social\Models\SocialSource;
use App\Domain\Social\Support\FetchedPost;
use App\Settings\IntegrationSettings;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * YouTube channels from their public feed (ADR-0031): the latest fifteen
 * videos with title, description, picture and views, no key needed. A
 * channel given by its @name needs the Data API key once, to find its id.
 */
final class YouTubeFetcher implements SocialFetcher
{
    public const FEED = 'https://www.youtube.com/feeds/videos.xml?channel_id=';

    public const CHANNELS_API = 'https://www.googleapis.com/youtube/v3/channels';

    public function __construct(private readonly IntegrationSettings $settings) {}

    public function fetch(SocialSource $source): array
    {
        $response = Http::withUserAgent(TelegramChannelReader::USER_AGENT)->timeout(20)->get(self::FEED.$this->channelId($source));
        if (! $response->successful()) {
            throw new RuntimeException(__('social.error.http', ['status' => $response->status()]));
        }

        return self::parse($response->body());
    }

    /**
     * @return list<FetchedPost>
     */
    public static function parse(string $xml): array
    {
        $document = new DOMDocument;
        if ($xml === '' || ! @$document->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            throw new RuntimeException(__('social.error.unreadable'));
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('a', 'http://www.w3.org/2005/Atom');
        $xpath->registerNamespace('yt', 'http://www.youtube.com/xml/schemas/2015');
        $xpath->registerNamespace('media', 'http://search.yahoo.com/mrss/');

        $posts = [];
        foreach ($xpath->query('/a:feed/a:entry') ?: [] as $entry) {
            if (! $entry instanceof DOMElement) {
                continue;
            }
            $id = trim((string) $xpath->evaluate('string(yt:videoId)', $entry));
            if (preg_match('/^[A-Za-z0-9_-]{11}$/', $id) !== 1) {
                continue;
            }
            $title = trim((string) $xpath->evaluate('string(a:title)', $entry));
            $description = trim((string) $xpath->evaluate('string(media:group/media:description)', $entry));
            $views = (int) $xpath->evaluate('string(media:group/media:community/media:statistics/@views)', $entry);

            try {
                $at = CarbonImmutable::parse((string) $xpath->evaluate('string(a:published)', $entry))->utc();
            } catch (Throwable) {
                continue;
            }

            $posts[] = new FetchedPost(
                externalId: $id,
                postedAt: $at,
                text: trim($title."\n\n".$description),
                permalink: "https://www.youtube.com/watch?v={$id}",
                imageUrls: ["https://i.ytimg.com/vi/{$id}/hqdefault.jpg"],
                hasVideo: true,
                metrics: $views > 0 ? ['views' => $views] : [],
            );
        }

        return $posts;
    }

    /** The UC… id, found once from an @name through the Data API and kept on the source. */
    private function channelId(SocialSource $source): string
    {
        if (preg_match('/^UC[A-Za-z0-9_-]{22}$/', $source->handle) === 1) {
            return $source->handle;
        }
        $known = $source->credentials['channel_id'] ?? null;
        if (is_string($known) && preg_match('/^UC[A-Za-z0-9_-]{22}$/', $known) === 1) {
            return $known;
        }

        $key = $this->settings->youtube_api_key;
        if (blank($key)) {
            throw new RuntimeException(__('social.error.youtube_channel_id'));
        }
        $id = (string) Http::timeout(20)->get(self::CHANNELS_API, ['part' => 'id', 'forHandle' => '@'.$source->handle, 'key' => $key])->json('items.0.id', '');
        if (preg_match('/^UC[A-Za-z0-9_-]{22}$/', $id) !== 1) {
            throw new RuntimeException(__('social.error.youtube_not_found'));
        }
        $source->forceFill(['credentials' => [...($source->credentials ?? []), 'channel_id' => $id]])->save();

        return $id;
    }
}
