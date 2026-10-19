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
    | Queues drained by `php artisan portal:work-queues` (cron, ADR-0002), in
    | priority order. Image conversions run on "media" (PLAN.md §9).
    */
    'queues' => ['default', 'media'],

    /*
    | Media library (PLAN.md §7, §11; ADR-0009). Files are identified by their
    | content, never by their name; only these kinds are accepted.
    */
    'media' => [
        // Largest upload per kind of file, in megabytes.
        'max_mb' => [
            'image' => 20,
            'vector' => 2,
            'document' => 25,
            'video' => 50,
            'audio' => 25,
        ],

        // Photos are scaled down to this longest edge on upload; images with
        // more pixels than this are refused (decompression bombs).
        'max_edge' => 3840,
        'max_megapixels' => 50,

        // Make AVIF copies as well as WebP: "auto" when the server's GD can write AVIF.
        'avif' => env('MEDIA_AVIF', 'auto'),

        // Shown against the storage used on the media screen.
        'quota_gb' => (float) env('MEDIA_QUOTA_GB', 10),

        // Cropped copies, made around each image's focal point (PLAN.md §12:
        // 16:9 for posts, 1:1 for portraits, 3:2 for offices). Widths larger
        // than the original are skipped.
        'presets' => [
            'landscape' => ['ratio' => [16, 9], 'widths' => [480, 960, 1600]],
            'square' => ['ratio' => [1, 1], 'widths' => [320, 640]],
            'photo' => ['ratio' => [3, 2], 'widths' => [600, 1200]],
        ],
    ],

    /*
    | Allows `php artisan portal:demo-accounts` (one sample account per role).
    | Only ever true on staging.
    */
    'demo_accounts' => (bool) env('PORTAL_DEMO_ACCOUNTS', false),
];
