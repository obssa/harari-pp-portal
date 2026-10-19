<?php

namespace App\Domain\Media\Support;

use Spatie\MediaLibrary\Support\UrlGenerator\DefaultUrlGenerator;

/**
 * Converted images are plain files on the public disk (cached by Cloudflare).
 * Originals stay on the private disk and are served by MediaController with
 * safe headers, so their URL is a route.
 */
final class MediaUrlGenerator extends DefaultUrlGenerator
{
    public function getUrl(): string
    {
        if ($this->conversion !== null || $this->media === null || $this->media->disk !== 'media') {
            return parent::getUrl();
        }

        return route('media.file', [
            'uuid' => $this->media->uuid,
            'name' => $this->media->file_name,
        ]);
    }
}
