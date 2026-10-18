<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Access\Models\Role;
use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

beforeEach(fn () => $this->actingAs(staff(SystemRole::SuperAdmin)));

it('lists all roles with their labels', function () {
    livewire(ListRoles::class)
        ->assertCanSeeTableRecords(Role::all())
        ->assertSee(SystemRole::MembershipOfficer->label());
});

it('shows the role × permission matrix with current grants', function () {
    $editor = Role::findByName('editor');

    livewire(EditRole::class, ['record' => $editor->getRouteKey()])
        ->assertFormSet([
            'permissions_posts' => ['posts.view', 'posts.create', 'posts.update', 'posts.translate', 'posts.review'],
        ]);
});

it('changes grants through the matrix and audits the change', function () {
    $editor = Role::findByName('editor');

    livewire(EditRole::class, ['record' => $editor->getRouteKey()])
        ->fillForm(['permissions_posts' => ['posts.view', 'posts.create', 'posts.update', 'posts.publish']])
        ->call('save')
        ->assertHasNoFormErrors();

    $editor = $editor->fresh();
    expect($editor->hasPermissionTo('posts.publish'))->toBeTrue()
        ->and($editor->hasPermissionTo('posts.review'))->toBeFalse()
        ->and($editor->hasPermissionTo('media.upload'))->toBeTrue()
        ->and(Activity::query()->where('log_name', 'roles')->where('subject_id', $editor->id)
            ->where('event', 'permissions_attached')->exists())->toBeTrue();
});

it('creates a custom role with a validated key', function () {
    livewire(CreateRole::class)
        ->fillForm(['name' => 'youth_editor', 'permissions_posts' => ['posts.view', 'posts.update']])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Role::findByName('youth_editor')->permissions->pluck('name')->sort()->values()->all())
        ->toBe(['posts.update', 'posts.view']);

    livewire(CreateRole::class)
        ->fillForm(['name' => 'Bad Key!'])
        ->call('create')
        ->assertHasFormErrors(['name' => 'regex']);

    livewire(CreateRole::class)
        ->fillForm(['name' => 'editor'])
        ->call('create')
        ->assertHasFormErrors(['name']);
});

it('refuses to open the Super Admin role for editing', function () {
    $this->get('/admin/roles/'.Role::findByName('super_admin')->getRouteKey().'/edit')->assertForbidden();
});

it('keeps system role keys fixed', function () {
    $editor = Role::findByName('editor');

    livewire(EditRole::class, ['record' => $editor->getRouteKey()])
        ->fillForm(['name' => 'renamed'])
        ->call('save');

    expect($editor->fresh()->name)->toBe('editor');
});

it('lets only roles.* holders in', function () {
    $this->actingAs(staff(SystemRole::Publisher))->get('/admin/roles')->assertForbidden();
});
