<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Health\Checks\FailedJobsCheck;
use App\Domain\Health\Checks\MailCheck;
use App\Domain\Health\Checks\QueueBacklogCheck;
use App\Domain\Health\Checks\StorageSizeCheck;
use App\Domain\Health\HealthChecks;
use App\Domain\Health\Mail\TestMail;
use App\Domain\Health\Notifications\HealthFailingNotice;
use App\Filament\Pages\SystemHealth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Result;
use Spatie\Health\Commands\RunHealthChecksCommand;
use Spatie\Health\Events\CheckEndedEvent;
use Spatie\Health\Facades\Health;
use Spatie\Health\Models\HealthCheckResultHistoryItem;

use function Pest\Livewire\livewire;

function failedJobRows(int $count): void
{
    foreach (range(1, $count) as $i) {
        DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default',
            'payload' => '{}', 'exception' => 'Sample failure', 'failed_at' => now()]);
    }
}

function waitingJob(int $minutesAgo): void
{
    DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null,
        'available_at' => now()->subMinutes($minutesAgo)->getTimestamp(), 'created_at' => now()->subMinutes($minutesAgo)->getTimestamp()]);
}

it('registers the portal checks by name', function () {
    $names = Health::registeredChecks()->map->getName()->all();

    expect($names)->toBe(HealthChecks::NAMES);
});

it('runs the checks and keeps the results', function () {
    Artisan::call(RunHealthChecksCommand::class, ['--no-notification' => true]);

    expect(HealthCheckResultHistoryItem::query()->where('check_name', 'database')->value('status'))->toBe('ok')
        ->and(HealthCheckResultHistoryItem::query()->where('check_name', 'cache')->value('status'))->toBe('ok')
        ->and(HealthCheckResultHistoryItem::query()->where('check_name', 'scheduler')->value('status'))->toBe('failed');
});

it('counts failed jobs', function () {
    expect(FailedJobsCheck::new()->run()->status->value)->toBe('ok');

    failedJobRows(2);
    expect(FailedJobsCheck::new()->run()->status->value)->toBe('warning');

    failedJobRows(8);
    expect(FailedJobsCheck::new()->run())->status->value->toBe('failed')
        ->and(FailedJobsCheck::new()->run()->getShortSummary())->toBe('10');
});

it('notices jobs that wait too long', function () {
    waitingJob(2);
    expect(QueueBacklogCheck::new()->run()->status->value)->toBe('ok');

    waitingJob(20);
    expect(QueueBacklogCheck::new()->run()->status->value)->toBe('warning');

    waitingJob(90);
    expect(QueueBacklogCheck::new()->run()->status->value)->toBe('failed');
});

it('checks that mail can leave the portal', function () {
    config(['mail.default' => 'log']);
    expect(MailCheck::new()->run()->status->value)->toBe('warning');

    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '']);
    expect(MailCheck::new()->run()->status->value)->toBe('failed');

    config(['mail.mailers.smtp.host' => 'mail.example.org', 'mail.from.address' => 'portal@example.org']);
    expect(MailCheck::new()->run()->status->value)->toBe('ok');
});

it('measures media and uploads against the quota', function () {
    $dir = sys_get_temp_dir().'/health-storage-'.uniqid();
    mkdir("{$dir}/nested", recursive: true);
    file_put_contents("{$dir}/nested/sample.bin", str_repeat('x', 2 * 1024 * 1024));

    $check = StorageSizeCheck::new()->paths([$dir, "{$dir}/missing"]);
    expect($check->run()->getShortSummary())->toBe('2 MB')
        ->and($check->quotaMb(10)->run()->status->value)->toBe('ok')
        ->and($check->quotaMb(2)->run()->status->value)->toBe('failed');
});

it('tells administrators once when a check starts failing', function () {
    Notification::fake();
    $admin = staff(SystemRole::Admin);
    $editor = staff(SystemRole::Editor);
    $check = DatabaseCheck::new()->name('database');

    event(new CheckEndedEvent($check, Result::make()->failed('Could not connect.')));
    Notification::assertSentTo($admin, HealthFailingNotice::class, fn (HealthFailingNotice $n): bool => $n->check === 'database');
    Notification::assertNotSentTo($editor, HealthFailingNotice::class);

    HealthCheckResultHistoryItem::query()->create(['check_name' => 'database', 'check_label' => 'Database', 'status' => 'failed',
        'notification_message' => 'Could not connect.', 'short_summary' => 'Failed', 'meta' => [], 'ended_at' => now(), 'batch' => (string) Str::uuid()]);
    event(new CheckEndedEvent($check, Result::make()->failed('Could not connect.')));
    event(new CheckEndedEvent($check, Result::make()->ok()));

    Notification::assertSentToTimes($admin, HealthFailingNotice::class, 1);
});

it('shows the latest results to administrators and runs the checks on request', function () {
    $this->actingAs(staff(SystemRole::Admin));

    livewire(SystemHealth::class)
        ->assertOk()
        ->assertSee(__('health.none'))
        ->callAction('runChecks')
        ->assertNotified(__('health.action.ran'));

    livewire(SystemHealth::class)
        ->assertSee(__('health.check.database'))
        ->assertSee(__('health.status.ok'))
        ->assertDontSee(__('health.none'));
});

it('sends a test email to the person asking', function () {
    Mail::fake();
    $admin = staff(SystemRole::Admin, attributes: ['email' => 'sample.admin@example.org']);
    $this->actingAs($admin);

    livewire(SystemHealth::class)->callAction('testMail')
        ->assertNotified(__('health.action.test_mail_sent', ['email' => 'sample.admin@example.org']));

    Mail::assertSent(TestMail::class, fn (TestMail $mail): bool => $mail->hasTo('sample.admin@example.org'));
});

it('is only for staff who may see the settings', function () {
    $this->actingAs(staff(SystemRole::Editor))->get(SystemHealth::getUrl())->assertForbidden();
    $this->actingAs(staff(SystemRole::Admin))->get(SystemHealth::getUrl())->assertOk();
});
