<?php

use App\Domain\Access\Actions\UpdateUserAccess;
use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Enums\SystemRole;
use App\Models\User;

it('applies a role without scopes everywhere', function () {
    $editor = staff(SystemRole::Editor);

    expect($editor->hasPermissionInScope('posts.update', ScopeType::Office, 'any-office'))->toBeTrue()
        ->and($editor->hasPermissionInScope('posts.update', ScopeType::League, 'youth'))->toBeTrue();
});

it('limits a scoped role to its scopes', function () {
    $editor = User::factory()->create();
    $editor->assignScopedRole(SystemRole::Editor, ScopeType::League, 'youth');
    $editor = $editor->fresh();

    expect($editor->hasPermissionInScope('posts.update', ScopeType::League, 'youth'))->toBeTrue()
        ->and($editor->hasPermissionInScope('posts.update', ScopeType::League, 'women'))->toBeFalse()
        ->and($editor->hasPermissionInScope('posts.update', ScopeType::Office, 'branch'))->toBeFalse()
        ->and($editor->hasPermissionInScope('posts.publish', ScopeType::League, 'youth'))->toBeFalse();
});

it('lets a Harari translator edit Harari strings only', function () {
    $translator = User::factory()->create();
    $translator->assignScopedRole(SystemRole::Translator, ScopeType::Language, 'har');
    $translator = $translator->fresh();

    expect($translator->canEditTranslationsFor('har'))->toBeTrue()
        ->and($translator->canEditTranslationsFor('am'))->toBeFalse()
        ->and($translator->canEditTranslationsFor('en'))->toBeFalse();
});

it('combines a scoped role with a global one', function () {
    $user = User::factory()->create();
    $user->assignScopedRole(SystemRole::Editor, ScopeType::League, 'youth');
    $user->assignRole(SystemRole::Publisher->value);
    $user = $user->fresh();

    expect($user->hasPermissionInScope('posts.update', ScopeType::League, 'women'))->toBeTrue();
});

it('gives Super Admins every scope', function () {
    $root = staff(SystemRole::SuperAdmin);

    expect($root->hasPermissionInScope('translations.am.edit', ScopeType::Language, 'am'))->toBeTrue();
});

it('refuses scope types a role does not support', function () {
    User::factory()->create()->assignScopedRole(SystemRole::Translator, ScopeType::Office, 'branch');
})->throws(InvalidArgumentException::class);

it('saves roles and scopes for a user and drops scopes of roles that were removed', function () {
    $admin = staff(SystemRole::Admin);
    $user = User::factory()->create();

    app(UpdateUserAccess::class)($admin, $user, ['editor', 'translator'], [
        ['role' => 'editor', 'scope_type' => 'league', 'scope_value' => 'youth'],
        ['role' => 'translator', 'scope_type' => 'language', 'scope_value' => 'har'],
        ['role' => 'translator', 'scope_type' => 'office', 'scope_value' => 'not-allowed'],
        ['role' => 'author', 'scope_type' => 'office', 'scope_value' => 'role-not-held'],
    ]);

    $user = $user->fresh();
    expect($user->getRoleNames()->sort()->values()->all())->toBe(['editor', 'translator'])
        ->and($user->roleScopes)->toHaveCount(2);

    app(UpdateUserAccess::class)($admin, $user, ['editor'], [
        ['role' => 'editor', 'scope_type' => 'league', 'scope_value' => 'youth'],
        ['role' => 'translator', 'scope_type' => 'language', 'scope_value' => 'har'],
    ]);

    $user = $user->fresh();
    expect($user->getRoleNames()->all())->toBe(['editor'])
        ->and($user->roleScopes->pluck('scope_value')->all())->toBe(['youth']);
});

it('never lets an Admin grant or remove the Super Admin role', function () {
    $admin = staff(SystemRole::Admin);
    $user = User::factory()->create();

    app(UpdateUserAccess::class)($admin, $user, ['super_admin', 'editor'], []);
    expect($user->fresh()->getRoleNames()->all())->toBe(['editor']);

    $root = staff(SystemRole::SuperAdmin);
    $other = staff(SystemRole::SuperAdmin);
    app(UpdateUserAccess::class)($admin, $other, ['editor'], []);
    expect($other->fresh()->hasRole('super_admin'))->toBeTrue();

    app(UpdateUserAccess::class)($root, $user, ['super_admin'], []);
    expect($user->fresh()->hasRole('super_admin'))->toBeTrue();
});
