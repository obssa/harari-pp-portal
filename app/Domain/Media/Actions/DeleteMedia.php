<?php

namespace App\Domain\Media\Actions;

use App\Domain\Media\Exceptions\MediaInUse;
use App\Domain\Media\Models\Media;

/**
 * Deletes a library file and every copy of it, unless something still uses it.
 */
final class DeleteMedia
{
    /**
     * @throws MediaInUse
     */
    public function handle(Media $media): void
    {
        if ($media->isUsed()) {
            throw new MediaInUse($media);
        }

        $media->delete();
    }
}
