<?php

namespace App\Domain\Press\Models;

use App\Domain\Content\Models\Post;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A press release sent to one media contact (ADR-0054): in which language,
 * what happened and who sent it.
 *
 * @property int $id
 * @property string $post_id
 * @property int $media_contact_id
 * @property string $locale
 * @property string $status
 * @property ?string $error
 * @property ?CarbonImmutable $sent_at
 * @property ?int $sent_by
 * @property CarbonImmutable $created_at
 * @property-read Post $post
 * @property-read MediaContact $contact
 * @property-read ?User $sender
 */
class PressDistribution extends Model
{
    public const QUEUED = 'queued';

    public const SENT = 'sent';

    public const FAILED = 'failed';

    public const SKIPPED = 'skipped';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sent_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
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
     * @return BelongsTo<MediaContact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(MediaContact::class, 'media_contact_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
