<?php

namespace App\Domain\Media\Support;

use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Exceptions\RejectedUpload;
use finfo;
use ZipArchive;

/**
 * Decides what an uploaded file really is (PLAN.md §11): the content type
 * comes from the file's bytes, never from its name or the browser. Anything
 * not on MediaKind's allow-list, too large, or carrying code is refused.
 */
final class UploadInspector
{
    /** Office formats are ZIP containers; the name decides which one, the contents must agree. */
    private const OFFICE = [
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'word/document.xml'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xl/workbook.xml'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'ppt/presentation.xml'],
        'odt' => ['application/vnd.oasis.opendocument.text', 'content.xml'],
        'ods' => ['application/vnd.oasis.opendocument.spreadsheet', 'content.xml'],
        'odp' => ['application/vnd.oasis.opendocument.presentation', 'content.xml'],
    ];

    public function inspect(string $path, string $originalName): InspectedFile
    {
        $size = is_file($path) ? (int) filesize($path) : 0;
        if ($size === 0) {
            throw RejectedUpload::because('empty');
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $mime = $this->normalise((string) (new finfo(FILEINFO_MIME_TYPE))->file($path), $extension, $path);

        $kind = MediaKind::fromMime($mime);
        if ($kind === null) {
            throw RejectedUpload::because('type_not_allowed');
        }

        if ($size > $kind->maxBytes()) {
            throw RejectedUpload::because('too_large', ['max' => (int) ($kind->maxBytes() / 1024 / 1024)]);
        }

        if (in_array($mime, ['text/plain', 'text/csv', 'image/svg+xml'], true) && $this->containsCode($path)) {
            throw RejectedUpload::because('contains_code');
        }

        if (str_starts_with($mime, 'application/vnd.')) {
            $this->assertSafeOfficeFile($path);
        }

        [$width, $height] = $kind->isRaster() ? $this->dimensions($path) : [null, null];

        return new InspectedFile($kind, $mime, $kind->extensionFor($mime), $size, $width, $height);
    }

    /**
     * libmagic reports some valid files under a generic type; the file name
     * may pick the specific type only when the content supports it.
     */
    private function normalise(string $mime, string $extension, string $path): string
    {
        $mime = strtolower($mime);

        if ($extension === 'svg' && in_array($mime, ['text/xml', 'application/xml', 'text/plain', 'image/svg'], true)
            && str_contains(strtolower((string) file_get_contents($path, length: 4096)), '<svg')) {
            return 'image/svg+xml';
        }

        if (in_array($mime, ['application/zip', 'application/octet-stream'], true) && isset(self::OFFICE[$extension])) {
            [$officeMime, $requiredEntry] = self::OFFICE[$extension];

            return $this->zipHas($path, $requiredEntry) ? $officeMime : $mime;
        }

        if ($extension === 'csv' && in_array($mime, ['text/plain', 'application/csv'], true)) {
            return 'text/csv';
        }

        if ($extension === 'm4a' && $mime === 'video/mp4') {
            return 'audio/mp4';
        }

        return $mime;
    }

    private function containsCode(string $path): bool
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return true;
        }

        try {
            $carry = '';
            while (! feof($handle)) {
                $chunk = $carry.(string) fread($handle, 65536);
                if (preg_match('/<\?(php|=)/i', $chunk) === 1) {
                    return true;
                }
                $carry = substr($chunk, -8);
            }
        } finally {
            fclose($handle);
        }

        return false;
    }

    /**
     * No macros (vbaProject.bin), no paths leaving the archive.
     */
    private function assertSafeOfficeFile(string $path): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw RejectedUpload::because('corrupt');
        }

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = strtolower((string) $zip->getNameIndex($i));
                if (str_contains($entry, '..') || str_starts_with($entry, '/') || str_ends_with($entry, 'vbaproject.bin')) {
                    throw RejectedUpload::because('contains_code');
                }
            }
        } finally {
            $zip->close();
        }
    }

    private function zipHas(string $path, string $entry): bool
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return false;
        }

        $found = $zip->locateName($entry) !== false;
        $zip->close();

        return $found;
    }

    /**
     * Width and height from the header, before any decoding, so oversized
     * images are refused without filling the server's memory.
     *
     * @return array{0: int, 1: int}
     */
    private function dimensions(string $path): array
    {
        $info = @getimagesize($path);
        if ($info === false || $info[0] < 1 || $info[1] < 1) {
            throw RejectedUpload::because('corrupt');
        }

        $maxPixels = (int) config('portal.media.max_megapixels', 50) * 1_000_000;
        if ($maxPixels < $info[0] * $info[1]) {
            throw RejectedUpload::because('too_many_pixels', ['max' => (int) config('portal.media.max_megapixels', 50)]);
        }

        return [$info[0], $info[1]];
    }
}
