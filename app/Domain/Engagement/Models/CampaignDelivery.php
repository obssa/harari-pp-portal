<?php

namespace App\Domain\Engagement\Models;

use App\Domain\Membership\Models\Member;
use App\Domain\Volunteers\Models\Volunteer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One recipient of a campaign (ADR-0052): who, in which language, what
 * happened, and the private token of their unsubscribe and click links.
 *
 * @property int $id
 * @property int $campaign_id
 * @property string $recipient_type
 * @property string $recipient_id
 * @property string $locale
 * @property string $address_hash
 * @property string $token
 * @property string $status
 * @property ?string $error
 * @property ?CarbonImmutable $sent_at
 * @property ?CarbonImmutable $opened_at
 * @property ?CarbonImmutable $clicked_at
 * @property int $clicks
 * @property ?CarbonImmutable $unsubscribed_at
 * @property-read Campaign $campaign
 */
class CampaignDelivery extends Model
{
    public const QUEUED = 'queued';

    public const SENT = 'sent';

    public const FAILED = 'failed';

    public const SKIPPED = 'skipped';

    public const TYPES = ['subscriber' => Subscriber::class, 'member' => Member::class, 'volunteer' => Volunteer::class];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sent_at' => 'immutable_datetime',
            'opened_at' => 'immutable_datetime',
            'clicked_at' => 'immutable_datetime',
            'unsubscribed_at' => 'immutable_datetime',
            'clicks' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public static function newToken(): string
    {
        return Str::random(12);
    }

    /** The person, if their record still exists. */
    public function recipient(): Subscriber|Member|Volunteer|null
    {
        $class = self::TYPES[$this->recipient_type] ?? null;

        return $class === null ? null : $class::query()->withoutGlobalScopes()->whereKey($this->recipient_id)->first();
    }

    public function unsubscribeUrl(): string
    {
        return route('campaigns.unsubscribe', ['locale' => $this->locale, 'token' => $this->token]);
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
