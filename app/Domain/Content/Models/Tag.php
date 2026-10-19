<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\Concerns\BelongsToSite;
use App\Domain\Content\Concerns\HasTranslations;
use App\Domain\Content\Concerns\TracksEditors;
use App\Domain\Content\Contracts\TranslatableModel;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A keyword attached to content, named per language.
 *
 * @property string $id
 */
class Tag extends Model implements TranslatableModel
{
    use BelongsToSite;

    /** @use HasTranslations<TagTranslation> */
    use HasTranslations;

    use HasUlids;
    use LogsActivity;
    use SoftDeletes;
    use TracksEditors;

    protected $fillable = [];

    public static function translationModel(): string
    {
        return TagTranslation::class;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('content')->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function name(?string $locale = null): string
    {
        return (string) $this->translation($locale)?->name;
    }
}
