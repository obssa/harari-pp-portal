<?php

namespace App\View\Components;

use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Support\ImageConversions;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * A responsive library image (PLAN.md §9 performance): AVIF and WebP srcsets
 * of one preset, width/height to avoid layout shift, the focal point as
 * object-position, and alt text in the page's language.
 *
 *   <x-picture :media="$post->featuredImage" preset="landscape" sizes="(min-width: 64rem) 50vw, 100vw" />
 *
 * Copies still waiting in the queue are left out; until one exists the
 * original is shown.
 */
final class Picture extends Component
{
    public ?string $src = null;

    public ?string $webpSrcset = null;

    public ?string $avifSrcset = null;

    public ?int $width = null;

    public ?int $height = null;

    public string $alt = '';

    public string $objectPosition = '50% 50%';

    public function __construct(
        public ?Media $media,
        public string $preset = 'landscape',
        public string $sizes = '100vw',
        public string $loading = 'lazy',
        public ?string $fetchpriority = null,
        ?string $alt = null,
    ) {
        if ($media === null) {
            return;
        }

        $this->alt = $alt ?? $media->altFor();
        $this->objectPosition = "{$media->focal_x}% {$media->focal_y}%";

        if ($media->kind !== MediaKind::Image) {
            $this->src = $media->getUrl();

            return;
        }

        $webp = [];
        $avif = [];
        foreach (ImageConversions::widthsFor($media, $preset) as $name => $width) {
            if ($media->hasGeneratedConversion($name)) {
                $webp[$width] = $media->getUrl($name);
            }
            if ($media->hasGeneratedConversion($name.'-avif')) {
                $avif[$width] = $media->getUrl($name.'-avif');
            }
        }

        if ($webp === []) {
            $this->src = $media->getUrl();
            $this->width = $media->width;
            $this->height = $media->height;

            return;
        }

        $this->webpSrcset = self::srcset($webp);
        $this->avifSrcset = $avif === [] ? null : self::srcset($avif);

        // The middle width is a sensible default for browsers that ignore srcset.
        $widths = array_keys($webp);
        $fallback = $widths[intdiv(count($widths) - 1, 2)];
        $this->src = $webp[$fallback];

        [$ratioX, $ratioY] = ImageConversions::presets()[$preset]['ratio'] ?? [16, 9];
        $this->width = $fallback;
        $this->height = (int) round($fallback * $ratioY / $ratioX);
    }

    public function shouldRender(): bool
    {
        return $this->src !== null;
    }

    public function render(): View
    {
        return view('components.picture');
    }

    /**
     * @param  array<int, string>  $urls  width => URL
     */
    private static function srcset(array $urls): string
    {
        ksort($urls);

        return implode(', ', array_map(fn (int $width, string $url): string => "{$url} {$width}w", array_keys($urls), $urls));
    }
}
