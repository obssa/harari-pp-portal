<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Access\Models\Role;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

it('denies everything to an account without roles', function () {
    $nobody = User::factory()->create();
    $other = User::factory()->create();

    foreach (['viewAny', 'create'] as $ability) {
        expect($nobody->can($ability, User::class))->toBeFalse()
            ->and($nobody->can($ability, Role::class))->toBeFalse()
            ->and($nobody->can($ability, Activity::class))->toBeFalse();
    }
    expect($nobody->can('update', $other))->toBeFalse()
        ->and($nobody->can('admin.access'))->toBeFalse();
});

it('lets an Admin manage ordinary users but not Super Admins', function () {
    $admin = staff(SystemRole::Admin);
    $editor = staff(SystemRole::Editor);
    $root = staff(SystemRole::SuperAdmin);

    expect($admin->can('update', $editor))->toBeTrue()
        ->and($admin->can('delete', $editor))->toBeTrue()
        ->and($admin->can('update', $root))->toBeFalse()
        ->and($admin->can('delete', $root))->toBeFalse()
        ->and($admin->can('resetMfa', $root))->toBeFalse();
});

it('stops anyone deleting their own account or the last Super Admin', function () {
    $root = staff(SystemRole::SuperAdmin);

    expect($root->can('delete', $root))->toBeFalse();

    $second = staff(SystemRole::SuperAdmin);
    expect($root->can('delete', $second))->toBeTrue();

    $second->delete();
    $third = User::factory()->create();
    expect($third->can('delete', $root))->toBeFalse();
});

it('never permanently deletes accounts, even for Super Admins', function () {
    $root = staff(SystemRole::SuperAdmin);
    $editor = staff(SystemRole::Editor);

    expect($root->can('forceDelete', $editor))->toBeFalse()
        ->and($root->can('forceDeleteAny', User::class))->toBeFalse();
});

it('keeps the Super Admin role immutable and system roles undeletable', function () {
    $root = staff(SystemRole::SuperAdmin);
    $custom = Role::query()->create(['name' => 'youth_editor', 'guard_name' => 'web']);

    expect($root->can('update', Role::findByName('super_admin')))->toBeFalse()
        ->and($root->can('update', Role::findByName('editor')))->toBeTrue()
        ->and($root->can('delete', Role::findByName('editor')))->toBeFalse()
        ->and($root->can('delete', $custom))->toBeTrue();

    User::factory()->create()->assignRole($custom);
    expect($root->can('delete', $custom->fresh()))->toBeFalse();
});

it('keeps the audit log read-only for everyone', function () {
    $root = staff(SystemRole::SuperAdmin);
    $entry = Activity::query()->latest('id')->firstOrFail();

    expect($root->can('viewAny', Activity::class))->toBeTrue()
        ->and($root->can('create', Activity::class))->toBeFalse()
        ->and($root->can('update', $entry))->toBeFalse()
        ->and($root->can('delete', $entry))->toBeFalse();
});

it('only lets Super Admins hand out the Super Admin role', function () {
    expect(staff(SystemRole::Admin)->can('assignRole', [User::class, 'super_admin']))->toBeFalse()
        ->and(staff(SystemRole::Admin)->can('assignRole', [User::class, 'editor']))->toBeTrue()
        ->and(staff(SystemRole::SuperAdmin)->can('assignRole', [User::class, 'super_admin']))->toBeTrue()
        ->and(staff(SystemRole::Editor)->can('assignRole', [User::class, 'author']))->toBeFalse();
});

it('refuses every action the panel never offers, even to Super Admins', function () {
    $root = staff(SystemRole::SuperAdmin);
    $editor = staff(SystemRole::Editor);
    $role = Role::findByName('editor');
    $entry = Activity::query()->latest('id')->firstOrFail();

    foreach (['replicate', 'forceDelete'] as $ability) {
        expect($root->can($ability, $editor))->toBeFalse("users.{$ability}")
            ->and($root->can($ability, $role))->toBeFalse("roles.{$ability}")
            ->and($root->can($ability, $entry))->toBeFalse("activity.{$ability}");
    }
    foreach (['reorder', 'forceDeleteAny', 'restoreAny', 'deleteAny'] as $ability) {
        expect($root->can($ability, Role::class))->toBeFalse("roles.{$ability}")
            ->and($root->can($ability, Activity::class))->toBeFalse("activity.{$ability}");
    }
    expect($root->can('reorder', User::class))->toBeFalse()
        ->and($root->can('restore', $role))->toBeFalse()
        ->and($root->can('restore', $entry))->toBeFalse()
        ->and($root->can('view', $entry))->toBeTrue()
        ->and($root->can('view', $role))->toBeTrue();
});

it('lets user managers restore deleted accounts but not other roles', function () {
    $admin = staff(SystemRole::Admin);
    $deleted = staff(SystemRole::Editor);
    $deleted->delete();

    expect($admin->can('restore', $deleted))->toBeTrue()
        ->and($admin->can('restoreAny', User::class))->toBeTrue()
        ->and(staff(SystemRole::Publisher)->can('restore', $deleted))->toBeFalse()
        ->and($admin->can('view', $deleted))->toBeTrue();
});
