<?php

namespace App\Domain\Access\Concerns;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Enums\SystemRole;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\RoleScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Role scopes (PLAN.md §8): a role assigned without scopes applies
 * everywhere; a role with scopes applies only to those offices, leagues or
 * languages. Permission checks that concern a slice of content must use
 * hasPermissionInScope(); plain can() answers "anywhere at all".
 *
 * @mixin User
 */
trait HasScopedRoles
{
    /** @return HasMany<RoleScope, $this> */
    public function roleScopes(): HasMany
    {
        return $this->hasMany(RoleScope::class);
    }

    /**
     * Gives the role (if missing) and limits it to one more scope.
     */
    public function assignScopedRole(SystemRole|Role|string $role, ScopeType $type, string $value): RoleScope
    {
        $role = $this->resolveRole($role);
        $system = $role->systemRole();

        if ($system !== null && ! in_array($type, $system->allowedScopes(), true)) {
            throw new \InvalidArgumentException("Role {$role->name} cannot be scoped by {$type->value}.");
        }

        if (! $this->hasRole($role)) {
            $this->assignRole($role);
        }

        return $this->roleScopes()->firstOrCreate([
            'role_id' => $role->getKey(),
            'scope_type' => $type->value,
            'scope_value' => $value,
        ]);
    }

    /**
     * @return Collection<int, RoleScope>
     */
    public function scopesFor(SystemRole|Role|string $role): Collection
    {
        $role = $this->resolveRole($role);
        $this->loadMissing('roleScopes');

        return $this->roleScopes->where('role_id', $role->getKey())->values();
    }

    public function hasPermissionInScope(string $permission, ScopeType $type, string $value): bool
    {
        $this->loadMissing(['roles', 'roleScopes']);

        if ($this->hasDirectPermission($permission) || $this->isSuperAdmin()) {
            return true;
        }

        foreach ($this->roles as $role) {
            if (! $role instanceof Role || ! $role->hasPermissionTo($permission)) {
                continue;
            }

            $scopes = $this->roleScopes->where('role_id', $role->getKey());
            if ($scopes->isEmpty()) {
                return true;
            }
            if ($scopes->contains(fn (RoleScope $s): bool => $s->scope_type === $type && $s->scope_value === $value)) {
                return true;
            }
        }

        return false;
    }

    public function canEditTranslationsFor(string $locale): bool
    {
        return $this->hasPermissionInScope("translations.{$locale}.edit", ScopeType::Language, $locale);
    }

    public function isSuperAdmin(): bool
    {
        $this->loadMissing('roles');

        return $this->hasRole(SystemRole::SuperAdmin->value);
    }

    public function requiresMfa(): bool
    {
        $this->loadMissing('roles');

        return $this->roles->contains(fn (mixed $role): bool => $role instanceof Role && (bool) $role->systemRole()?->requiresMfa());
    }

    private function resolveRole(SystemRole|Role|string $role): Role
    {
        if ($role instanceof Role) {
            return $role;
        }

        $found = Role::findByName($role instanceof SystemRole ? $role->value : $role, 'web');
        if (! $found instanceof Role) {
            throw new \LogicException('Unexpected role model; check config/permission.php.');
        }

        return $found;
    }
}
