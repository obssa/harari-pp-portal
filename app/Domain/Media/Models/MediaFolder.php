<?php

namespace App\Domain\Media\Models;

use App\Domain\Sites\Models\Site;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A folder in the media library, nested to any depth, per site.
 *
 * @property int $id
 * @property int $site_id
 * @property ?int $parent_id
 * @property string $name
 * @property-read ?MediaFolder $parent
 */
class MediaFolder extends Model
{
    use LogsActivity;

    protected $fillable = ['name', 'parent_id'];

    protected static function booted(): void
    {
        static::creating(function (MediaFolder $folder): void {
            $folder->site_id ??= Site::current()->id;
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('media')->logOnly(['name', 'parent_id'])->logOnlyDirty();
    }

    /**
     * @return BelongsTo<MediaFolder, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<MediaFolder, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Media, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'folder_id');
    }

    /**
     * Ids of this folder and every folder inside it.
     *
     * @return list<int>
     */
    public function selfAndDescendantIds(): array
    {
        $ids = [$this->id];
        $level = [$this->id];

        while ($level !== []) {
            $level = self::query()->whereIn('parent_id', $level)->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $ids = [...$ids, ...$level];
        }

        return $ids;
    }

    /**
     * "Parent / Child / This" for lists and selects.
     */
    public function path(): string
    {
        $names = [$this->name];
        $seen = [$this->id];
        $parentId = $this->parent_id;

        while ($parentId !== null && ! in_array($parentId, $seen, true)) {
            $parent = self::query()->find($parentId, ['id', 'name', 'parent_id']);
            if ($parent === null) {
                break;
            }
            array_unshift($names, $parent->name);
            $seen[] = $parent->id;
            $parentId = $parent->parent_id;
        }

        return implode(' / ', $names);
    }

    /**
     * Folder paths of the current site, for selects.
     *
     * @param  list<int>  $except
     * @return array<int, string>
     */
    public static function options(array $except = []): array
    {
        $folders = self::query()->where('site_id', Site::current()->id)->get(['id', 'name', 'parent_id'])->keyBy('id');

        $paths = [];
        foreach ($folders as $folder) {
            if (in_array($folder->id, $except, true)) {
                continue;
            }

            $names = [$folder->name];
            $seen = [$folder->id];
            $parent = $folders->get((int) $folder->parent_id);
            while ($parent !== null && ! in_array($parent->id, $seen, true)) {
                array_unshift($names, $parent->name);
                $seen[] = $parent->id;
                $parent = $folders->get((int) $parent->parent_id);
            }
            $paths[$folder->id] = implode(' / ', $names);
        }

        asort($paths, SORT_NATURAL | SORT_FLAG_CASE);

        return $paths;
    }
}
