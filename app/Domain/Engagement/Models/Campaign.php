<?php

namespace App\Domain\Engagement\Models;

use App\Domain\Content\Concerns\BelongsToSite;
use App\Domain\Engagement\Enums\CampaignStatus;
use App\Domain\Translation\Support\Locales;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * An email or SMS campaign (PLAN.md §5, ADR-0052): the text in each language
 * and who receives it. Email text is `subject` and `body` (Markdown); SMS
 * text is `text`.
 *
 * @property int $id
 * @property string $name
 * @property string $channel
 * @property array{type?: string, topics?: list<string>, locales?: list<string>, woredas?: list<string>, statuses?: list<string>} $audience
 * @property array<string, array{subject?: ?string, body?: ?string, text?: ?string}> $content
 * @property CampaignStatus $status
 * @property ?CarbonImmutable $scheduled_at
 * @property ?CarbonImmutable $started_at
 * @property ?CarbonImmutable $finished_at
 * @property ?int $created_by
 * @property ?int $sent_by
 * @property CarbonImmutable $created_at
 * @property-read ?User $creator
 * @property-read ?User $sender
 * @property-read int $deliveries_count
 * @property-read int $sent_count
 */
class Campaign extends Model
{
    use BelongsToSite;
    use LogsActivity;

    public const EMAIL = 'email';

    public const SMS = 'sms';

    protected $fillable = ['name', 'channel', 'audience', 'content'];

    protected function casts(): array
    {
        return [
            'audience' => 'array',
            'content' => 'array',
            'status' => CampaignStatus::class,
            'scheduled_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * Languages with complete text for this channel.
     *
     * @return list<string>
     */
    public function languages(): array
    {
        return array_values(array_filter(Locales::all(), fn (string $locale): bool => $this->isWritten($locale)));
    }

    public function isWritten(string $locale): bool
    {
        $text = $this->content[$locale] ?? [];

        return $this->channel === self::EMAIL
            ? filled($text['subject'] ?? null) && filled($text['body'] ?? null)
            : filled($text['text'] ?? null);
    }

    /** The language to send a recipient: theirs, then its fallbacks, then any written one. */
    public function languageFor(string $locale): ?string
    {
        foreach (Locales::fallbackChain($locale) as $candidate) {
            if ($this->isWritten($candidate)) {
                return $candidate;
            }
        }

        return $this->languages()[0] ?? null;
    }

    /**
     * @return HasMany<CampaignDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(CampaignDelivery::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('campaigns')->logOnly(['name', 'channel', 'audience', 'status', 'scheduled_at'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
