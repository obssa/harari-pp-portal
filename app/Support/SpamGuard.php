<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

/**
 * Cheap checks against form robots (PLAN.md §11): a field people never see
 * must stay empty, and the form must have been open for a few seconds but
 * not for days. Turnstile is checked separately when it is configured.
 */
final class SpamGuard
{
    public const HONEYPOT = 'website';

    public const STARTED = 'form_started';

    /** Fastest believable time to fill in a form. */
    public const MIN_SECONDS = 3;

    /** After this the form must be opened again. */
    public const MAX_SECONDS = 6 * 3600;

    public static function stamp(): string
    {
        return Crypt::encryptString((string) time());
    }

    public static function check(Request $request): void
    {
        $started = null;
        try {
            $started = (int) Crypt::decryptString((string) $request->input(self::STARTED, ''));
        } catch (DecryptException) {
            // A missing or altered stamp counts as a robot.
        }

        $age = $started === null ? -1 : time() - $started;
        if (filled($request->input(self::HONEYPOT)) || $age < self::MIN_SECONDS || $age > self::MAX_SECONDS) {
            throw ValidationException::withMessages(['form' => __('forms.spam')]);
        }
    }
}
