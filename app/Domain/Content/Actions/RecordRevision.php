<?php

namespace App\Domain\Content\Actions;

use App\Domain\Content\Models\Revision;
use App\Domain\Content\Models\Translation;
use Illuminate\Database\Eloquent\Model;

/**
 * Saves a version of one language of a record when its text changed since
 * the last version (PLAN.md §7: revision history with diff and restore).
 */
final class RecordRevision
{
    /** Fields kept in a version; anything else is structural or derived. */
    public const FIELDS = ['title', 'kicker', 'excerpt', 'body', 'seo_title', 'seo_description', 'slug', 'status', 'published_at'];

    public function handle(Model $record, Translation $translation, ?string $note = null): ?Revision
    {
        $data = self::snapshot($translation);

        /** @var Revision|null $last */
        $last = Revision::query()
            ->where('revisionable_type', $record->getMorphClass())
            ->where('revisionable_id', (string) $record->getKey())
            ->where('locale', $translation->locale)
            ->latest('id')
            ->first();

        if ($last !== null && $last->data == $data) {
            return null;
        }

        $id = auth()->id();

        $revision = new Revision([
            'locale' => $translation->locale,
            'data' => $data,
            'note' => $note,
            'created_by' => is_int($id) ? $id : null,
        ]);
        $revision->revisionable()->associate($record);
        $revision->save();

        return $revision;
    }

    /**
     * @return array<string, mixed>
     */
    public static function snapshot(Translation $translation): array
    {
        $data = [];
        foreach (self::FIELDS as $field) {
            $value = $translation->getAttribute($field);
            $data[$field] = $value instanceof \BackedEnum ? $value->value
                : ($value instanceof \DateTimeInterface ? $value->format(DATE_ATOM) : $value);
        }

        return $data;
    }
}
