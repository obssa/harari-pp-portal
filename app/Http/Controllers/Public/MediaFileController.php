<?php

namespace App\Http\Controllers\Public;

use App\Domain\Documents\Models\Document;
use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Models\Media;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * Serves a library file's original from the private disk (ADR-0009). The
 * type comes from the database (decided at upload), the browser is told not
 * to guess it, and SVGs run in a sandbox with no scripts.
 */
final class MediaFileController extends Controller
{
    public function __invoke(string $uuid, string $name): BinaryFileResponse
    {
        $media = Media::query()
            ->where('uuid', $uuid)
            ->where('collection_name', Media::LIBRARY)
            ->where('disk', 'media')
            ->first();

        abort_if($media === null || $media->file_name !== $name, 404);
        // ADR-0049: files of members-only documents are only served in the member portal.
        abort_if(Document::isMembersOnlyFile((int) $media->getKey()), 404);

        $path = $media->getPathRelativeToRoot();
        abort_unless(Storage::disk('media')->exists($path), 404);

        $mime = (string) $media->mime_type;
        $inline = $media->kind->opensInline($mime);

        $headers = [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                $inline ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT,
                $media->file_name,
            ),
            'Cross-Origin-Resource-Policy' => 'same-site',
        ];

        if ($mime === 'application/pdf') {
            // The document page shows PDFs in a frame (ADR-0026); no other site may.
            $headers['Content-Security-Policy'] = "frame-ancestors 'self'";
            $headers['X-Frame-Options'] = 'SAMEORIGIN';
        }

        if ($media->kind === MediaKind::Vector || str_starts_with($mime, 'text/')) {
            $headers['Content-Security-Policy'] = "default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox";
        }

        return response()->file(Storage::disk('media')->path($path), $headers);
    }
}
