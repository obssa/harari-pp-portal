<?php

namespace App\Domain\Homepage\Models;

use App\Domain\Content\Concerns\BelongsToSite;
use App\Domain\Homepage\Enums\SlideLayout;
use App\Domain\Homepage\Enums\SlideStatus;
use App\Domain\Homepage\Support\SlideLink;
use App\Domain\Media\Models\Media;
use App\Domain\Translation\Support\Locales;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One slide of the homepage carousel (ADR-0070): its layout and picture,
 * its text and buttons in each language, when it shows and in which order.
 *
 * @property int $id
 * @property string $name
 * @property SlideLayout $layout
 * @property ?int $image_id
 * @property int $overlay
 * @property string $align
 * @property array<string, array<string, string>>|null $content language => field => text
 * @property list<array{link?: string, style?: string, label?: array<string, string>}>|null $buttons
 * @property list<string>|null $locales
 * @property bool $active
 * @property ?CarbonImmutable $starts_at
 * @property ?CarbonImmutable $ends_at
 * @property ?int $duration
 * @property int $sort
 * @property ?int $created_by
 * @property ?int $updated_by
 * @property-read ?Media $image
 */
class HeroSlide extends Model
{
    use BelongsToSite;
    use LogsActivity;

    /** Text fields of a slide, in each language. */
    public const FIELDS = ['kicker', 'title', 'highlight', 'text', 'note'];

    public const MAX_BUTTONS = 3;

    public const BUTTON_STYLES = ['primary', 'secondary'];

    public const ALIGNS = ['start', 'center'];

    protected $fillable = ['name', 'layout', 'image_id', 'overlay', 'align', 'content', 'buttons', 'locales', 'active', 'starts_at', 'ends_at', 'duration', 'sort'];

    protected $attributes = ['layout' => 'photo', 'overlay' => 80, 'align' => 'start', 'active' => true, 'sort' => 0];

    protected function casts(): array
    {
        return [
            'layout' => SlideLayout::class,
            'overlay' => 'integer',
            'content' => 'array',
            'buttons' => 'array',
            'locales' => 'array',
            'active' => 'boolean',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'duration' => 'integer',
            'sort' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('homepage')
            ->logOnly(['name', 'layout', 'image_id', 'overlay', 'align', 'content', 'buttons', 'locales', 'active', 'starts_at', 'ends_at', 'duration', 'sort'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function image(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'image_id');
    }

    /**
     * Slides on show now, in their order.
     *
     * @param  Builder<HeroSlide>  $query
     * @return Builder<HeroSlide>
     */
    public function scopeLive(Builder $query, ?CarbonInterface $now = null): Builder
    {
        $now ??= now();

        return $query->where('active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->orderBy('sort')->orderBy('id');
    }

    public function status(?CarbonInterface $now = null): SlideStatus
    {
        $now ??= now();

        return match (true) {
            ! $this->active => SlideStatus::Off,
            $this->ends_at !== null && $this->ends_at->lessThanOrEqualTo($now) => SlideStatus::Ended,
            $this->starts_at !== null && $this->starts_at->greaterThan($now) => SlideStatus::Scheduled,
            default => SlideStatus::Live,
        };
    }

    public function showsIn(string $locale): bool
    {
        $locales = $this->locales;

        return ! is_array($locales) || $locales === [] || in_array($locale, $locales, true);
    }

    /**
     * A text in this language, following the fallback chain (har → am → en),
     * with the language it was written in.
     *
     * @return array{0: string, 1: string} text and its language
     */
    public function text(string $field, string $locale): array
    {
        foreach (Locales::fallbackChain($locale) as $candidate) {
            $value = trim((string) ($this->content[$candidate][$field] ?? ''));
            if ($value !== '') {
                return [$value, $candidate];
            }
        }

        return ['', $locale];
    }

    /**
     * The heading around the words shown in colour: [before, words, after].
     * Words that are not in the heading are left out here (the banner shows
     * them after it).
     *
     * @return array{0: string, 1: string, 2: string}
     */
    public static function splitHighlight(string $title, string $highlight): array
    {
        $highlight = trim($highlight);
        $at = $highlight === '' ? false : mb_stripos($title, $highlight);
        if ($at === false) {
            return [$title, '', ''];
        }
        $length = mb_strlen($highlight);

        return [mb_substr($title, 0, $at), mb_substr($title, $at, $length), mb_substr($title, $at + $length)];
    }

    /** The title an editor sees in lists: the admin's language, else any. */
    public function adminTitle(): string
    {
        [$title] = $this->text('title', app()->getLocale());

        return $title !== '' ? $title : $this->name;
    }

    /**
     * Languages the slide has a title in.
     *
     * @return list<string>
     */
    public function languages(): array
    {
        return array_values(array_filter(Locales::all(), fn (string $locale): bool => trim((string) ($this->content[$locale]['title'] ?? '')) !== ''));
    }

    /**
     * The buttons in this language that lead somewhere and have a label.
     *
     * @return list<array{url: string, label: string, lang: string, style: string, external: bool}>
     */
    public function buttonsFor(string $locale): array
    {
        $buttons = [];
        foreach (array_slice($this->buttons ?? [], 0, self::MAX_BUTTONS) as $index => $button) {
            $url = SlideLink::url($button['link'] ?? null, $locale);
            $label = '';
            $lang = $locale;
            foreach (Locales::fallbackChain($locale) as $candidate) {
                $text = trim((string) ($button['label'][$candidate] ?? ''));
                if ($text !== '') {
                    [$label, $lang] = [$text, $candidate];
                    break;
                }
            }
            if ($url === null || $label === '') {
                continue;
            }
            $style = $button['style'] ?? null;
            $buttons[] = [
                'url' => $url,
                'label' => $label,
                'lang' => $lang,
                'style' => in_array($style, self::BUTTON_STYLES, true) ? $style : ($index === 0 ? 'primary' : 'secondary'),
                'external' => SlideLink::isExternal($button['link'] ?? null),
            ];
        }

        return $buttons;
    }
}
