<?php

namespace App\Domain\Media\Actions;

use App\Domain\Content\Actions\WriteTranslations;
use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Models\Gallery;
use App\Domain\Media\Models\Media;
use App\Domain\Organisation\Models\League;
use App\Domain\Organisation\Models\Office;
use App\Domain\Translation\Support\Locales;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Saves a photo album with its languages and photos (ADR-0023). Photos are
 * library images in the order given, each with a caption per language; the
 * cover defaults to the first photo.
 */
final class SaveGallery
{
    public function __construct(
        private readonly WriteTranslations $translations,
        private readonly SyncMediaUsage $usage,
    ) {}

    /**
     * @param  array<string, mixed>  $data  fields, `translations[locale]`, `items[] = {media_id, caption[locale]}`
     */
    public function handle(Gallery $gallery, array $data, User $actor): Gallery
    {
        /** @var array<string, array<string, mixed>> $translations */
        $translations = (array) ($data['translations'] ?? []);
        WriteTranslations::requireName($translations, 'title');

        $items = $this->items($data['items'] ?? []);
        $cover = filled($data['cover_id'] ?? null) ? (int) $data['cover_id'] : ($items[0]['media_id'] ?? null);

        return DB::transaction(function () use ($gallery, $data, $translations, $actor, $items, $cover): Gallery {
            $gallery->fill([
                'cover_id' => $cover,
                'office_id' => filled($data['office_id'] ?? null) && Office::query()->whereKey($data['office_id'])->exists() ? (string) $data['office_id'] : null,
                'league_id' => filled($data['league_id'] ?? null) && League::query()->whereKey($data['league_id'])->exists() ? (string) $data['league_id'] : null,
                'taken_on' => $this->date($data['taken_on'] ?? null),
                'allow_download' => (bool) ($data['allow_download'] ?? false),
            ]);
            if (! $gallery->exists) {
                $gallery->created_by = $actor->id;
            }
            $gallery->updated_by = $actor->id;
            $gallery->save();

            $gallery->items()->delete();
            foreach ($items as $position => $item) {
                $gallery->items()->create([...$item, 'sort' => $position]);
            }

            $this->translations->handle($gallery, $translations, ['summary'], $actor->can('galleries.manage'), 'title');
            $this->usage->handle($gallery, 'cover', [$gallery->cover_id]);
            $this->usage->handle($gallery, 'photos', array_column($items, 'media_id'));

            return $gallery->refresh();
        });
    }

    /**
     * Library images only, each once, with trimmed captions.
     *
     * @return list<array{media_id: int, caption: array<string, string>|null}>
     */
    private function items(mixed $items): array
    {
        $ids = [];
        $wanted = [];
        foreach (is_array($items) ? $items : [] as $item) {
            $id = (int) (is_array($item) ? ($item['media_id'] ?? 0) : 0);
            if ($id > 0 && ! in_array($id, $ids, true)) {
                $ids[] = $id;
                $wanted[] = [$id, is_array($item['caption'] ?? null) ? $item['caption'] : []];
            }
        }

        $images = Media::query()->library()->where('kind', MediaKind::Image->value)->whereKey($ids)->pluck('id')->all();
        $clean = [];
        foreach ($wanted as [$id, $caption]) {
            if (! in_array($id, $images, true)) {
                continue;
            }
            $texts = [];
            foreach (Locales::all() as $locale) {
                $text = trim((string) ($caption[$locale] ?? ''));
                if ($text !== '') {
                    $texts[$locale] = mb_substr($text, 0, 300);
                }
            }
            $clean[] = ['media_id' => $id, 'caption' => $texts === [] ? null : $texts];
        }

        if (count($clean) > Gallery::MAX_ITEMS) {
            throw ValidationException::withMessages(['data.items' => __('gallery.validation.too_many', ['max' => Gallery::MAX_ITEMS])]);
        }

        return $clean;
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
