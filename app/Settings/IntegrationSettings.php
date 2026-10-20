<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Credentials for outside services (PLAN.md §7: API keys encrypted, Super
 * Admin only). Secrets are encrypted at rest and never shown back in the admin.
 */
class IntegrationSettings extends Settings
{
    /** "log" until an SMS provider is chosen (ethiotelecom, africastalking, twilio). */
    public string $sms_driver;

    public ?string $sms_api_key;

    public ?string $sms_sender_id;

    public ?string $google_translate_api_key;

    /** "anthropic" or "openai" for the optional AI translation driver. */
    public string $llm_provider;

    public ?string $llm_api_key;

    /** Machine translation (ADR-0035): "none", "google" or "llm". */
    public string $mt_driver;

    /** The LLM model; empty for the provider's default. */
    public ?string $llm_model;

    public ?string $meta_page_access_token;

    public ?string $youtube_api_key;

    public ?string $telegram_bot_token;

    public ?string $turnstile_site_key;

    public ?string $turnstile_secret_key;

    public static function group(): string
    {
        return 'integrations';
    }

    /**
     * @return list<string>
     */
    public static function encrypted(): array
    {
        return self::secretFields();
    }

    /**
     * @return list<string>
     */
    public static function secretFields(): array
    {
        return [
            'sms_api_key',
            'google_translate_api_key',
            'llm_api_key',
            'meta_page_access_token',
            'youtube_api_key',
            'telegram_bot_token',
            'turnstile_secret_key',
        ];
    }

    public function isConfigured(string $field): bool
    {
        return filled($this->{$field} ?? null);
    }
}
