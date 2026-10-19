<?php

namespace App\Domain\Webhooks\Models;

use App\Domain\Content\Concerns\BelongsToSite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * An outside address told about content events (PLAN.md §9 domain events
 * and webhooks). Requests are signed with the secret (HMAC-SHA256).
 *
 * @property int $id
 * @property string $name
 * @property string $url
 * @property string $secret
 * @property list<string> $events
 * @property bool $is_active
 */
class Webhook extends Model
{
    use BelongsToSite;
    use LogsActivity;

    /** Events a webhook can subscribe to. */
    public const EVENTS = ['post.published', 'post.scheduled', 'post.unpublished', 'post.archived'];

    protected $fillable = ['name', 'url', 'secret', 'events', 'is_active'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'events' => 'array',
            'is_active' => 'boolean',
            'last_delivered_at' => 'immutable_datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('integrations')->logOnly(['name', 'url', 'events', 'is_active'])->logOnlyDirty();
    }

    /**
     * @return HasMany<WebhookDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function listensTo(string $event): bool
    {
        return $this->is_active && in_array($event, $this->events, true);
    }
}
