<?php

namespace App\Domain\Translation\Models;

use App\Domain\Translation\Support\Locales;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\TranslationLoader\LanguageLine;

/**
 * A UI string edited in the admin. Overrides the shipped lang/*.json value
 * for the locales it has text for.
 *
 * @property int $id
 * @property string $group
 * @property string $key
 * @property array<string, string> $text
 * @property ?string $context
 * @property ?string $screenshot_url
 * @property ?int $updated_by
 */
class UiTranslation extends LanguageLine
{
    use LogsActivity;

    protected $table = 'ui_translations';

    /** @var array<string, mixed> */
    protected $attributes = ['group' => '*'];

    /**
     * Only the exact locale, and empty means "not translated": the portal's
     * fallback chain (FallbackTranslator) decides what to show instead.
     */
    public function getTranslation(string $locale): ?string
    {
        $value = $this->text[$locale] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Forget the cached group for every portal locale, including ones whose
     * value was just removed.
     */
    public function flushGroupCache(): void
    {
        foreach (array_unique([...Locales::all(), ...array_keys($this->text ?? [])]) as $locale) {
            Cache::forget(static::getCacheKey($this->group, (string) $locale));
        }
    }

    /** @return BelongsTo<User, $this> */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('translations')
            ->logOnly(['group', 'key', 'text', 'context'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
