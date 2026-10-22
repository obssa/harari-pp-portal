<?php

use App\Domain\Access\Enums\SystemRole;

it('brands the admin with the party logo, its colours and self-hosted fonts', function () {
    $html = (string) $this->get('/admin/login')->assertOk()->getContent();

    expect($html)->toContain('/admin/brand.css?v=', 'hpp-brand', '/brand/logo-128.png', __('admin.brand.sub'))
        ->not->toContain('fonts.bunny.net')
        ->and(file_get_contents(public_path('admin/brand.css')))->toContain('/fonts/portal/inter-latin-wght-v5.woff2');
    expect(public_path('fonts/portal/inter-latin-wght-v5.woff2'))->toBeFile();
});

it('opens the dashboard on a welcome band with the date and a link to the site', function () {
    $this->actingAs(staff(SystemRole::SuperAdmin, mfa: false, attributes: ['name' => 'Sample Administrator', 'locale' => 'am']));

    $this->get('/admin')->assertOk()
        ->assertSee('hpp-welcome', escape: false)
        ->assertSee(__('dashboard.welcome.greeting', ['name' => 'Sample'], 'am'))
        ->assertSee('href="'.url('/am').'"', escape: false)
        ->assertSee(__('dashboard.customise', [], 'am'));
});
