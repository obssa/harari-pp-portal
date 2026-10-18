<?php

namespace App\Domain\Access\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Permission names follow `module.action` (see PermissionRegistry).
 */
class Permission extends SpatiePermission {}
