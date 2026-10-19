<?php

namespace App\Filament\Support;

use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Models\Media;
use Filament\Forms\Components\Select;

/**
 * Chooses files from the media library by title, with a thumbnail next to
 * each photo (featured images, image and gallery blocks, file blocks).
 */
final class MediaPicker
{
    /**
     * @param  list<MediaKind>  $kinds
     */
    public static function make(string $name, array $kinds = [MediaKind::Image], bool $multiple = false): Select
    {
        $kindValues = array_map(fn (MediaKind $kind): string => $kind->value, $kinds);

        return Select::make($name)
            ->multiple($multiple)
            ->searchable()
            ->allowHtml()
            ->getSearchResultsUsing(fn (string $search): array => self::options(
                Media::query()->library()->whereIn('kind', $kindValues)
                    ->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('file_name', 'like', "%{$search}%"))
                    ->latest()->limit(30)->get(),
            ))
            ->options(fn (): array => self::options(
                Media::query()->library()->whereIn('kind', $kindValues)->latest()->limit(30)->get(),
            ))
            ->getOptionLabelUsing(fn (mixed $value): ?string => ($media = Media::query()->library()->find($value)) ? self::label($media) : null)
            ->getOptionLabelsUsing(fn (array $values): array => self::options(Media::query()->library()->whereIn('id', $values)->get()));
    }

    /**
     * @param  iterable<Media>  $media
     * @return array<int, string>
     */
    private static function options(iterable $media): array
    {
        $options = [];
        foreach ($media as $item) {
            $options[$item->id] = self::label($item);
        }

        return $options;
    }

    private static function label(Media $media): string
    {
        $name = e($media->name);
        $preview = $media->adminPreviewUrl();

        if ($preview === null) {
            return $name;
        }

        return '<span style="display:inline-flex;align-items:center;gap:.5rem">'
            .'<img src="'.e($preview).'" alt="" style="width:2rem;height:2rem;object-fit:cover;border-radius:.25rem">'
            .$name.'</span>';
    }
}
