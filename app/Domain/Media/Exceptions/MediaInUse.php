<?php

namespace App\Domain\Media\Exceptions;

use App\Domain\Media\Models\Media;
use RuntimeException;

final class MediaInUse extends RuntimeException
{
    public function __construct(public readonly Media $media)
    {
        parent::__construct("Media {$media->id} is still in use.");
    }

    public function userMessage(): string
    {
        return __('admin.media.in_use', ['name' => $this->media->name, 'count' => $this->media->usages()->count()]);
    }
}
