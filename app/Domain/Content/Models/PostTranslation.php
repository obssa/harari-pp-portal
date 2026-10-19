<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Support\BodyRenderer;
use App\Domain\Search\Concerns\SearchableTranslation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One language of a post. Word count and reading time follow the body.
 *
 * @property string $post_id
 * @property string $title
 * @property ?string $kicker
 * @property ?string $excerpt
 * @property ?array<string, mixed> $body
 * @property ?string $seo_title
 * @property ?string $seo_description
 * @property bool $noindex
 * @property ContentStatus $status
 * @property ?CarbonImmutable $published_at
 * @property int $word_count
 * @property int $reading_time
 * @property bool $machine_translated
 * @property ?int $reviewed_by
 * @property-read Post $post
 */
class PostTranslation extends Translation
{
    use LogsActivity;
    use SearchableTranslation;

    /** Words a reader manages per minute, for the reading time. */
    public const WORDS_PER_MINUTE = 200;

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return [
            'body' => 'array',
            'status' => ContentStatus::class,
            'published_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'noindex' => 'boolean',
            'machine_translated' => 'boolean',
            'word_count' => 'integer',
            'reading_time' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        parent::booted();

        static::saving(function (PostTranslation $translation): void {
            if ($translation->isDirty('body') || ! $translation->exists) {
                $words = BodyRenderer::wordCount($translation->body);
                $translation->word_count = $words;
                $translation->reading_time = max(1, (int) ceil($words / self::WORDS_PER_MINUTE));
            }

            if ($translation->status === ContentStatus::Published && $translation->published_at === null) {
                $translation->published_at = CarbonImmutable::now();
            }
        });

        static::updated(function (PostTranslation $translation): void {
            $old = $translation->getOriginal('slug');
            $wasPublished = $translation->getOriginal('status') === ContentStatus::Published
                || $translation->getOriginal('status') === ContentStatus::Published->value;

            if ($translation->wasChanged('slug') && is_string($old) && $wasPublished) {
                Redirect::moved(
                    "/{$translation->locale}/news/{$old}",
                    "/{$translation->locale}/news/{$translation->slug}",
                );
            }
        });
    }

    /**
     * @return list<?string>
     */
    public function searchableParts(): array
    {
        return [$this->title, $this->kicker, $this->excerpt, BodyRenderer::toText($this->body)];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('content')
            ->logOnly(['locale', 'title', 'slug', 'kicker', 'excerpt', 'status', 'published_at', 'noindex'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isLive(): bool
    {
        return $this->status === ContentStatus::Published
            && $this->published_at !== null
            && $this->published_at->lessThanOrEqualTo(CarbonImmutable::now());
    }
}
