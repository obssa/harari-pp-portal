<?php

namespace App\Domain\Content\Actions;

use App\Domain\Content\Contracts\TranslatableModel;
use App\Domain\Content\Models\Revision;
use App\Domain\Content\Models\Translation;
use InvalidArgumentException;

/**
 * Puts the text of an earlier version back into its language (PLAN.md §7).
 * Status, publish date and slug stay as they are, so a live page keeps its
 * address and stays live; the restore itself becomes a new version.
 */
final class RestoreRevision
{
    /** Fields a restore writes back. */
    public const FIELDS = ['title', 'kicker', 'excerpt', 'body', 'seo_title', 'seo_description'];

    public function __construct(private readonly RecordRevision $revisions) {}

    public function handle(Revision $revision): Translation
    {
        $record = $revision->revisionable;
        if (! $record instanceof TranslatableModel || $revision->locale === null) {
            throw new InvalidArgumentException('This version cannot be restored.');
        }

        $values = array_intersect_key($revision->data, array_flip(self::FIELDS));
        $translation = $record->putTranslation($revision->locale, $values);

        $this->revisions->handle($record, $translation, __('content.revision.restored', [
            'date' => $revision->created_at->toDateTimeString(),
        ]));

        return $translation;
    }
}
