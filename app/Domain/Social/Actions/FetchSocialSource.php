<?php

namespace App\Domain\Social\Actions;

use App\Domain\Import\Support\TelegramChannelReader;
use App\Domain\Media\Actions\UploadMedia;
use App\Domain\Media\Models\Media;
use App\Domain\Social\Models\SocialPost;
use App\Domain\Social\Models\SocialSource;
use App\Domain\Social\Support\FetchedPost;
use App\Domain\Social\Support\Fetchers;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reads one source and keeps its posts up to date (ADR-0031): new posts are
 * added with a library copy of their first picture, known ones get their
 * newer text and counts, and staff decisions (hidden, pinned) are kept. A
 * failure is written on the source and never stops the other sources.
 */
final class FetchSocialSource
{
    /** Most posts read per fetch. */
    public const LIMIT = 30;

    /** Largest picture copied into the library. */
    public const MAX_IMAGE_BYTES = 8 * 1024 * 1024;

    public function __construct(
        private readonly Fetchers $fetchers,
        private readonly UploadMedia $upload,
    ) {}

    /**
     * @return array{added: int, updated: int, error: ?string}
     */
    public function handle(SocialSource $source): array
    {
        $added = 0;
        $updated = 0;
        try {
            $fetcher = $this->fetchers->for($source->platform);
            if ($fetcher === null) {
                throw new \RuntimeException(__('social.error.not_available', ['platform' => $source->platform->label()]));
            }

            foreach (array_slice($fetcher->fetch($source), 0, self::LIMIT) as $fetched) {
                /** @var SocialPost|null $post */
                $post = $source->posts()->where('external_id', $fetched->externalId)->first();
                if ($post !== null) {
                    $post->fill(['text' => $fetched->text, 'metrics' => $fetched->metrics ?: $post->metrics, 'has_video' => $fetched->hasVideo])->save();
                    $updated++;

                    continue;
                }

                $source->posts()->create([
                    'external_id' => $fetched->externalId,
                    'posted_at' => $fetched->postedAt,
                    'text' => $fetched->text,
                    'permalink' => $fetched->permalink,
                    'image_id' => $this->image($source, $fetched)?->id,
                    'has_video' => $fetched->hasVideo,
                    'metrics' => $fetched->metrics ?: null,
                ]);
                $added++;
            }

            $source->forceFill(['last_fetched_at' => CarbonImmutable::now(), 'last_error' => null])->save();

            return ['added' => $added, 'updated' => $updated, 'error' => null];
        } catch (Throwable $e) {
            $error = Str::limit($e->getMessage(), 500);
            $source->forceFill(['last_fetched_at' => CarbonImmutable::now(), 'last_error' => $error])->save();
            Log::warning('Social source failed', ['source' => $source->url(), 'error' => $error]);

            return ['added' => $added, 'updated' => $updated, 'error' => $error];
        }
    }

    /** The first picture, copied and cleaned like any upload, credited to the account. */
    private function image(SocialSource $source, FetchedPost $fetched): ?Media
    {
        $url = $fetched->imageUrls[0] ?? null;
        if ($url === null || ! str_starts_with($url, 'https://')) {
            return null;
        }

        $path = null;
        try {
            $response = Http::withUserAgent(TelegramChannelReader::USER_AGENT)->timeout(30)->get($url);
            $body = $response->body();
            if (! $response->successful() || $body === '' || strlen($body) > self::MAX_IMAGE_BYTES) {
                return null;
            }
            File::ensureDirectoryExists(storage_path('app/media-work'));
            $path = storage_path('app/media-work/social-'.Str::uuid().'.jpg');
            file_put_contents($path, $body);
            $media = $this->upload->handle($path, "{$source->platform->value}-{$source->handle}-{$fetched->externalId}.jpg");
            $media->forceFill(['credit' => $source->name, 'alt' => ['am' => Str::limit($fetched->text, 200, '') ?: $source->name]])->save();

            return $media;
        } catch (Throwable $e) {
            Log::warning('Social picture skipped', ['post' => $fetched->permalink, 'error' => $e->getMessage()]);

            return null;
        } finally {
            if ($path !== null && is_file($path)) {
                @unlink($path);
            }
        }
    }
}
