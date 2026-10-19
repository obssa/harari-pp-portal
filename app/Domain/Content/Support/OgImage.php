<?php

namespace App\Domain\Content\Support;

use GdImage;
use Illuminate\Support\Facades\Storage;

/**
 * Sharing images for pages without a photo (PLAN.md §11: auto-generated OG
 * images with title and logo): 1200×630 PNG in the party colours with the
 * title in Latin or Ethiopic letters. Made once per title and kept on the
 * public disk.
 */
final class OgImage
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    private const MARGIN = 80;

    private const TITLE_SIZE = 54;

    private const MAX_LINES = 4;

    /**
     * Public URL of the image for this title, made on first use.
     */
    public function url(string $key, string $title, string $kicker): string
    {
        $path = 'og/'.$key.'-'.substr(hash('sha256', $title.'|'.$kicker.'|v1'), 0, 16).'.png';
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            $disk->put($path, $this->render($title, $kicker));
        }

        return $disk->url($path);
    }

    public function render(string $title, string $kicker): string
    {
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

        // Party blue, a little darker towards the bottom, with the Harar accent bar.
        for ($y = 0; $y < self::HEIGHT; $y++) {
            $t = $y / self::HEIGHT;
            $colour = (int) imagecolorallocate($image, (int) (49 - 12 * $t), (int) (81 - 24 * $t), (int) (237 - 36 * $t));
            imageline($image, 0, $y, self::WIDTH, $y, $colour);
        }
        imagefilledrectangle($image, 0, self::HEIGHT - 14, self::WIDTH, self::HEIGHT, (int) imagecolorallocate($image, 205, 98, 52));

        $white = (int) imagecolorallocate($image, 255, 255, 255);
        $soft = (int) imagecolorallocate($image, 223, 228, 253);

        $this->text($image, mb_strtoupper($kicker), 26, self::MARGIN, 120, $soft);

        $y = 210;
        foreach ($this->wrap($title, self::TITLE_SIZE, self::WIDTH - 2 * self::MARGIN) as $line) {
            $this->text($image, $line, self::TITLE_SIZE, self::MARGIN, $y, $white);
            $y += (int) (self::TITLE_SIZE * 1.45);
        }

        $this->text($image, __('site.name').' · '.__('site.office'), 24, self::MARGIN, self::HEIGHT - 60, $soft);

        ob_start();
        imagepng($image, null, 6);

        return (string) ob_get_clean();
    }

    /**
     * @return list<string>
     */
    private function wrap(string $text, int $size, int $width): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        $lines = [];
        $line = '';

        foreach ($words as $word) {
            $candidate = $line === '' ? $word : $line.' '.$word;
            if ($line !== '' && $this->measure($candidate, $size) > $width) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $candidate;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }

        if (count($lines) > self::MAX_LINES) {
            $lines = array_slice($lines, 0, self::MAX_LINES);
            $lines[self::MAX_LINES - 1] = rtrim($lines[self::MAX_LINES - 1], ' .,;:').'…';
        }

        return $lines;
    }

    private function text(GdImage $image, string $text, int $size, int $x, int $y, int $colour): void
    {
        foreach ($this->runs($text) as [$run, $font]) {
            $box = imagettftext($image, $size, 0, $x, $y, $colour, $font, $run);
            $x = $box === false ? $x : $box[2];
        }
    }

    private function measure(string $text, int $size): int
    {
        $width = 0;
        foreach ($this->runs($text) as [$run, $font]) {
            $box = imagettfbbox($size, 0, $font, $run);
            $width += $box === false ? 0 : $box[2] - $box[0];
        }

        return $width;
    }

    /**
     * Splits text into runs of Ethiopic and other letters, each with its font
     * (GD has no font fallback).
     *
     * @return list<array{0: string, 1: string}>
     */
    private function runs(string $text): array
    {
        $latin = resource_path('fonts/og/Inter-Bold.ttf');
        $ethiopic = resource_path('fonts/og/NotoSansEthiopic-Bold.ttf');

        $runs = [];
        $current = '';
        $currentFont = null;
        foreach (mb_str_split($text) as $char) {
            $code = mb_ord($char);
            $font = ($code >= 0x1200 && $code <= 0x137F) ? $ethiopic
                : ($char === ' ' && $currentFont !== null ? $currentFont : $latin);
            if ($font !== $currentFont && $current !== '') {
                $runs[] = [$current, (string) $currentFont];
                $current = '';
            }
            $current .= $char;
            $currentFont = $font;
        }
        if ($current !== '') {
            $runs[] = [$current, (string) $currentFont];
        }

        return $runs;
    }
}
