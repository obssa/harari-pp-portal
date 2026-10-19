<?php

namespace Tests\Support;

use Closure;
use GdImage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Real files for media tests, made on the fly (no binary fixtures in git).
 */
final class TestFiles
{
    public static function dir(): string
    {
        $dir = sys_get_temp_dir().'/hpp-test-files';
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir;
    }

    public static function path(string $extension): string
    {
        return self::dir().'/'.Str::random(16).'.'.$extension;
    }

    /**
     * @param  (Closure(GdImage, int, int): void)|null  $paint
     */
    public static function image(int $width, int $height, string $format = 'jpg', ?Closure $paint = null): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 40, 120, 200));
        if ($paint !== null) {
            $paint($image, $width, $height);
        }

        $path = self::path($format);
        match ($format) {
            'jpg' => imagejpeg($image, $path, 90),
            'png' => imagepng($image, $path),
            'webp' => imagewebp($image, $path, 90),
            'gif' => imagegif($image, $path),
        };

        return $path;
    }

    /** Left half red, right half blue: shows where a crop was taken. */
    public static function splitImage(int $width, int $height): string
    {
        return self::image($width, $height, 'png', function (GdImage $image, int $w, int $h): void {
            imagefilledrectangle($image, 0, 0, intdiv($w, 2) - 1, $h - 1, (int) imagecolorallocate($image, 255, 0, 0));
            imagefilledrectangle($image, intdiv($w, 2), 0, $w - 1, $h - 1, (int) imagecolorallocate($image, 0, 0, 255));
        });
    }

    public const EXIF_SECRET = 'GPS-SECRET-LOCATION';

    /**
     * A JPEG carrying an EXIF block (ImageDescription = EXIF_SECRET), the
     * way phones embed location and camera data.
     */
    public static function jpegWithExif(int $width = 600, int $height = 400): string
    {
        $jpeg = (string) file_get_contents(self::image($width, $height));

        $description = self::EXIF_SECRET."\0";
        $tiff = "II*\x00".pack('V', 8)
            .pack('v', 1)
            .pack('vvVV', 0x010E, 2, strlen($description), 26)
            .pack('V', 0)
            .$description;
        $app1 = "Exif\x00\x00".$tiff;
        $segment = "\xFF\xE1".pack('n', strlen($app1) + 2).$app1;

        $path = self::path('jpg');
        file_put_contents($path, substr($jpeg, 0, 2).$segment.substr($jpeg, 2));

        return $path;
    }

    /**
     * A PNG whose header claims huge dimensions (a decompression bomb):
     * only the header is real, so nothing large is ever written.
     */
    public static function pngClaiming(int $width, int $height): string
    {
        $chunk = function (string $type, string $data): string {
            return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        };

        $png = "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            .$chunk('IDAT', (string) gzcompress(str_repeat("\0", 64)))
            .$chunk('IEND', '');

        $path = self::path('png');
        file_put_contents($path, $png);

        return $path;
    }

    public static function docx(bool $withMacros = false): string
    {
        $path = self::path('docx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Sample</w:t></w:r></w:p></w:body></w:document>');
        if ($withMacros) {
            $zip->addFromString('word/vbaProject.bin', str_repeat("\x01", 64));
        }
        $zip->close();

        return $path;
    }

    public static function text(string $content, string $extension = 'txt'): string
    {
        $path = self::path($extension);
        file_put_contents($path, $content);

        return $path;
    }

    public static function svg(string $body): string
    {
        return self::text('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">'.$body.'</svg>', 'svg');
    }
}
