<?php

namespace App\Domain\Media\Actions;

use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Models\Media;
use Spatie\MediaLibrary\Conversions\FileManipulator;

/**
 * Remakes a photo's cropped copies, e.g. after its focal point moved. The
 * copies keep their names; versioned URLs (media-library.version_urls)
 * make browsers and Cloudflare fetch the new files.
 */
final class RegenerateConversions
{
    public function __construct(private readonly FileManipulator $manipulator) {}

    public function handle(Media $media): void
    {
        if ($media->kind !== MediaKind::Image || $media->collection_name !== Media::LIBRARY) {
            return;
        }

        $this->manipulator->createDerivedFiles($media->refresh());
    }
}
