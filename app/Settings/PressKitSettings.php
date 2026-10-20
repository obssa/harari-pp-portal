<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * What journalists find on the press page (PLAN.md §4: logos, colours,
 * boilerplate, leadership portraits, media contact; ADR-0029).
 */
class PressKitSettings extends Settings
{
    /** @var array<string, string> per language */
    public array $boilerplate;

    /** @var array<string, string> per language, e.g. "Communication office" */
    public array $contact_name;

    public ?string $contact_email;

    public ?string $contact_phone;

    /** @var list<int> library media ids of logos to download */
    public array $logo_ids;

    /** The press enquiry form, when there is one. */
    public ?string $form_id;

    public static function group(): string
    {
        return 'press';
    }
}
