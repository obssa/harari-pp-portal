<?php

namespace App\Domain\Access\Listeners;

use App\Domain\Access\Actions\InvalidateUserSessions;
use App\Domain\Access\Models\Permission;
use App\Domain\Access\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Collection;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;

/**
 * Audits role and permission grants and signs users out after their roles
 * change, so a downgraded account cannot keep working on an old session.
 */
final class RecordAccessChanges
{
    public function __construct(private readonly InvalidateUserSessions $invalidate) {}

    public function roleAttached(RoleAttachedEvent $event): void
    {
        $this->roleChanged($event->model, 'roles_attached', $event->rolesOrIds);
    }

    public function roleDetached(RoleDetachedEvent $event): void
    {
        $this->roleChanged($event->model, 'roles_detached', $event->rolesOrIds);
    }

    public function permissionAttached(PermissionAttachedEvent $event): void
    {
        $this->permissionChanged($event->model, 'permissions_attached', $event->permissionsOrIds);
    }

    public function permissionDetached(PermissionDetachedEvent $event): void
    {
        $this->permissionChanged($event->model, 'permissions_detached', $event->permissionsOrIds);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            RoleAttachedEvent::class => 'roleAttached',
            RoleDetachedEvent::class => 'roleDetached',
            PermissionAttachedEvent::class => 'permissionAttached',
            PermissionDetachedEvent::class => 'permissionDetached',
        ];
    }

    private function roleChanged(Model $model, string $event, mixed $roles): void
    {
        if (! $model instanceof User) {
            return;
        }

        activity('users')
            ->performedOn($model)
            ->event($event)
            ->withProperties(['roles' => self::names($roles, Role::class)])
            ->log($event);

        ($this->invalidate)($model);
    }

    private function permissionChanged(Model $model, string $event, mixed $permissions): void
    {
        activity($model instanceof Role ? 'roles' : 'users')
            ->performedOn($model)
            ->event($event)
            ->withProperties(['permissions' => self::names($permissions, Permission::class)])
            ->log($event);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return list<string>
     */
    private static function names(mixed $items, string $modelClass): array
    {
        $items = $items instanceof Collection ? $items->all() : (is_array($items) ? $items : [$items]);
        $names = [];
        $ids = [];
        foreach ($items as $item) {
            if ($item instanceof Model) {
                $names[] = (string) $item->getAttribute('name');
            } elseif (is_int($item) || ctype_digit((string) $item)) {
                $ids[] = (int) $item;
            } elseif (is_string($item)) {
                $names[] = $item;
            }
        }
        if ($ids !== []) {
            $names = [...$names, ...$modelClass::query()->whereKey($ids)->pluck('name')->all()];
        }
        sort($names);

        return array_values(array_unique($names));
    }
}
