<?php

/*
| Laravel Scout (ADR-0002, ADR-0020). Only the settings this portal changes;
| the package's own config file supplies the rest. The database driver is the
| default so a missing SCOUT_DRIVER never points saves at a service the host
| does not have.
*/

return [
    'driver' => env('SCOUT_DRIVER', 'database'),

    'queue' => env('SCOUT_QUEUE', false),
];
