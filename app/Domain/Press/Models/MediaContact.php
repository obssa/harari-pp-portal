<?php

namespace App\Domain\Press\Models;

use App\Domain\Content\Concerns\BelongsToSite;
use App\Domain\Events\Models\EventRegistration;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A journalist or newsroom that receives press releases (PLAN.md §5,
 * ADR-0054). Name, email, phone and notes are encrypted; the token is their
 * private opt-out link.
 *
 * @property int $id
 * @property string $name
 * @property string $outlet
 * @property ?string $position
 * @property string $email
 * @property string $email_hash
 * @property ?string $phone
 * @property ?string $phone_hash
 * @property list<string> $languages
 * @property ?string $notes
 * @property string $token
 * @property ?CarbonImmutable $opted_out_at
 * @property ?int $created_by
 * @property CarbonImmutable $created_at
 */
class MediaContact extends Model
{
    use BelongsToSite;
    use LogsActivity;

    protected $fillable = ['name', 'outlet', 'position', 'email', 'email_hash', 'phone', 'phone_hash', 'languages', 'notes'];

    protected $hidden = ['name', 'email', 'phone', 'notes', 'token'];

    protected static function booted(): void
    {
        static::creating(function (self $contact): void {
            $contact->token ??= Str::random(24);
        });
    }

    protected function casts(): array
    {
        return [
            'name' => 'encrypted',
            'email' => 'encrypted',
            'phone' => 'encrypted',
            'notes' => 'encrypted',
            'languages' => 'array',
            'opted_out_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public static function hash(string $value): string
    {
        return EventRegistration::hash($value);
    }

    /**
     * Contacts who still take press releases.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('opted_out_at');
    }

    /**
     * @return HasMany<PressDistribution, $this>
     */
    public function distributions(): HasMany
    {
        return $this->hasMany(PressDistribution::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('press')->logOnly(['outlet', 'position', 'languages', 'opted_out_at'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
