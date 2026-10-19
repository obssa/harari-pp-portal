<?php

use App\Domain\Access\Enums\SystemRole;
use App\Filament\Pages\ManageSecurity;
use App\Settings\SecuritySettings;
use Filament\Facades\Filament;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

it('starts with two-step sign-in optional', function () {
    expect(app(SecuritySettings::class)->require_mfa)->toBeFalse()
        ->and(SecuritySettings::mfaRequired())->toBeFalse()
        ->and(staff(SystemRole::SuperAdmin, mfa: false)->requiresMfa())->toBeFalse();
});

it('shows the security page to Super Admins only', function (SystemRole $role, int $status) {
    $this->actingAs(staff($role))->get('/admin/settings/security')->assertStatus($status);
})->with([
    'super admin' => [SystemRole::SuperAdmin, 200],
    'admin' => [SystemRole::Admin, 403],
    'publisher' => [SystemRole::Publisher, 403],
    'editor' => [SystemRole::Editor, 403],
]);

it('lets a Super Admin make two-step sign-in compulsory and optional again, with an audit entry', function () {
    $publisher = staff(SystemRole::Publisher, mfa: false);
    $this->actingAs(staff(SystemRole::SuperAdmin));

    livewire(ManageSecurity::class)
        ->assertSee(__('admin.settings.security.field.without_app'))
        ->fillForm(['require_mfa' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(SecuritySettings::class)->require_mfa)->toBeTrue()
        ->and($publisher->fresh()?->requiresMfa())->toBeTrue();
    $entry = Activity::query()->where('log_name', 'settings')->latest('id')->firstOrFail();
    expect($entry->getExtraProperty('attributes'))->toBe(['require_mfa' => true]);

    $this->actingAs($publisher)
        ->get('/admin')
        ->assertRedirect(Filament::getPanel('admin')->getSetUpRequiredMultiFactorAuthenticationUrl());

    $this->actingAs(staff(SystemRole::SuperAdmin));
    livewire(ManageSecurity::class)->fillForm(['require_mfa' => false])->call('save')->assertHasNoFormErrors();

    $this->actingAs($publisher->fresh())->get('/admin')->assertOk();
});

it('counts people in the covered roles who have not set up the app', function () {
    staff(SystemRole::Admin, mfa: false);
    staff(SystemRole::Publisher, mfa: true);
    staff(SystemRole::Editor, mfa: false);

    expect(ManageSecurity::coveredWithoutApp())->toBe(1);
});
