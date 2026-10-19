<?php

namespace App\Domain\Media\Support;

use App\Domain\Media\Enums\MediaKind;

/**
 * What an upload really is, as found by UploadInspector.
 */
final readonly class InspectedFile
{
    public function __construct(
        public MediaKind $kind,
        public string $mime,
        public string $extension,
        public int $size,
        public ?int $width = null,
        public ?int $height = null,
    ) {}
}
