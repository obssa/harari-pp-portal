<?php

return [
    /*
    | Locales the portal serves, in switcher order (PLAN.md §6).
    */
    'locales' => ['en', 'am', 'om', 'har'],

    /*
    | Used when neither the visitor's cookie nor their browser names a
    | supported language (PLAN.md §3). Amharic until the party decides.
    */
    'default_locale' => env('PORTAL_DEFAULT_LOCALE', 'am'),

    /*
    | Dates are stored in UTC and shown in this time zone (Ethiopian and
    | Gregorian dates both follow the local day).
    */
    'timezone' => env('PORTAL_TIMEZONE', 'Africa/Addis_Ababa'),

    /*
    | Where a missing or empty string is looked up next (PLAN.md §6):
    | Harari falls back to Amharic, then English; the others to English.
    */
    'fallbacks' => [
        'en' => ['en'],
        'am' => ['am', 'en'],
        'om' => ['om', 'en'],
        'har' => ['har', 'am', 'en'],
    ],

    /*
    | Each language named in its own script for the language switcher
    | (PLAN.md §12). These are the same in every locale, so they are data,
    | not translations. The Harari name awaits confirmation by the party.
    */
    'locale_names' => [
        'en' => 'English',
        'am' => 'አማርኛ',
        'om' => 'Afaan Oromoo',
        'har' => 'ሐረሪ',
    ],

    /*
    | Staff with these roles must turn on an authenticator app before they can
    | use the admin panel (PLAN.md §7, §8).
    */
    'mfa_required_roles' => ['super_admin', 'admin', 'publisher'],

    /*
    | Allows `php artisan portal:demo-accounts` (one sample account per role).
    | Only ever true on staging.
    */
    'demo_accounts' => (bool) env('PORTAL_DEMO_ACCOUNTS', false),
];
