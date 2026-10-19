<?php

namespace App\Domain\Homepage\Models;

use App\Domain\Content\Concerns\BelongsToSite;
use App\Domain\Homepage\Enums\SectionKey;
use App\Domain\Translation\Support\Locales;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One section of the homepage (PLAN.md §7 Homepage builder, §10
 * `homepage_sections`): on or off, its place, its settings (limit,
 * headings and languages it shows in, items) and hand-picked posts.
 *
 * @property int $id
 * @property SectionKey $key
 * @property bool $enabled
 * @property int $sort
 * @property array<string, mixed>|null $settings
 * @property list<string>|null $curated_ids
 */
class HomepageSection extends Model
{
    use BelongsToSite;
    use LogsActivity;

    protected $fillable = ['key', 'enabled', 'sort', 'settings', 'curated_ids', 'updated_by'];

    protected function casts(): array
    {
        return [
            'key' => SectionKey::class,
            'enabled' => 'boolean',
            'sort' => 'integer',
            'settings' => 'array',
            'curated_ids' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('homepage')
            ->logOnly(['key', 'enabled', 'sort', 'settings', 'curated_ids'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function setting(string $name, mixed $default = null): mixed
    {
        return data_get($this->settings ?? [], $name, $default);
    }

    public function limit(): int
    {
        $limit = (int) $this->setting('limit', $this->key->defaultLimit());

        return max(1, min($this->key->maxItems(), $limit));
    }

    /** The editor's heading for this language, or the standard one. */
    public function heading(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $custom = trim((string) $this->setting("titles.{$locale}", ''));

        return $custom !== '' ? $custom : $this->key->label($locale);
    }

    public function showsIn(string $locale): bool
    {
        $locales = $this->setting('locales');

        return ! is_array($locales) || $locales === [] || in_array($locale, $locales, true);
    }

    /**
     * A per-language text from the section's items or settings, following
     * the language fallback chain.
     *
     * @param  array<string, mixed>|null  $values  locale => text
     */
    public static function localized(?array $values, ?string $locale = null): string
    {
        foreach (Locales::fallbackChain($locale ?? app()->getLocale()) as $candidate) {
            $text = trim((string) ($values[$candidate] ?? ''));
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }
}
