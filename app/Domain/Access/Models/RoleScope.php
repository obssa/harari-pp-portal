<?php

namespace App\Domain\Access\Models;

use App\Domain\Access\Actions\InvalidateUserSessions;
use App\Domain\Access\Enums\ScopeType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Restricts one of a user's roles to an office, league or language. A role
 * with no scope rows for that user applies everywhere (PLAN.md §8).
 *
 * @property int $id
 * @property int $user_id
 * @property int $role_id
 * @property ScopeType $scope_type
 * @property string $scope_value
 * @property-read User $user
 * @property-read Role $role
 */
class RoleScope extends Model
{
    protected $fillable = ['user_id', 'role_id', 'scope_type', 'scope_value'];

    protected static function booted(): void
    {
        // A changed scope changes what the user may touch: audit it and sign them out.
        static::created(fn (RoleScope $scope) => $scope->recordChange('scope_added'));
        static::deleted(fn (RoleScope $scope) => $scope->recordChange('scope_removed'));
    }

    protected function casts(): array
    {
        return ['scope_type' => ScopeType::class];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    private function recordChange(string $event): void
    {
        activity('users')
            ->performedOn($this->user)
            ->event($event)
            ->withProperties([
                'role' => $this->role->name,
                'scope_type' => $this->scope_type->value,
                'scope_value' => $this->scope_value,
            ])
            ->log($event);

        app(InvalidateUserSessions::class)($this->user);
    }
}
