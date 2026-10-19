<?php

namespace App\Domain\Import\Models;

use App\Domain\Content\Concerns\BelongsToSite;
use App\Domain\Content\Models\Post;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message read from a source and what became of it: a draft post, a
 * duplicate of a post already imported, or skipped (photos without text).
 *
 * @property int $id
 * @property int $content_source_id
 * @property string $external_id
 * @property CarbonImmutable $message_at
 * @property string $status
 * @property ?string $post_id
 * @property ?string $fingerprint
 * @property-read ContentSource $source
 * @property-read ?Post $post
 */
class ContentImport extends Model
{
    use BelongsToSite;

    public const IMPORTED = 'imported';

    public const DUPLICATE = 'duplicate';

    public const SKIPPED = 'skipped';

    protected $fillable = ['content_source_id', 'external_id', 'message_at', 'status', 'post_id', 'fingerprint'];

    protected function casts(): array
    {
        return ['message_at' => 'immutable_datetime'];
    }

    /**
     * @return BelongsTo<ContentSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(ContentSource::class, 'content_source_id');
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
