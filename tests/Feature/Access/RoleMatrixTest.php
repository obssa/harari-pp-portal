<?php

use App\Domain\Access\Actions\SyncPermissions;
use App\Domain\Access\Enums\SystemRole;
use App\Domain\Access\Models\Permission;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Support\PermissionRegistry;

it('names every permission module.action (or translations.<locale>.edit) without duplicates', function () {
    $all = PermissionRegistry::all();

    expect($all)->toEqual(array_values(array_unique($all)));
    foreach ($all as $permission) {
        expect($permission)->toMatch('/^[a-z_]+\.([a-z_]+|(en|am|om|har)\.edit)$/');
    }
});

it('creates every permission and all thirteen system roles', function () {
    expect(Permission::count())->toBe(count(PermissionRegistry::all()))
        ->and(Role::query()->pluck('name')->sort()->values()->all())
        ->toEqual(collect(SystemRole::cases())->map->value->sort()->values()->all());
});

it('only grants permissions that exist in the registry', function (SystemRole $role) {
    expect(array_diff($role->defaultPermissions(), PermissionRegistry::all()))->toBe([]);
})->with(SystemRole::cases());

it('is idempotent and keeps changes a Super Admin made to a role', function () {
    $editor = Role::findByName('editor');
    $editor->revokePermissionTo('media.upload');

    $result = app(SyncPermissions::class)();

    expect($result['created'])->toBe([])
        ->and($result['roles_created'])->toBe([])
        ->and(Permission::count())->toBe(count(PermissionRegistry::all()))
        ->and($editor->fresh()->hasPermissionTo('media.upload'))->toBeFalse();
});

it('always gives the Super Admin role every permission', function () {
    Role::findByName('super_admin')->revokePermissionTo('users.delete');

    app(SyncPermissions::class)();

    expect(Role::findByName('super_admin')->permissions()->count())->toBe(count(PermissionRegistry::all()));
});

it('removes permissions that are no longer in the registry', function () {
    Permission::query()->create(['name' => 'obsolete.thing', 'guard_name' => 'web']);

    $result = app(SyncPermissions::class)();

    expect($result['deleted'])->toBe(['obsolete.thing'])
        ->and(Permission::query()->where('name', 'obsolete.thing')->exists())->toBeFalse();
});

it('follows PLAN.md §8 for what each role may and may not do', function (SystemRole $role, array $may, array $mayNot) {
    $grants = Role::findByName($role->value)->permissions->pluck('name')->all();

    foreach ($may as $permission) {
        expect($grants)->toContain($permission);
    }
    foreach ($mayNot as $permission) {
        expect($grants)->not->toContain($permission);
    }
})->with([
    'admin' => [SystemRole::Admin, ['users.create', 'roles.update', 'posts.publish', 'members.export'],
        ['settings.manage', 'integrations.manage', 'audit.purge', 'backups.manage', 'users.impersonate']],
    'publisher' => [SystemRole::Publisher, ['posts.publish', 'posts.feature', 'menus.manage', 'homepage.manage'],
        ['users.view', 'settings.view', 'roles.view']],
    'editor' => [SystemRole::Editor, ['posts.create', 'posts.update', 'posts.review', 'media.upload'],
        ['posts.publish', 'posts.delete', 'users.view']],
    'author' => [SystemRole::Author, ['posts.create', 'posts.update_own', 'media.upload'],
        ['posts.update', 'posts.publish', 'posts.delete']],
    'translator' => [SystemRole::Translator, ['translations.har.edit', 'translations.import', 'translations.export'],
        ['posts.update', 'posts.publish', 'pages.publish']],
    'media manager' => [SystemRole::MediaManager, ['media.delete', 'galleries.manage', 'social.manage'],
        ['posts.view', 'members.view']],
    'communications officer' => [SystemRole::CommunicationsOfficer, ['press_kit.manage', 'media_contacts.manage', 'campaigns.send'],
        ['members.view', 'settings.view']],
    'membership officer' => [SystemRole::MembershipOfficer, ['applications.review', 'members.export', 'volunteers.manage'],
        ['posts.view', 'settings.view']],
    'support agent' => [SystemRole::SupportAgent, ['inbox.view', 'inbox.reply'], ['posts.view', 'members.view']],
    'office head' => [SystemRole::OfficeHead, ['inbox.view', 'posts.view'], ['posts.create', 'inbox.reply']],
    'member' => [SystemRole::Member, ['portal.access'], ['admin.access']],
]);

it('keeps the analyst strictly read-only', function () {
    $writes = ['create', 'update', 'update_own', 'delete', 'manage', 'publish', 'send', 'import', 'upload', 'reply', 'assign', 'review'];
    foreach (Role::findByName('analyst')->permissions->pluck('name') as $permission) {
        expect(explode('.', $permission)[1])->not->toBeIn($writes);
    }
});

it('reserves super-admin-only permissions for the Super Admin role', function () {
    foreach (SystemRole::SUPER_ADMIN_ONLY as $permission) {
        $holders = Role::query()->whereHas('permissions', fn ($q) => $q->where('name', $permission))->pluck('name')->all();
        expect($holders)->toBe(['super_admin']);
    }
});

it('lets every staff role into the admin panel but not members', function (SystemRole $role) {
    expect(Role::findByName($role->value)->hasPermissionTo('admin.access'))->toBe($role !== SystemRole::Member);
})->with(SystemRole::cases());

it('requires two-factor login exactly for Super Admin, Admin and Publisher', function () {
    $required = array_values(array_filter(SystemRole::cases(), fn (SystemRole $r) => $r->requiresMfa()));

    expect($required)->toBe([SystemRole::SuperAdmin, SystemRole::Admin, SystemRole::Publisher]);
});

it('gives every permission, module and role a label in every shipped language', function () {
    foreach (['en', 'am', 'om'] as $locale) {
        app()->setLocale($locale);
        foreach (PermissionRegistry::modules() as $module => $permissions) {
            expect(PermissionRegistry::moduleLabel($module))->not->toStartWith('access.');
            foreach ($permissions as $permission) {
                expect(PermissionRegistry::label($permission))->not->toStartWith('access.');
            }
        }
        foreach (SystemRole::cases() as $role) {
            expect($role->label())->not->toStartWith('access.');
        }
    }
});
