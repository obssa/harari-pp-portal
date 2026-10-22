<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;
use Throwable;

/**
 * How people who apply to join prove their contact details (ADR-0067): a
 * code by SMS (the default, ADR-0044), a code by email, or no code at all,
 * chosen by an administrator under Settings → Membership.
 */
class MembershipSettings extends Settings
{
    public const SMS = 'sms';

    public const EMAIL = 'email';

    public const NONE = 'none';

    public const VERIFICATIONS = [self::SMS, self::EMAIL, self::NONE];

    public string $verification;

    public static function group(): string
    {
        return 'membership';
    }

    /** The method in force; SMS while the settings cannot be read (e.g. before migrations). */
    public static function verification(): string
    {
        try {
            $method = app(self::class)->verification;

            return in_array($method, self::VERIFICATIONS, true) ? $method : self::SMS;
        } catch (Throwable) {
            return self::SMS;
        }
    }
}
