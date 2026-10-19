<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Support\BodyRenderer;
use App\Domain\Content\Support\Slugger;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One language of a page.
 *
 * @property string $page_id
 * @property string $title
 * @property ?string $excerpt
 * @property ?array<string, mixed> $body
 * @property ?string $seo_title
 * @property ?string $seo_description
 * @property bool $noindex
 * @property ContentStatus $status
 * @property ?CarbonImmutable $published_at
 * @property int $word_count
 * @property int $reading_time
 * @property-read Page $page
 */
class PageTranslation extends Translation
{
    use LogsActivity;

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return [
            'body' => 'array',
            'status' => ContentStatus::class,
            'published_at' => 'immutable_datetime',
            'noindex' => 'boolean',
            'word_count' => 'integer',
            'reading_time' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        parent::booted();

        static::saving(function (PageTranslation $translation): void {
            if (in_array($translation->slug, Page::RESERVED_SLUGS, true)) {
                $translation->slug = app(Slugger::class)->unique(
                    $translation->slug.'-page',
                    $translation->getTable(),
                    $translation->locale,
                    $translation->getKey(),
                );
            }

            if ($translation->isDirty('body') || ! $translation->exists) {
                $words = BodyRenderer::wordCount($translation->body);
                $translation->word_count = $words;
                $translation->reading_time = max(1, (int) ceil($words / PostTranslation::WORDS_PER_MINUTE));
            }

            if ($translation->status === ContentStatus::Published && $translation->published_at === null) {
                $translation->published_at = CarbonImmutable::now();
            }
        });

        // Only the page's own slug needs a redirect: sub-pages are found by
        // their last slug and sent on to their new address (PageController).
        static::updated(function (PageTranslation $translation): void {
            $old = $translation->getOriginal('slug');
            $wasPublished = $translation->getOriginal('status') === ContentStatus::Published
                || $translation->getOriginal('status') === ContentStatus::Published->value;
            if (! $translation->wasChanged('slug') || ! is_string($old) || ! $wasPublished) {
                return;
            }

            $path = $translation->page()->first()?->path($translation->locale);
            if ($path !== null) {
                $slash = strrpos($path, '/');
                $oldPath = $slash === false ? $old : substr($path, 0, $slash + 1).$old;
                Redirect::moved("/{$translation->locale}/{$oldPath}", "/{$translation->locale}/{$path}");
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('content')
            ->logOnly(['locale', 'title', 'slug', 'excerpt', 'status', 'published_at', 'noindex'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function isLive(): bool
    {
        return $this->status === ContentStatus::Published
            && $this->published_at !== null
            && $this->published_at->lessThanOrEqualTo(CarbonImmutable::now());
    }
}
