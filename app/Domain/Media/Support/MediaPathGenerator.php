<?php

namespace App\Domain\Media\Support;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Files live under their random UUID, never their sequential id, so stored
 * paths cannot be enumerated: library/{uuid}/ for the original (private
 * disk) and library/{uuid}/conversions/ for public copies.
 */
final class MediaPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return 'library/'.$this->folder($media).'/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getPath($media).'conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getPath($media).'responsive/';
    }

    private function folder(Media $media): string
    {
        return (string) ($media->uuid ?? $media->getKey());
    }
}
