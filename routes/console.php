<?php

use App\Domain\Analytics\Models\PageViewDay;
use App\Domain\Analytics\Models\TrafficDay;
use App\Domain\Search\Models\SearchTerm;
use App\Filament\Pages\SystemHealth;
use App\Filament\Widgets\QueueHealth;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;
use Spatie\Health\Commands\DispatchQueueCheckJobsCommand;
use Spatie\Health\Commands\RunHealthChecksCommand;
use Spatie\Health\Commands\ScheduleCheckHeartbeatCommand;
use Spatie\Health\Models\HealthCheckResultHistoryItem;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// PLAN.md §11: audit entries are kept for 2 years (config/activitylog.php).
Schedule::command('activitylog:clean --force')->dailyAt('02:30');

// ADR-0013: scheduled posts go live within a minute of their time.
Schedule::command('content:publish-due')->everyMinute()->withoutOverlapping();

// ADR-0022: new stories from the public channels arrive as drafts.
Schedule::command('content:import')->everyThirtyMinutes()->withoutOverlapping();

// ADR-0021: the dashboard shows when the scheduler last ran.
Schedule::call(fn () => Cache::forever(QueueHealth::HEARTBEAT, now()->toIso8601String()))
    ->everyMinute()
    ->name('heartbeat');

// ADR-0025: reminders the day before an event; registrations deleted after the retention period.
Schedule::command('events:send-reminders')->hourly()->withoutOverlapping();
Schedule::command('events:purge-registrations')->dailyAt('03:30');

// ADR-0041: resolved tickets, old notices and failed jobs are removed after their retention period.
Schedule::command('forms:purge-submissions')->dailyAt('03:40');
Schedule::command('membership:purge-applications')->dailyAt('03:42');
Schedule::command('notifications:prune')->dailyAt('03:45');
Schedule::command('queue:prune-failed', ['--hours' => 720])->dailyAt('03:50');

// ADR-0031: social sources are read when their interval has passed.
Schedule::command('social:fetch')->everyFifteenMinutes()->withoutOverlapping();

// ADR-0020, ADR-0037, ADR-0040: search terms unused for a year, health results older than a week and page view
// counts older than two years are removed.
Schedule::command('model:prune', ['--model' => [SearchTerm::class, HealthCheckResultHistoryItem::class, PageViewDay::class, TrafficDay::class]])->dailyAt('03:10');

// ADR-0040: scheduled traffic reports leave at 07:00 Addis Ababa time.
Schedule::command('analytics:send-reports')->dailyAt('04:00')->withoutOverlapping();

// ADR-0052: scheduled campaigns start on the minute.
Schedule::command('campaigns:dispatch')->everyMinute()->withoutOverlapping();

// ADR-0046: renewal reminders go out at 09:00 Addis Ababa time; long-overdue memberships lapse.
Schedule::command('membership:renewals')->dailyAt('06:00')->withoutOverlapping();

// ADR-0037: health checks every five minutes; the scheduler and queue checks need their heartbeats.
Schedule::command(RunHealthChecksCommand::class)->everyFiveMinutes()->withoutOverlapping();
Schedule::command(ScheduleCheckHeartbeatCommand::class)->everyMinute();
Schedule::command(DispatchQueueCheckJobsCommand::class)->everyMinute();

// ADR-0037: backups at 02:00 Addis Ababa time (23:00 UTC), old ones removed before, a check every
// morning; "Back up now" on the health page runs one within a minute; a monthly restore drill.
Schedule::command('backup:clean --disable-notifications')->dailyAt('22:40')->withoutOverlapping();
Schedule::command('backup:run --disable-notifications')->dailyAt('23:00')->withoutOverlapping();
Schedule::command('backup:monitor')->dailyAt('05:00');

// ADR-0038: Facebook page tokens are checked every morning (09:00 Addis Ababa time).
Schedule::command('social:check-tokens')->dailyAt('06:00');
Schedule::command('backup:run --disable-notifications')->everyMinute()->name('backup-requested')->withoutOverlapping()
    ->when(fn (): bool => (bool) Cache::pull(SystemHealth::BACKUP_REQUESTED));
Schedule::command('portal:restore-drill')->monthlyOn(1, '01:00')->withoutOverlapping()
    ->when(fn (): bool => filled(config('database.connections.drill.database')));

// The cron line starts the scheduler after a random pause, so two runs can fall in the same minute
// (the nightly backup ran twice on 2026-10-20): every task starts at most once per due minute. The lock
// lives in the cache, which all runs share.
foreach (Schedule::events() as $event) {
    $event->onOneServer();
}
