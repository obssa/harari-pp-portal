<?php

use App\Domain\Access\Enums\SystemRole;

it('shows the admin panel in English by default', function () {
    $this->actingAs(staff(SystemRole::Admin))
        ->get('/admin/users')
        ->assertOk()
        ->assertSee(__('admin.nav.administration', [], 'en'));
});

it('shows the admin panel in the staff member\'s chosen language', function () {
    $this->actingAs(staff(SystemRole::Admin, attributes: ['locale' => 'am']))
        ->get('/admin/users')
        ->assertOk()
        ->assertSee('አስተዳደር')
        ->assertSee('lang="am"', escape: false);
});

it('falls back to English for Filament labels Amharic does not cover', function () {
    $this->actingAs(staff(SystemRole::Admin, attributes: ['locale' => 'am']))
        ->get('/admin/users')
        ->assertOk()
        ->assertDontSee('filament-panels::');
});
