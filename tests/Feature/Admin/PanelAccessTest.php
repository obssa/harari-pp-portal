<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Access\Enums\UserStatus;
use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Activitylog\Models\Activity;

it('sends guests to the admin sign-in page', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
    $this->get('/admin/login')->assertOk();
});

it('keeps public members and role-less accounts out of the admin panel', function (?SystemRole $role) {
    $user = $role ? staff($role) : User::factory()->create();

    $this->actingAs($user)->get('/admin')->assertForbidden();
})->with([
    'member' => [SystemRole::Member],
    'no role' => [null],
]);

it('lets every staff role reach the dashboard', function (SystemRole $role) {
    $this->actingAs(staff($role))->get('/admin')->assertOk();
})->with(array_values(array_filter(SystemRole::cases(), fn (SystemRole $r) => $r !== SystemRole::Member)));

it('blocks suspended and deleted accounts even with an open session', function () {
    $suspended = staff(SystemRole::Editor, attributes: ['status' => UserStatus::Suspended]);
    $this->actingAs($suspended)->get('/admin')->assertForbidden();

    $deleted = staff(SystemRole::Editor);
    $deleted->delete();
    $this->actingAs($deleted)->get('/admin')->assertForbidden();
});

it('makes Super Admins, Admins and Publishers set up two-factor login first', function (SystemRole $role) {
    $this->actingAs(staff($role, mfa: false))
        ->get('/admin')
        ->assertRedirect(Filament::getPanel('admin')->getSetUpRequiredMultiFactorAuthenticationUrl());
})->with([SystemRole::SuperAdmin, SystemRole::Admin, SystemRole::Publisher]);

it('lets other staff in without two-factor login', function (SystemRole $role) {
    $this->actingAs(staff($role, mfa: false))->get('/admin')->assertOk();
})->with([SystemRole::Editor, SystemRole::Author, SystemRole::Translator, SystemRole::Analyst]);

it('lets privileged users in once two-factor login is on', function () {
    $this->actingAs(staff(SystemRole::Admin, mfa: true))->get('/admin')->assertOk();
});

it('shows each screen only to roles allowed to see it', function (SystemRole $role, string $path, int $status) {
    $this->actingAs(staff($role))->get($path)->assertStatus($status);
})->with([
    'admin sees users' => [SystemRole::Admin, '/admin/users', 200],
    'admin sees roles' => [SystemRole::Admin, '/admin/roles', 200],
    'admin sees audit log' => [SystemRole::Admin, '/admin/audit-log', 200],
    'editor blocked from users' => [SystemRole::Editor, '/admin/users', 403],
    'editor blocked from roles' => [SystemRole::Editor, '/admin/roles', 403],
    'publisher blocked from audit log' => [SystemRole::Publisher, '/admin/audit-log', 403],
    'analyst blocked from users' => [SystemRole::Analyst, '/admin/users', 403],
    'super admin sees users' => [SystemRole::SuperAdmin, '/admin/users', 200],
]);

it('records refused requests in the audit log', function () {
    $editor = staff(SystemRole::Editor);

    $this->actingAs($editor)->get('/admin/users')->assertForbidden();

    $entry = Activity::query()->where('log_name', 'security')->latest('id')->firstOrFail();
    expect($entry->event)->toBe('access_denied')
        ->and($entry->causer?->is($editor))->toBeTrue()
        ->and($entry->getExtraProperty('path'))->toBe('/admin/users')
        ->and($entry->getExtraProperty('ip'))->toBe('127.0.0.1');
});

it('has no screen for creating or editing audit entries', function () {
    $this->actingAs(staff(SystemRole::SuperAdmin))->get('/admin/audit-log/create')->assertNotFound();
});
