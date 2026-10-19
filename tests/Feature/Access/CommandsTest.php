<?php

use App\Console\Commands\DemoAccountsCommand;
use App\Domain\Access\Enums\SystemRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates a Super Admin with a one-time password that works', function () {
    $this->artisan('portal:create-super-admin', ['email' => 'Chair@Example.org', '--name' => 'Chair'])
        ->expectsOutputToContain('Super Admin chair@example.org created')
        ->assertSuccessful();

    $user = User::query()->where('email', 'chair@example.org')->firstOrFail();
    expect($user->isSuperAdmin())->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->requiresMfa())->toBeFalse();

    requireMfa();
    expect($user->fresh()?->requiresMfa())->toBeTrue();
});

it('refuses to overwrite an existing account unless asked to promote it', function () {
    $user = User::factory()->create(['email' => 'existing@example.org', 'password' => 'Keep-This-Password-1']);

    $this->artisan('portal:create-super-admin', ['email' => 'existing@example.org'])->assertFailed();
    expect($user->fresh()->isSuperAdmin())->toBeFalse();

    $this->artisan('portal:create-super-admin', ['email' => 'existing@example.org', '--promote' => true])->assertSuccessful();
    $user = $user->fresh();
    expect($user->isSuperAdmin())->toBeTrue()
        ->and(Hash::check('Keep-This-Password-1', $user->password))->toBeTrue();
});

it('rejects an invalid email', function () {
    $this->artisan('portal:create-super-admin', ['email' => 'not-an-email'])->assertExitCode(2);
});

it('syncs permissions from the command line', function () {
    $this->artisan('portal:sync-permissions')->expectsOutputToContain('Permissions: 0 created')->assertSuccessful();
});

it('refuses to create sample accounts unless enabled outside production', function () {
    config(['portal.demo_accounts' => false]);
    $this->artisan('portal:demo-accounts')->assertFailed();

    config(['portal.demo_accounts' => true]);
    $this->app['env'] = 'production';
    $this->artisan('portal:demo-accounts')->assertFailed();

    expect(User::query()->where('email', 'like', '%@'.DemoAccountsCommand::DOMAIN)->count())->toBe(0);
});

it('creates one labelled sample account per role on staging and removes them again', function () {
    config(['portal.demo_accounts' => true]);
    $this->app['env'] = 'staging';

    $this->artisan('portal:demo-accounts')->assertSuccessful();

    $samples = User::query()->where('email', 'like', '%@'.DemoAccountsCommand::DOMAIN)->get();
    expect($samples)->toHaveCount(count(SystemRole::cases()));
    $translator = $samples->firstWhere('email', 'translator@'.DemoAccountsCommand::DOMAIN);
    expect($translator->name)->toStartWith('Sample')
        ->and($translator->canEditTranslationsFor('har'))->toBeTrue()
        ->and($translator->canEditTranslationsFor('am'))->toBeFalse();

    $this->artisan('portal:demo-accounts', ['--remove' => true])->assertSuccessful();
    expect(User::withTrashed()->where('email', 'like', '%@'.DemoAccountsCommand::DOMAIN)->count())->toBe(0);
});
