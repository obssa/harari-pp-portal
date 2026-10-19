<?php

namespace App\Domain\Media\Support;

use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Models\Media;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\Conversions\Conversion;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;

/**
 * The copies made of every library photo (PLAN.md §7, §9): a small preview
 * for the admin, then each preset of config('portal.media.presets') at each
 * width, cropped around the focal point, in WebP and (when the server can)
 * AVIF. Re-encoding drops all metadata, including EXIF and GPS.
 */
final class ImageConversions
{
    public const THUMB = 'thumb';

    public const WEBP_QUALITY = 80;

    public const AVIF_QUALITY = 60;

    public static function register(HasMedia $owner, ?BaseMedia $media): void
    {
        if (! $media instanceof Media || $media->kind !== MediaKind::Image) {
            return;
        }

        $thumb = $owner->addMediaConversion(self::THUMB)->performOnCollections(Media::LIBRARY)->nonQueued();
        $thumb->fit(Fit::Max, 320, 320);
        self::encode($thumb, 'webp', self::WEBP_QUALITY);

        $avif = self::avifEnabled();
        foreach (self::sizes($media) as $name => [$width, $height]) {
            $formats = $avif ? [$name => 'webp', $name.'-avif' => 'avif'] : [$name => 'webp'];

            foreach ($formats as $conversionName => $format) {
                $conversion = $owner->addMediaConversion($conversionName)->performOnCollections(Media::LIBRARY);
                $conversion->focalCropAndResize($width, $height, $media->focal_x, $media->focal_y);
                self::encode($conversion, $format, $format === 'avif' ? self::AVIF_QUALITY : self::WEBP_QUALITY);
            }
        }
    }

    private static function encode(Conversion $conversion, string $format, int $quality): void
    {
        $conversion->format($format);
        $conversion->quality($quality);
    }

    /**
     * Conversion name => [width, height] for this image. Widths larger than
     * the original are skipped, but the smallest width of a preset is kept.
     *
     * @return array<string, array{0: int, 1: int}>
     */
    public static function sizes(Media $media): array
    {
        $sizes = [];
        foreach (self::presets() as $preset => $definition) {
            [$ratioX, $ratioY] = $definition['ratio'];
            $widths = $definition['widths'];
            sort($widths);

            foreach ($widths as $index => $width) {
                if ($index > 0 && $media->width !== null && $width > $media->width) {
                    break;
                }
                $sizes["{$preset}-{$width}"] = [$width, (int) round($width * $ratioY / $ratioX)];
            }
        }

        return $sizes;
    }

    /**
     * Conversion names of one preset for this image, smallest first.
     *
     * @return array<string, int> name => width
     */
    public static function widthsFor(Media $media, string $preset): array
    {
        $widths = [];
        foreach (self::sizes($media) as $name => [$width]) {
            if (str_starts_with($name, $preset.'-')) {
                $widths[$name] = $width;
            }
        }

        return $widths;
    }

    /**
     * @return array<string, array{ratio: array{0: int, 1: int}, widths: list<int>}>
     */
    public static function presets(): array
    {
        /** @var array<string, array{ratio: array{0: int, 1: int}, widths: list<int>}> */
        return config('portal.media.presets', []);
    }

    public static function avifEnabled(): bool
    {
        $setting = config('portal.media.avif', 'auto');

        if ($setting === 'auto') {
            return self::serverSupportsAvif();
        }

        return filter_var($setting, FILTER_VALIDATE_BOOLEAN) && self::serverSupportsAvif();
    }

    public static function serverSupportsAvif(): bool
    {
        return function_exists('imageavif') && (bool) (gd_info()['AVIF Support'] ?? false);
    }
}
