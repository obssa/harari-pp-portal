<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\Support\Slugger;
use Illuminate\Database\Eloquent\Model;

/**
 * One language of a translatable record (ADR-0011). The slug is made from
 * the title (or name) when left empty, unique within its language, and only
 * changes when an editor changes it.
 *
 * @property int $id
 * @property string $locale
 * @property ?string $slug
 */
abstract class Translation extends Model
{
    protected $guarded = ['id'];

    /** Column the slug is made from. */
    protected string $sluggable = 'title';

    protected static function booted(): void
    {
        static::saving(function (Translation $translation): void {
            $source = trim((string) $translation->getAttribute($translation->sluggable));
            $typed = trim((string) $translation->slug);

            // An existing slug never changes with the title: links must keep working.
            if ($typed !== '' && ! $translation->isDirty('slug')) {
                return;
            }

            $translation->slug = app(Slugger::class)->unique(
                $typed !== '' ? $typed : $source,
                $translation->getTable(),
                $translation->locale,
                $translation->getKey(),
            );
        });
    }
}
