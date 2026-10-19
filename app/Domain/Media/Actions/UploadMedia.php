<?php

namespace App\Domain\Media\Actions;

use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Exceptions\RejectedUpload;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Models\MediaFolder;
use App\Domain\Media\Support\InspectedFile;
use App\Domain\Media\Support\SvgSanitizer;
use App\Domain\Media\Support\UploadInspector;
use App\Domain\Sites\Models\Site;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Image\Enums\ImageDriver;
use Spatie\Image\Image;
use Throwable;

/**
 * Adds one uploaded file to the site's media library (PLAN.md §7, §11).
 *
 * 1. UploadInspector decides what the file really is, from its bytes.
 * 2. Photos are re-encoded: turned upright, scaled to the maximum edge, and
 *    stripped of EXIF/GPS and any hidden payload. SVGs are sanitised.
 * 3. The stored name is a slug with the extension of the real type, so a
 *    file called "photo.php.jpg" can never be stored as PHP.
 */
final class UploadMedia
{
    public function __construct(
        private readonly UploadInspector $inspector,
        private readonly SvgSanitizer $svg,
    ) {}

    /**
     * @throws RejectedUpload
     */
    public function handle(string $path, string $originalName, ?User $uploader = null, ?MediaFolder $folder = null): Media
    {
        $file = $this->inspector->inspect($path, $originalName);
        $title = $this->title($originalName);
        $work = $this->workingCopy($file);

        try {
            [$width, $height] = match ($file->kind) {
                MediaKind::Image => $this->reencode($path, $work, $file),
                MediaKind::Vector => $this->sanitiseSvg($path, $work),
                default => $this->copy($path, $work),
            };

            /** @var Media $media */
            $media = Site::current()
                ->addMedia($work)
                ->usingName($title)
                ->usingFileName($this->fileName($title, $file->extension))
                ->withAttributes([
                    'site_id' => Site::current()->id,
                    'folder_id' => $folder?->id,
                    'uploaded_by' => $uploader?->id,
                    'kind' => $file->kind,
                    'width' => $width,
                    'height' => $height,
                    'mime_type' => $file->mime,
                ])
                ->toMediaCollection(Media::LIBRARY);
        } finally {
            if (is_file($work)) {
                @unlink($work);
            }
        }

        return $media;
    }

    private function workingCopy(InspectedFile $file): string
    {
        $directory = storage_path('app/media-work');
        if (! is_dir($directory)) {
            mkdir($directory, 0750, true);
        }

        return $directory.'/'.Str::random(32).'.'.$file->extension;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function reencode(string $source, string $target, InspectedFile $file): array
    {
        try {
            $image = Image::useImageDriver(ImageDriver::Gd)->loadFile($source);

            $maxEdge = (int) config('portal.media.max_edge', 3840);
            if (max($image->getWidth(), $image->getHeight()) > $maxEdge) {
                $image->getWidth() >= $image->getHeight() ? $image->width($maxEdge) : $image->height($maxEdge);
            }

            $image->quality($file->extension === 'png' ? 90 : 88)->save($target);

            return [$image->getWidth(), $image->getHeight()];
        } catch (RejectedUpload $e) {
            throw $e;
        } catch (Throwable) {
            throw RejectedUpload::because('corrupt');
        }
    }

    /**
     * @return array{0: null, 1: null}
     */
    private function sanitiseSvg(string $source, string $target): array
    {
        file_put_contents($target, $this->svg->sanitize((string) file_get_contents($source)));

        return [null, null];
    }

    /**
     * @return array{0: null, 1: null}
     */
    private function copy(string $source, string $target): array
    {
        copy($source, $target);

        return [null, null];
    }

    /** A readable title from the file name ("Mayor visit-2026.jpg" → "Mayor visit 2026"). */
    private function title(string $originalName): string
    {
        $base = pathinfo($originalName, PATHINFO_FILENAME);
        $title = trim((string) preg_replace('/[\s_\-]+/u', ' ', $base));

        return Str::limit($title !== '' ? $title : __('admin.media.untitled'), 120, '');
    }

    private function fileName(string $title, string $extension): string
    {
        $slug = Str::limit(Str::slug($title), 80, '');

        return ($slug !== '' ? $slug : 'file').'.'.$extension;
    }
}
