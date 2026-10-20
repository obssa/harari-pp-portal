<?php

namespace App\Domain\Translation\Models;

use App\Domain\Content\Concerns\TracksEditors;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One approved term in every language (ADR-0034), e.g. the party's name or
 * "Medemer", with notes on how to use it.
 *
 * @property int $id
 * @property array<string, string> $terms
 * @property ?string $notes
 */
class GlossaryTerm extends Model
{
    use LogsActivity;
    use TracksEditors;

    protected $fillable = ['terms', 'notes'];

    protected function casts(): array
    {
        return ['terms' => 'array'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('translations')
            ->logOnly(['terms', 'notes'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function term(string $locale): ?string
    {
        $term = trim((string) ($this->terms[$locale] ?? ''));

        return $term !== '' ? $term : null;
    }
}
