<?php

namespace App\Domain\Homepage\Actions;

use App\Domain\Homepage\Enums\SlideLayout;
use App\Domain\Homepage\Models\HeroSlide;
use App\Domain\Homepage\Support\SlideLink;
use App\Domain\Media\Actions\SyncMediaUsage;
use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Models\Media;
use App\Domain\Translation\Support\Locales;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Saves a slide of the homepage carousel (ADR-0070): checks its layout,
 * picture, texts, buttons and dates, keeps its place in the order, and
 * records which library picture it uses.
 */
final class SaveHeroSlide
{
    /** Longest text of each field, in characters. */
    public const LIMITS = ['kicker' => 80, 'title' => 160, 'highlight' => 60, 'text' => 400, 'note' => 200];

    public const LABEL_LIMIT = 40;

    public function __construct(private readonly SyncMediaUsage $usage) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(HeroSlide $slide, array $data, User $actor): HeroSlide
    {
        $layout = SlideLayout::tryFrom((string) ($data['layout'] ?? ''));
        if ($layout === null) {
            throw ValidationException::withMessages(['data.layout' => __('homepage.carousel.validation.layout')]);
        }

        $name = mb_substr(trim(strip_tags((string) ($data['name'] ?? ''))), 0, 120);
        $content = $this->content((array) ($data['content'] ?? []));
        if ($name === '') {
            $name = mb_substr($content[Locales::all()[0]]['title'] ?? (array_values($content)[0]['title'] ?? ''), 0, 120);
        }
        if (! collect($content)->contains(fn (array $fields): bool => ($fields['title'] ?? '') !== '')) {
            throw ValidationException::withMessages(['data.content.'.Locales::all()[0].'.title' => __('homepage.carousel.validation.title')]);
        }

        $imageId = filled($data['image_id'] ?? null) ? (int) $data['image_id'] : null;
        if ($imageId !== null && ! Media::query()->whereKey($imageId)->whereIn('kind', [MediaKind::Image->value, MediaKind::Vector->value])->exists()) {
            $imageId = null;
        }
        if ($imageId === null && $layout->needsImage()) {
            throw ValidationException::withMessages(['data.image_id' => __('homepage.carousel.validation.image')]);
        }

        $startsAt = $this->date($data['starts_at'] ?? null);
        $endsAt = $this->date($data['ends_at'] ?? null);
        if ($startsAt !== null && $endsAt !== null && $endsAt->lessThanOrEqualTo($startsAt)) {
            throw ValidationException::withMessages(['data.ends_at' => __('homepage.carousel.validation.dates')]);
        }

        $buttons = $this->buttons((array) ($data['buttons'] ?? []));
        $locales = array_values(array_intersect(Locales::all(), array_map('strval', (array) ($data['locales'] ?? []))));
        $duration = filled($data['duration'] ?? null) ? max(3, min(30, (int) $data['duration'])) : null;

        return DB::transaction(function () use ($slide, $data, $actor, $layout, $name, $content, $imageId, $startsAt, $endsAt, $buttons, $locales, $duration): HeroSlide {
            $slide->fill([
                'name' => $name,
                'layout' => $layout,
                'image_id' => $imageId,
                'overlay' => max(0, min(95, (int) ($data['overlay'] ?? 80))),
                'align' => in_array($data['align'] ?? null, HeroSlide::ALIGNS, true) ? $data['align'] : 'start',
                'content' => $content,
                'buttons' => $buttons,
                'locales' => $locales === [] || count($locales) === count(Locales::all()) ? null : $locales,
                'active' => (bool) ($data['active'] ?? true),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'duration' => $duration,
            ]);
            if (! $slide->exists) {
                $slide->sort = isset($data['sort']) ? max(0, (int) $data['sort']) : ((int) HeroSlide::query()->max('sort')) + 1;
                $slide->created_by = $actor->id;
            }
            $slide->updated_by = $actor->id;
            $slide->save();

            $this->usage->handle($slide, 'image', [$slide->image_id]);

            return $slide->refresh();
        });
    }

    /**
     * Plain text of each field in each language, trimmed to its limit;
     * languages with nothing written are left out.
     *
     * @param  array<mixed>  $input
     * @return array<string, array<string, string>>
     */
    private function content(array $input): array
    {
        $content = [];
        foreach (Locales::all() as $locale) {
            $fields = [];
            foreach (self::LIMITS as $field => $limit) {
                $value = trim(strip_tags((string) ($input[$locale][$field] ?? '')));
                if ($value !== '') {
                    $fields[$field] = mb_substr($value, 0, $limit);
                }
            }
            if ($fields !== []) {
                $content[$locale] = $fields;
            }
        }

        return $content;
    }

    /**
     * Buttons as stored: the link (composed from the form's parts, or given
     * whole), its style and its label in each language.
     *
     * @param  array<mixed>  $input
     * @return list<array{link: string, style: string, label: array<string, string>}>
     */
    private function buttons(array $input): array
    {
        $buttons = [];
        foreach (array_values($input) as $index => $button) {
            if (! is_array($button)) {
                continue;
            }
            $link = isset($button['link']) ? trim((string) $button['link']) : self::compose($button);
            $labels = [];
            foreach (Locales::all() as $locale) {
                $label = trim(strip_tags((string) ($button['label'][$locale] ?? '')));
                if ($label !== '') {
                    $labels[$locale] = mb_substr($label, 0, self::LABEL_LIMIT);
                }
            }
            if ($link === '' && $labels === []) {
                continue;
            }
            if (! SlideLink::isValid($link)) {
                throw ValidationException::withMessages(["data.buttons.{$index}.kind" => __('homepage.carousel.validation.link')]);
            }
            if ($labels === []) {
                throw ValidationException::withMessages(["data.buttons.{$index}.label.".Locales::all()[0] => __('homepage.carousel.validation.label')]);
            }
            $buttons[] = [
                'link' => $link,
                'style' => in_array($button['style'] ?? null, HeroSlide::BUTTON_STYLES, true) ? (string) $button['style'] : (count($buttons) === 0 ? 'primary' : 'secondary'),
                'label' => $labels,
            ];
        }
        if (count($buttons) > HeroSlide::MAX_BUTTONS) {
            throw ValidationException::withMessages(['data.buttons' => __('homepage.carousel.validation.buttons', ['count' => HeroSlide::MAX_BUTTONS])]);
        }

        return $buttons;
    }

    /**
     * The stored link from the admin form's parts.
     *
     * @param  array<mixed>  $button
     */
    public static function compose(array $button): string
    {
        $kind = (string) ($button['kind'] ?? '');
        $value = match ($kind) {
            'route' => (string) ($button['route'] ?? ''),
            'leader', 'page', 'post' => (string) ($button[$kind] ?? ''),
            'url' => trim((string) ($button['url'] ?? '')),
            default => '',
        };

        return $kind !== '' && $value !== '' ? "{$kind}:{$value}" : '';
    }

    /**
     * The admin form's parts of a stored link.
     *
     * @return array<string, string>
     */
    public static function decompose(?string $link): array
    {
        [$kind, $value] = SlideLink::split($link);

        return match ($kind) {
            'route' => ['kind' => 'route', 'route' => $value],
            'leader', 'page', 'post' => ['kind' => $kind, $kind => $value],
            'url' => ['kind' => 'url', 'url' => $value],
            default => ['kind' => 'route'],
        };
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }
}
