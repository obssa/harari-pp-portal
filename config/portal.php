<?php

return [
    /*
    | Locales the portal serves, in switcher order (PLAN.md §6).
    */
    'locales' => ['en', 'am', 'om', 'har'],

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
