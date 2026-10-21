<?php

namespace App\Domain\Documents\Support;

use App\Domain\Documents\Models\Document;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * Hands a signed-in member the file of a members-only document (ADR-0049,
 * ADR-0057): from the private media disk, never cached, and noted in the
 * audit log without saying who.
 */
final class MembersOnlyFile
{
    public static function response(Document $document, string $locale): BinaryFileResponse
    {
        abort_if($document->access !== Document::ACCESS_MEMBERS, 404);

        $media = $document->fileFor($locale)?->file;
        abort_if($media === null || ! Storage::disk('media')->exists($media->getPathRelativeToRoot()), 404);

        activity('membership')->performedOn($document)->causedBy(null)->event('member_download')->log('Members-only document downloaded');

        return response()->file(Storage::disk('media')->path($media->getPathRelativeToRoot()), [
            'Content-Type' => (string) $media->mime_type,
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $media->file_name),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
