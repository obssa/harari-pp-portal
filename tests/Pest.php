<?php

use App\Domain\Access\Enums\SystemRole;
use App\Models\User;
use App\Settings\SecuritySettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Feature tests boot the app and run against MySQL (harari_test) inside a
| transaction-safe refresh. Unit tests also get the app container so services
| can resolve config and translations, but never touch the database.
*/
pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(TestCase::class)->in('Unit');

/**
 * A staff account with one system role. Privileged roles get an
 * authenticator secret by default so they pass the MFA requirement.
 *
 * @param  array<string, mixed>  $attributes
 */
/** Turns on Settings → Security "require two-step sign-in" (ADR-0018). */
function requireMfa(bool $on = true): void
{
    $settings = app(SecuritySettings::class);
    $settings->require_mfa = $on;
    $settings->save();
}

function staff(SystemRole $role, ?bool $mfa = null, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->assignRole($role->value);

    if ($mfa ?? $role->requiresMfa()) {
        $user->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP'])->saveQuietly();
    }

    return $user->fresh() ?? $user;
}
