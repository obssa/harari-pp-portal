<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// PLAN.md §11: audit entries are kept for 2 years (config/activitylog.php).
Schedule::command('activitylog:clean --force')->dailyAt('02:30');
