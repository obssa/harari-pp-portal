<?php

namespace App\Domain\Social\Models;

use App\Domain\Content\Models\Post;
use App\Domain\Social\Enums\Platform;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A post shared to one of the party's Facebook pages or Telegram channels
 * (ADR-0059), with what was sent and what came back.
 *
 * @property int $id
 * @property string $post_id
 * @property ?string $social_source_id
 * @property Platform $platform
 * @property string $account
 * @property string $locale
 * @property string $message
 * @property string $status
 * @property ?string $external_id
 * @property ?string $external_url
 * @property ?string $error
 * @property ?int $shared_by
 * @property ?CarbonImmutable $shared_at
 * @property CarbonImmutable $created_at
 * @property-read Post $post
 * @property-read ?SocialSource $source
 * @property-read ?User $sharer
 */
class SocialShare extends Model
{
    public const QUEUED = 'queued';

    public const SENT = 'sent';

    public const FAILED = 'failed';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'shared_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @return BelongsTo<SocialSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(SocialSource::class, 'social_source_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sharer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by');
    }
}
