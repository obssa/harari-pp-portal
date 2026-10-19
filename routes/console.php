<?php

use App\Domain\Search\Models\SearchTerm;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// PLAN.md §11: audit entries are kept for 2 years (config/activitylog.php).
Schedule::command('activitylog:clean --force')->dailyAt('02:30');

// ADR-0013: scheduled posts go live within a minute of their time.
Schedule::command('content:publish-due')->everyMinute()->withoutOverlapping();

// ADR-0020: search terms nobody searched for in a year are removed.
Schedule::command('model:prune', ['--model' => [SearchTerm::class]])->dailyAt('03:10');
