<?php

namespace App\Domain\Content\Actions;

use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostTranslation;
use Illuminate\Support\Facades\DB;

/**
 * Copies a post with its languages, categories, tags and links as a new draft
 * (every language back to Draft, new slugs, nothing published).
 */
final class DuplicatePost
{
    public function __construct(private readonly RecordRevision $revisions) {}

    public function handle(Post $original): Post
    {
        $original->loadMissing(['translations', 'categories', 'tags', 'offices', 'leagues', 'leaders']);

        return DB::transaction(function () use ($original): Post {
            $copy = new Post($original->only(['type', 'featured_image_id', 'source_locale']));
            $copy->save();
            $copy->categories()->sync($original->categories->modelKeys());
            $copy->tags()->sync($original->tags->modelKeys());
            $copy->offices()->sync($original->offices->modelKeys());
            $copy->leagues()->sync($original->leagues->modelKeys());
            $copy->leaders()->sync($original->leaders->modelKeys());

            foreach ($original->translations as $translation) {
                /** @var PostTranslation $translation */
                $new = $copy->putTranslation($translation->locale, [
                    ...$translation->only(['title', 'kicker', 'excerpt', 'body', 'seo_title', 'seo_description', 'noindex']),
                    'slug' => null,
                    'status' => ContentStatus::Draft,
                    'published_at' => null,
                ]);
                $this->revisions->handle($copy, $new, __('content.revision.duplicated'));
            }

            return $copy->refresh();
        });
    }
}
