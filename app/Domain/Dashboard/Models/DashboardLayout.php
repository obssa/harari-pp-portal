<?php

namespace App\Domain\Dashboard\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person's own dashboard: the widgets they chose, in order, with widths.
 *
 * @property int $id
 * @property int $user_id
 * @property list<array{key: string, size: int}> $widgets
 */
class DashboardLayout extends Model
{
    protected $fillable = ['user_id', 'widgets'];

    protected function casts(): array
    {
        return ['widgets' => 'array'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
