<?php

namespace App\Domain\Media\Actions;

use App\Domain\Content\Actions\WriteTranslations;
use App\Domain\Content\Models\Category;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Models\Video;
use App\Domain\Media\Support\VideoLink;
use App\Domain\Organisation\Models\League;
use App\Domain\Organisation\Models\Office;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Saves a video (ADR-0024). The address decides the provider; a YouTube
 * video without a chosen thumbnail gets its YouTube picture copied into the
 * library once, so listing pages never load anything from YouTube.
 */
final class SaveVideo
{
    public function __construct(
        private readonly WriteTranslations $translations,
        private readonly SyncMediaUsage $usage,
        private readonly UploadMedia $upload,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Video $video, array $data, User $actor): Video
    {
        /** @var array<string, array<string, mixed>> $translations */
        $translations = (array) ($data['translations'] ?? []);
        WriteTranslations::requireName($translations, 'title');

        $link = VideoLink::parse((string) ($data['url'] ?? ''));
        if ($link === null) {
            throw ValidationException::withMessages(['data.url' => __('video.validation.url')]);
        }

        $isLive = (bool) ($data['is_live'] ?? false);
        $starts = $this->time($data['live_starts_at'] ?? null);
        $ends = $this->time($data['live_ends_at'] ?? null);
        if ($isLive && $starts === null) {
            throw ValidationException::withMessages(['data.live_starts_at' => __('video.validation.live_start')]);
        }
        if ($starts !== null && $ends !== null && $ends->lessThanOrEqualTo($starts)) {
            throw ValidationException::withMessages(['data.live_ends_at' => __('video.validation.live_end')]);
        }

        $thumbnail = filled($data['thumbnail_id'] ?? null) ? (int) $data['thumbnail_id'] : null;
        if ($thumbnail === null && $link->youtubeId !== null) {
            $thumbnail = $video->youtube_id === $link->youtubeId && $video->thumbnail_id !== null
                ? $video->thumbnail_id
                : $this->youtubeThumbnail($link, $this->firstTitle($translations))?->id;
        }

        return DB::transaction(function () use ($video, $data, $translations, $actor, $link, $thumbnail, $isLive, $starts, $ends): Video {
            $video->fill([
                'provider' => $link->provider,
                'url' => $link->url,
                'youtube_id' => $link->youtubeId,
                'thumbnail_id' => $thumbnail,
                'category_id' => filled($data['category_id'] ?? null) && Category::query()->whereKey($data['category_id'])->where('type', Category::TYPE_VIDEO)->exists() ? (string) $data['category_id'] : null,
                'office_id' => filled($data['office_id'] ?? null) && Office::query()->whereKey($data['office_id'])->exists() ? (string) $data['office_id'] : null,
                'league_id' => filled($data['league_id'] ?? null) && League::query()->whereKey($data['league_id'])->exists() ? (string) $data['league_id'] : null,
                'recorded_on' => $this->time($data['recorded_on'] ?? null)?->startOfDay(),
                'is_featured' => (bool) ($data['is_featured'] ?? false),
                'is_live' => $isLive,
                'live_starts_at' => $starts,
                'live_ends_at' => $ends,
            ]);
            if (! $video->exists) {
                $video->created_by = $actor->id;
            }
            $video->updated_by = $actor->id;
            $video->save();

            $this->translations->handle($video, $translations, ['summary'], $actor->can('videos.manage'), 'title');
            $this->usage->handle($video, 'thumbnail', [$video->thumbnail_id]);

            return $video->refresh();
        });
    }

    /**
     * Copies YouTube's picture into the library (cleaned like any upload).
     */
    private function youtubeThumbnail(VideoLink $link, string $title): ?Media
    {
        $url = $link->thumbnailUrl();
        if ($url === null) {
            return null;
        }

        $path = null;
        try {
            $response = Http::timeout(15)->get($url);
            if (! $response->successful() || $response->body() === '') {
                return null;
            }

            File::ensureDirectoryExists(storage_path('app/media-work'));
            $path = storage_path('app/media-work/video-'.Str::uuid().'.jpg');
            file_put_contents($path, $response->body());
            $media = $this->upload->handle($path, "youtube-{$link->youtubeId}.jpg");
            $media->forceFill(['alt' => $title !== '' ? ['en' => $title] : null, 'credit' => 'YouTube'])->save();

            return $media;
        } catch (Throwable $e) {
            Log::warning('Video thumbnail not copied', ['video' => $link->url, 'error' => $e->getMessage()]);

            return null;
        } finally {
            if ($path !== null && is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $translations
     */
    private function firstTitle(array $translations): string
    {
        foreach ($translations as $values) {
            $title = trim((string) ($values['title'] ?? ''));
            if ($title !== '') {
                return mb_substr($title, 0, 250);
            }
        }

        return '';
    }

    private function time(mixed $value): ?CarbonImmutable
    {
        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }
}
