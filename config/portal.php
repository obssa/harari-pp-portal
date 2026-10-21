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
    | Posts written in these languages stand in on listing pages when a post
    | has no translation of its own (shown with their real language marked).
    */
    'content_fallbacks' => [
        'har' => ['am'],
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
    | use the admin panel, while Settings → Security makes it compulsory
    | (PLAN.md §7, §8, ADR-0018).
    */
    'mfa_required_roles' => ['super_admin', 'admin', 'publisher'],

    /*
    | Queues drained by `php artisan portal:work-queues` (cron, ADR-0002), in
    | priority order. Image conversions run on "media" (PLAN.md §9).
    */
    'queues' => ['default', 'media'],

    /*
    | The hosting quota in megabytes, if the host sets one: the health page
    | warns at 80% and fails at 95% of it (ADR-0037). Empty: size only.
    */
    'storage_quota_mb' => env('PORTAL_STORAGE_QUOTA_MB'),

    /*
    | Addresses or CIDR ranges allowed to open the admin, comma-separated
    | (ADR-0038). Empty: every address, as before.
    */
    'admin_allowed_ips' => env('PORTAL_ADMIN_ALLOWED_IPS', ''),

    /*
    | Days after an event that registrations (names and contact details) are
    | kept before they are deleted (ADR-0025).
    */
    'registration_retention_days' => (int) env('PORTAL_REGISTRATION_RETENTION_DAYS', 180),

    /*
    | Days after a message or ticket was resolved that it is kept, with its
    | attachments and notes, before it is deleted (ADR-0041).
    */
    'submission_retention_days' => (int) env('PORTAL_SUBMISSION_RETENTION_DAYS', 730),

    /*
    | Days after a rejected or withdrawn membership application was decided
    | that it is kept (ADR-0044). Unfinished applications go after a week.
    */
    'closed_application_retention_days' => (int) env('PORTAL_CLOSED_APPLICATION_RETENTION_DAYS', 180),

    /*
    | Membership renewals (ADR-0046): members get one SMS this many days before
    | their renewal is due, and become lapsed this many days after it.
    */
    'membership_reminder_days' => (int) env('PORTAL_MEMBERSHIP_REMINDER_DAYS', 30),
    'membership_grace_days' => (int) env('PORTAL_MEMBERSHIP_GRACE_DAYS', 90),

    /*
    | Campaign sending limits per minute (ADR-0052), to stay within what the mail
    | and SMS accounts accept.
    */
    'campaign_email_per_minute' => (int) env('PORTAL_CAMPAIGN_EMAIL_PER_MINUTE', 200),
    'campaign_sms_per_minute' => (int) env('PORTAL_CAMPAIGN_SMS_PER_MINUTE', 30),

    /*
    | Counting campaign email opens with an invisible image (ADR-0053). Off: many
    | mail apps block it, and it counts people without asking. Clicks are counted
    | through the portal's own links either way.
    */
    'campaign_open_tracking' => (bool) env('PORTAL_CAMPAIGN_OPEN_TRACKING', false),

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
