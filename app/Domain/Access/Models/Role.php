<?php

namespace App\Domain\Access\Models;

use App\Domain\Access\Enums\SystemRole;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use LogsActivity;

    public function systemRole(): ?SystemRole
    {
        return SystemRole::tryFrom($this->name);
    }

    public function isSystem(): bool
    {
        return $this->systemRole() !== null;
    }

    public function isSuperAdmin(): bool
    {
        return $this->name === SystemRole::SuperAdmin->value;
    }

    public function label(): string
    {
        return $this->systemRole()?->label() ?? $this->name;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('roles')
            ->logOnly(['name', 'guard_name'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
