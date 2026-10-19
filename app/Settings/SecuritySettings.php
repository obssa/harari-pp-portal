<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;
use Throwable;

/**
 * Sign-in rules (ADR-0018). Two-step sign-in is optional for everyone until
 * a Super Admin turns on `require_mfa`; it then becomes compulsory for the
 * roles in config('portal.mfa_required_roles').
 */
class SecuritySettings extends Settings
{
    public bool $require_mfa;

    public static function group(): string
    {
        return 'security';
    }

    /** Whether the switch is on; off while the settings cannot be read (e.g. before migrations). */
    public static function mfaRequired(): bool
    {
        try {
            return app(self::class)->require_mfa;
        } catch (Throwable) {
            return false;
        }
    }
}
