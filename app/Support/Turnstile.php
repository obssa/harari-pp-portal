<?php

namespace App\Support;

use App\Settings\IntegrationSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Cloudflare Turnstile (PLAN.md §11) on public forms, once both keys are
 * saved under Administration → Integrations. Without keys the forms rely on
 * the honeypot, the time check and rate limits.
 */
final class Turnstile
{
    public const FIELD = 'cf-turnstile-response';

    public const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public const SCRIPT_URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js';

    public function __construct(private readonly IntegrationSettings $settings) {}

    public function enabled(): bool
    {
        return filled($this->settings->turnstile_site_key) && filled($this->settings->turnstile_secret_key);
    }

    public function siteKey(): ?string
    {
        return $this->enabled() ? $this->settings->turnstile_site_key : null;
    }

    /** Fails closed: if Cloudflare cannot be asked, the visitor tries again. */
    public function check(Request $request): void
    {
        if (! $this->enabled()) {
            return;
        }

        $answer = (string) $request->input(self::FIELD, '');
        $passed = false;
        if ($answer !== '') {
            try {
                $passed = (bool) Http::asForm()->timeout(5)->post(self::VERIFY_URL, [
                    'secret' => $this->settings->turnstile_secret_key,
                    'response' => $answer,
                    'remoteip' => $request->ip(),
                ])->json('success', false);
            } catch (Throwable) {
                $passed = false;
            }
        }

        if (! $passed) {
            throw ValidationException::withMessages(['form' => __('forms.turnstile')]);
        }
    }
}
