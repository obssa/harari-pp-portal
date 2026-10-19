<?php

namespace App\Domain\Content\Actions;

use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostTranslation;
use App\Domain\Content\Support\BodyRenderer;
use App\Domain\Media\Actions\SyncMediaUsage;
use App\Domain\Media\Models\Media;
use App\Domain\Translation\Support\Locales;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves a post with its languages, categories and tags in one transaction,
 * keeps a version of every changed language, and records which library
 * files the post uses (PLAN.md §7).
 *
 * Rules enforced here, whatever the form allowed:
 *  - at least one language has a title;
 *  - only people with `posts.publish` publish, schedule or archive;
 *  - only people who may edit the post itself change its type, image,
 *    categories, tags or flags (translators change the text only);
 *  - a featured image needs alt text in every live language among
 *    English, Amharic and Afaan Oromo (PLAN.md §11).
 */
final class SavePost
{
    /** Languages whose alt text a featured image must have (Harari falls back to Amharic). */
    public const ALT_REQUIRED = ['en', 'am', 'om'];

    public function __construct(
        private readonly RecordRevision $revisions,
        private readonly SyncMediaUsage $usage,
    ) {}

    /**
     * @param  array<string, mixed>  $data  post fields, `categories`, `tags`, `translations[locale][field]`
     */
    public function handle(Post $post, array $data, User $actor): Post
    {
        /** @var array<string, array<string, mixed>> $translations */
        $translations = (array) ($data['translations'] ?? []);
        $this->assertHasTitle($translations);

        $canEditStructure = ! $post->exists
            ? $actor->can('posts.create')
            : ($actor->can('posts.update') || ($actor->can('posts.update_own') && $post->created_by === $actor->id));

        return DB::transaction(function () use ($post, $data, $translations, $actor, $canEditStructure): Post {
            if ($canEditStructure) {
                $post->fill([
                    'type' => $data['type'] ?? $post->type ?? 'news',
                    'featured_image_id' => filled($data['featured_image_id'] ?? null) ? (int) $data['featured_image_id'] : null,
                    'source_locale' => $data['source_locale'] ?? $post->source_locale,
                ]);

                if ($actor->can('posts.feature')) {
                    $post->is_featured = (bool) ($data['is_featured'] ?? $post->is_featured);
                    $post->is_sticky = (bool) ($data['is_sticky'] ?? $post->is_sticky);
                }
            }

            if (! $post->exists) {
                $post->created_by = $actor->id;
            }
            $post->updated_by = $actor->id;
            $post->save();

            if ($canEditStructure) {
                $post->categories()->sync(array_values((array) ($data['categories'] ?? [])));
                $post->tags()->sync(array_values((array) ($data['tags'] ?? [])));
                $post->offices()->sync(array_values((array) ($data['offices'] ?? [])));
                $post->leagues()->sync(array_values((array) ($data['leagues'] ?? [])));
                $post->leaders()->sync(array_values((array) ($data['leaders'] ?? [])));
            }

            $this->saveTranslations($post, $translations, $actor);
            $this->assertFeaturedImageDescribed($post);
            $this->syncUsage($post);

            return $post->refresh();
        });
    }

    /**
     * @param  array<string, array<string, mixed>>  $translations
     */
    private function saveTranslations(Post $post, array $translations, User $actor): void
    {
        $canPublish = $actor->can('posts.publish');
        $existing = $post->translations()->get()->keyBy('locale');

        foreach (Locales::all() as $locale) {
            $values = $translations[$locale] ?? null;
            if ($values === null) {
                continue;
            }

            /** @var PostTranslation|null $current */
            $current = $existing->get($locale);
            $title = trim((string) ($values['title'] ?? ''));

            if ($title === '') {
                if ($current !== null && ($canPublish || ! $current->status->needsPublisher())) {
                    $current->delete();
                }

                continue;
            }

            $status = ContentStatus::tryFrom((string) ($values['status'] ?? '')) ?? $current->status ?? ContentStatus::Draft;
            if ($status->needsPublisher() && ! $canPublish && $status !== $current?->status) {
                throw ValidationException::withMessages([
                    "data.translations.{$locale}.status" => __('content.validation.publish_permission'),
                ]);
            }
            if (! $canPublish && $current !== null && $current->status->needsPublisher()) {
                $status = $current->status;
            }

            $translation = $post->putTranslation($locale, [
                'title' => $title,
                'slug' => filled($values['slug'] ?? null) ? trim((string) $values['slug']) : $current?->slug,
                'kicker' => self::nullable($values['kicker'] ?? null),
                'excerpt' => self::nullable($values['excerpt'] ?? null),
                'body' => is_array($values['body'] ?? null) ? $values['body'] : null,
                'seo_title' => self::nullable($values['seo_title'] ?? null),
                'seo_description' => self::nullable($values['seo_description'] ?? null),
                'noindex' => (bool) ($values['noindex'] ?? false),
                'status' => $status,
                'published_at' => $canPublish
                    ? (filled($values['published_at'] ?? null) ? $values['published_at'] : $current?->published_at)
                    : $current?->published_at,
            ]);

            $this->revisions->handle($post, $translation);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $translations
     */
    private function assertHasTitle(array $translations): void
    {
        foreach ($translations as $values) {
            if (trim((string) ($values['title'] ?? '')) !== '') {
                return;
            }
        }

        throw ValidationException::withMessages([
            'data.translations.en.title' => __('content.validation.title_required'),
        ]);
    }

    private function assertFeaturedImageDescribed(Post $post): void
    {
        $media = $post->featured_image_id ? Media::query()->find($post->featured_image_id) : null;
        if ($media === null) {
            return;
        }

        $live = $post->translations()->whereIn('locale', self::ALT_REQUIRED)
            ->whereIn('status', [ContentStatus::Published->value, ContentStatus::Scheduled->value])
            ->pluck('locale')->all();

        $missing = array_values(array_filter($live, fn (string $locale): bool => ! $media->hasAltFor($locale)));
        if ($missing === []) {
            return;
        }

        throw ValidationException::withMessages([
            'data.featured_image_id' => __('content.validation.alt_required', [
                'languages' => implode(', ', array_map(fn (string $l): string => Locales::nativeName($l), $missing)),
            ]),
        ]);
    }

    private function syncUsage(Post $post): void
    {
        $this->usage->handle($post, 'featured_image', [$post->featured_image_id]);

        $bodyIds = [];
        foreach ($post->translations()->get() as $translation) {
            $bodyIds = [...$bodyIds, ...BodyRenderer::mediaIds($translation->body)];
        }
        $this->usage->handle($post, 'body', $bodyIds);
    }

    private static function nullable(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
