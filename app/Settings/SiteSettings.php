<?php

namespace App\Settings;

use App\Domain\Translation\Support\Locales;
use Spatie\LaravelSettings\Settings;

/**
 * Site-wide settings editable in the admin (PLAN.md §7 Settings).
 * Translatable values are stored per locale: ['en' => ..., 'am' => ...].
 */
class SiteSettings extends Settings
{
    public string $default_locale;

    /** Which calendar leads in dual dates: "ethiopian" or "gregorian". */
    public string $calendar_primary;

    public bool $show_secondary_calendar;

    public ?string $contact_email;

    public ?string $contact_phone;

    public ?string $hotline;

    /** @var array<string, string> */
    public array $address;

    /** @var array<string, string> */
    public array $office_hours;

    /** @var array<string, string> platform => URL */
    public array $social_links;

    /** ADR-0071/0072: the Facebook page's posts on the site: at once (auto, the default), after the visitor agrees (ask), or off. */
    public string $facebook_feed;

    public const FACEBOOK_FEED = ['off', 'ask', 'auto'];

    public static function group(): string
    {
        return 'site';
    }

    /**
     * A per-locale value in the current language, following the fallback chain.
     */
    public function localized(string $property, ?string $locale = null): ?string
    {
        $values = $this->{$property} ?? null;
        if (! is_array($values)) {
            return null;
        }

        foreach (Locales::fallbackChain($locale ?? app()->getLocale()) as $candidate) {
            if (filled($values[$candidate] ?? null)) {
                return (string) $values[$candidate];
            }
        }

        return null;
    }
}
