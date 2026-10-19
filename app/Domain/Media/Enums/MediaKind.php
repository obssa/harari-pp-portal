<?php

namespace App\Domain\Media\Enums;

/**
 * The kinds of file the media library accepts (PLAN.md §11: no executable
 * types). Each kind lists the content types it allows, as detected from the
 * file's bytes, and the extension the stored file gets.
 */
enum MediaKind: string
{
    case Image = 'image';
    case Vector = 'vector';
    case Document = 'document';
    case Video = 'video';
    case Audio = 'audio';

    /**
     * Detected content type => stored extension.
     *
     * @return array<string, string>
     */
    public function mimeTypes(): array
    {
        return match ($this) {
            self::Image => [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
            ],
            self::Vector => [
                'image/svg+xml' => 'svg',
            ],
            self::Document => [
                'application/pdf' => 'pdf',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
                'application/vnd.oasis.opendocument.text' => 'odt',
                'application/vnd.oasis.opendocument.spreadsheet' => 'ods',
                'application/vnd.oasis.opendocument.presentation' => 'odp',
                'text/plain' => 'txt',
                'text/csv' => 'csv',
            ],
            self::Video => [
                'video/mp4' => 'mp4',
                'video/webm' => 'webm',
            ],
            self::Audio => [
                'audio/mpeg' => 'mp3',
                'audio/mp4' => 'm4a',
                'audio/x-m4a' => 'm4a',
                'audio/ogg' => 'ogg',
            ],
        };
    }

    public static function fromMime(?string $mime): ?self
    {
        if ($mime === null) {
            return null;
        }

        $mime = strtolower(trim(explode(';', $mime)[0]));

        foreach (self::cases() as $kind) {
            if (array_key_exists($mime, $kind->mimeTypes())) {
                return $kind;
            }
        }

        return null;
    }

    public function extensionFor(string $mime): string
    {
        return $this->mimeTypes()[strtolower($mime)];
    }

    public function maxBytes(): int
    {
        return (int) config("portal.media.max_mb.{$this->value}", 10) * 1024 * 1024;
    }

    /** Raster images get re-encoded, resized and cropped copies. */
    public function isRaster(): bool
    {
        return $this === self::Image;
    }

    /** Shown in a browser tab rather than downloaded. */
    public function opensInline(string $mime): bool
    {
        return match ($this) {
            self::Image, self::Vector, self::Video, self::Audio => true,
            self::Document => $mime === 'application/pdf',
        };
    }

    public function label(): string
    {
        return __("admin.media.kind.{$this->value}");
    }

    /**
     * Every accepted content type, for the upload field.
     *
     * @return list<string>
     */
    public static function acceptedMimeTypes(): array
    {
        $all = [];
        foreach (self::cases() as $kind) {
            $all = [...$all, ...array_keys($kind->mimeTypes())];
        }

        return array_values(array_unique($all));
    }

    /** The largest upload of any kind, in kilobytes (for Livewire's limit). */
    public static function largestUploadKb(): int
    {
        return (int) max(array_map(fn (self $kind): int => $kind->maxBytes(), self::cases())) / 1024;
    }
}
