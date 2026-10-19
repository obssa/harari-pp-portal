<?php

namespace App\Domain\Content\Queries;

use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Enums\PostType;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostTranslation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Posts as the public site sees them (PLAN.md §4 News & Media): live in the
 * page's language, or — for languages with a content fallback such as
 * Harari → Amharic — live in the fallback language when no translation of
 * their own exists. Sticky posts first, then newest.
 */
final class PublicPosts
{
    /**
     * Languages whose posts may stand in for this one, in order.
     *
     * @return list<string>
     */
    public static function languages(string $locale): array
    {
        return [$locale, ...(array) config("portal.content_fallbacks.{$locale}", [])];
    }

    /**
     * @param  array{type?: ?PostType, category?: ?string, tag?: ?string}  $filters
     * @return LengthAwarePaginator<int, Post>
     */
    public function paginate(string $locale, array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        return $this->query($locale, $filters)->paginate($perPage)->withQueryString();
    }

    /**
     * Latest live posts linked to an office, league or leader.
     *
     * @param  'offices'|'leagues'|'leaders'  $relation
     * @return Collection<int, Post>
     */
    public function linkedTo(string $locale, string $relation, string $id, int $limit = 6): Collection
    {
        return $this->query($locale, [])
            ->whereHas($relation, fn (Builder $q) => $q->whereKey($id))
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Post>
     */
    public function latest(string $locale, int $limit, ?PostType $type = null): Collection
    {
        return $this->query($locale, ['type' => $type])->limit($limit)->get();
    }

    /**
     * The live translation a reader of this language should see.
     */
    public static function shown(Post $post, string $locale): ?PostTranslation
    {
        foreach (self::languages($locale) as $candidate) {
            $translation = $post->translation($candidate, fallback: false);
            if ($translation instanceof PostTranslation && $translation->isLive()) {
                return $translation;
            }
        }

        return null;
    }

    /**
     * @param  array{type?: ?PostType, category?: ?string, tag?: ?string}  $filters
     * @return Builder<Post>
     */
    private function query(string $locale, array $filters): Builder
    {
        $languages = self::languages($locale);

        return Post::query()
            ->whereHas('translations', fn (Builder $q) => $q
                ->whereIn('locale', $languages)
                ->where('status', ContentStatus::Published->value)
                ->where('published_at', '<=', now()))
            ->when($filters['type'] ?? null, fn (Builder $q, PostType $type) => $q->where('type', $type->value))
            ->when($filters['category'] ?? null, fn (Builder $q, string $slug) => $q->whereHas(
                'categories.translations',
                fn (Builder $c) => $c->where('slug', $slug)->whereIn('locale', $languages),
            ))
            ->when($filters['tag'] ?? null, fn (Builder $q, string $slug) => $q->whereHas(
                'tags.translations',
                fn (Builder $t) => $t->where('slug', $slug)->whereIn('locale', $languages),
            ))
            ->with([
                'translations' => fn ($q) => $q->whereIn('locale', $languages),
                'featuredImage',
                'categories.translations',
            ])
            ->orderByDesc('is_sticky')
            ->orderByDesc(
                PostTranslation::query()->select('published_at')
                    ->whereColumn('post_id', 'posts.id')
                    ->whereIn('locale', $languages)
                    ->where('status', ContentStatus::Published->value)
                    ->orderByDesc('published_at')
                    ->limit(1),
            );
    }
}
