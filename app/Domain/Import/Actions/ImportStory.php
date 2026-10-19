<?php

namespace App\Domain\Import\Actions;

use App\Domain\Content\Actions\RecordRevision;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Enums\PostType;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostTranslation;
use App\Domain\Content\Support\BodyRenderer;
use App\Domain\Import\Models\ContentImport;
use App\Domain\Import\Models\ContentSource;
use App\Domain\Import\Support\StoryText;
use App\Domain\Import\Support\TelegramChannelReader;
use App\Domain\Import\Support\TelegramMessage;
use App\Domain\Media\Actions\SyncMediaUsage;
use App\Domain\Media\Actions\UploadMedia;
use App\Domain\Media\Models\Media;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Makes one draft post from a channel story (docs/content/sources.md rules):
 * text as written, the source and its link kept, photos downloaded once,
 * cleaned like any upload and credited, nothing published. A story already
 * imported from the same channel or a higher-priority source is recorded as
 * a duplicate.
 */
final class ImportStory
{
    /** At most this many photos are kept per story. */
    public const MAX_PHOTOS = 10;

    /** Largest photo downloaded, in bytes. */
    public const MAX_PHOTO_BYTES = 10 * 1024 * 1024;

    /** Stories this many days apart are never the same story. */
    public const DUPLICATE_DAYS = 3;

    public function __construct(
        private readonly UploadMedia $upload,
        private readonly SyncMediaUsage $usage,
        private readonly RecordRevision $revisions,
    ) {}

    /**
     * @param  list<TelegramMessage>  $media
     */
    public function handle(ContentSource $source, TelegramMessage $message, array $media = []): ContentImport
    {
        $fingerprint = StoryText::fingerprint($message->text);
        $duplicate = $this->duplicateOf($source, $message, $fingerprint);
        if ($duplicate !== null) {
            return $this->record($source, $message, ContentImport::DUPLICATE, $duplicate->post_id, $fingerprint);
        }

        $locale = StoryText::language($message->text, $source->default_locale);
        $story = StoryText::split($message->text);
        $photos = $this->photos($source, $message, $media, $story['title'], $locale);

        $post = DB::transaction(function () use ($source, $message, $locale, $story, $photos): Post {
            $post = new Post;
            $post->fill([
                'type' => PostType::News,
                'source_locale' => $locale,
                'featured_image_id' => $photos[0]->id ?? null,
            ]);
            $post->forceFill([
                'source_url' => $message->url(),
                'source_name' => $source->name,
                'source_published_at' => $message->at,
            ])->save();

            /** @var PostTranslation $translation */
            $translation = $post->putTranslation($locale, [
                'title' => $story['title'],
                'excerpt' => $story['summary'],
                'body' => StoryText::body($story['paragraphs'], array_map(fn (Media $m): int => $m->id, array_slice($photos, 1))),
                'status' => ContentStatus::Draft,
            ]);
            $this->revisions->handle($post, $translation, __('import.revision_note', ['source' => $source->name]));

            $this->usage->handle($post, 'featured_image', [$post->featured_image_id]);
            $this->usage->handle($post, 'body', BodyRenderer::mediaIds($translation->body));

            return $post;
        });

        return $this->record($source, $message, ContentImport::IMPORTED, $post->id, $fingerprint);
    }

    public function skip(ContentSource $source, TelegramMessage $message): ContentImport
    {
        return $this->record($source, $message, ContentImport::SKIPPED, null, null);
    }

    /**
     * The import of the same story from this channel (a repost) or from a
     * source of equal or higher priority.
     */
    private function duplicateOf(ContentSource $source, TelegramMessage $message, string $fingerprint): ?ContentImport
    {
        if ($fingerprint === '') {
            return null;
        }

        $candidates = ContentImport::query()
            ->with('source')
            ->where('status', ContentImport::IMPORTED)
            ->whereBetween('message_at', [$message->at->subDays(self::DUPLICATE_DAYS), $message->at->addDays(self::DUPLICATE_DAYS)])
            ->get();

        return $candidates->first(fn (ContentImport $import): bool => $import->source->priority <= $source->priority
            && StoryText::similarity((string) $import->fingerprint, $fingerprint) >= StoryText::SAME_STORY);
    }

    /**
     * Downloads and cleans the story's photos; a photo that cannot be read
     * is left out rather than stopping the import.
     *
     * @param  list<TelegramMessage>  $media
     * @return list<Media>
     */
    private function photos(ContentSource $source, TelegramMessage $message, array $media, string $title, string $locale): array
    {
        $urls = array_slice(array_values(array_unique(array_merge(
            $message->photos,
            ...array_map(fn (TelegramMessage $m): array => $m->photos, $media),
        ))), 0, self::MAX_PHOTOS);

        $photos = [];
        foreach ($urls as $index => $url) {
            $path = null;
            try {
                $response = Http::withUserAgent(TelegramChannelReader::USER_AGENT)->timeout(30)->get($url);
                $body = $response->body();
                if (! $response->successful() || $body === '' || strlen($body) > self::MAX_PHOTO_BYTES) {
                    continue;
                }

                // A random name in the app's own work folder (the media pipeline's, ADR-0009).
                File::ensureDirectoryExists(storage_path('app/media-work'));
                $path = storage_path('app/media-work/import-'.Str::uuid().'.jpg');
                file_put_contents($path, $body);
                $photo = $this->upload->handle($path, "{$message->channel}-{$message->id}-".($index + 1).'.jpg');
                $photo->forceFill([
                    'alt' => [$locale => mb_substr($title, 0, 250)],
                    'credit' => $source->name,
                ])->save();
                $photos[] = $photo;
            } catch (Throwable $e) {
                Log::warning('Imported photo skipped', ['source' => $message->url(), 'error' => $e->getMessage()]);
            } finally {
                if ($path !== null && is_file($path)) {
                    @unlink($path);
                }
            }
        }

        return $photos;
    }

    private function record(ContentSource $source, TelegramMessage $message, string $status, ?string $postId, ?string $fingerprint): ContentImport
    {
        return ContentImport::query()->updateOrCreate(
            ['content_source_id' => $source->id, 'external_id' => $message->externalId()],
            ['message_at' => $message->at, 'status' => $status, 'post_id' => $postId, 'fingerprint' => $fingerprint],
        );
    }
}
