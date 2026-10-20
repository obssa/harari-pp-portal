<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Health\Backups\BackupArchive;
use App\Domain\Health\Backups\RestoreDrill;
use App\Domain\Health\Notifications\HealthFailingNotice;
use App\Filament\Pages\SystemHealth;
use Filament\Actions\Testing\TestAction;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\Events\BackupHasFailed;

use function Pest\Livewire\livewire;

/** A backup made the way the scheduler makes it, into a folder of this test. */
function makeBackup(bool $onlyDb = true): string
{
    $exit = Artisan::call('backup:run', array_filter(['--disable-notifications' => true, '--only-db' => $onlyDb]));
    expect($exit)->toBe(0, Artisan::output());

    return (string) BackupArchive::latest();
}

function drillTables(): array
{
    return Schema::connection('drill')->getTableListing((string) config('database.connections.drill.database'), false);
}

beforeEach(function () {
    $this->backupRoot = sys_get_temp_dir().'/portal-backups-'.uniqid();
    config(['filesystems.disks.backups.root' => $this->backupRoot]);
    Storage::forgetDisk('backups');
});

afterEach(function () {
    File::deleteDirectory($this->backupRoot);
    Schema::connection('drill')->dropAllTables();
});

it('writes the database and the uploads into one encrypted zip', function () {
    $files = sys_get_temp_dir().'/portal-files-'.uniqid();
    File::ensureDirectoryExists("{$files}/public/backup-sample");
    File::put("{$files}/public/backup-sample/sample.txt", 'Sample upload');
    config(['backup.backup.source.files.include' => ["{$files}/public"], 'backup.backup.source.files.relative_path' => $files]);
    app()->forgetScopedInstances();

    $path = makeBackup(onlyDb: false);
    File::deleteDirectory($files);

    $zip = new ZipArchive;
    $zip->open(Storage::disk('backups')->path($path));
    $names = collect(range(0, $zip->numFiles - 1))->map(fn (int $i): string => (string) $zip->getNameIndex($i));
    $encrypted = collect(range(0, $zip->numFiles - 1))
        ->reject(fn (int $i): bool => str_ends_with((string) $zip->getNameIndex($i), '/'))
        ->every(fn (int $i): bool => ($zip->statIndex($i)['encryption_method'] ?? 0) !== ZipArchive::EM_NONE);
    $zip->close();

    expect($names)->toContain('public/backup-sample/sample.txt')
        ->and($names->contains(fn (string $n): bool => str_starts_with($n, 'db-dumps/') && str_ends_with($n, '.sql.gz')))->toBeTrue()
        ->and($encrypted)->toBeTrue()
        ->and(BackupArchive::all()[0])->toMatchArray(['path' => $path, 'name' => basename($path)]);
});

it('restores the newest backup in the drill database, checks it and empties it again', function () {
    $backup = makeBackup();

    $result = app(RestoreDrill::class)->run();

    expect($result)->toMatchArray(['backup' => $backup, 'ok' => true, 'missing' => [], 'error' => null])
        ->and($result['tables'])->toBeGreaterThan(50)
        ->and($result['migrations'])->toBe(DB::table('migrations')->count())
        ->and($result['rows'])->toHaveKey('users')
        ->and(drillTables())->toBe([])
        ->and(RestoreDrill::history()[0]['ok'])->toBeTrue();
});

it('restores a backup into a database with one command, after asking', function () {
    $backup = makeBackup();

    $this->artisan('portal:restore-database', ['--connection' => 'drill'])
        ->expectsConfirmation("Replace the tables of the database \"harari_drill\" (drill) with the backup {$backup}?", 'no')
        ->assertFailed();
    expect(drillTables())->toBe([]);

    $this->artisan('portal:restore-database', ['backup' => $backup, '--connection' => 'drill', '--force' => true])->assertSuccessful();
    expect(drillTables())->toContain('migrations', 'users', 'posts')
        ->and(DB::connection('drill')->table('migrations')->count())->toBe(DB::table('migrations')->count());

    $this->artisan('portal:restore-database', ['backup' => 'harari-pp-portal/missing.zip', '--force' => true])->assertFailed();
});

it('refuses a backup it cannot read', function () {
    $backup = makeBackup();
    config(['backup.backup.password' => 'not-the-password']);

    expect(fn () => BackupArchive::extractDump($backup))->toThrow(RuntimeException::class, 'BACKUP_ARCHIVE_PASSWORD')
        ->and(fn () => BackupArchive::extractDump('../etc/passwd.zip'))->toThrow(RuntimeException::class);

    $result = app(RestoreDrill::class)->run($backup);
    expect($result['ok'])->toBeFalse()->and($result['error'])->toContain('BACKUP_ARCHIVE_PASSWORD');
});

it('backs up the database before migrating, only when migrations are waiting', function () {
    $this->artisan('portal:backup-before-migrate')->expectsOutputToContain('No pending migrations')->assertSuccessful();
    expect(BackupArchive::all())->toBe([]);

    $extra = sys_get_temp_dir().'/portal-migrations-'.uniqid();
    File::ensureDirectoryExists($extra);
    File::put("{$extra}/2099_01_01_000000_sample_pending.php", "<?php\nreturn new class extends Illuminate\\Database\\Migrations\\Migration { public function up(): void {} };\n");
    app('migrator')->path($extra);

    $this->artisan('portal:backup-before-migrate')->expectsOutputToContain('1 pending migration(s)')->assertSuccessful();
    expect(BackupArchive::all()[0]['name'])->toStartWith('before-migrate-');

    File::deleteDirectory($extra);
});

it('tells administrators when a backup fails', function () {
    Notification::fake();
    $admin = staff(SystemRole::Admin);

    event(new BackupHasFailed(new Exception('The disk is full.')));

    Notification::assertSentTo($admin, HealthFailingNotice::class, fn (HealthFailingNotice $n): bool => $n->check === 'backups'
        && str_contains($n->message, 'The disk is full.'));
});

it('lists the backups and lets only Super Admins start or download one', function () {
    $backup = makeBackup();

    $this->actingAs(staff(SystemRole::Admin));
    livewire(SystemHealth::class)
        ->assertCanSeeTableRecords([$backup])
        ->assertActionHidden(TestAction::make('backupNow')->table())
        ->assertActionHidden(TestAction::make('download')->table($backup));

    $this->actingAs(staff(SystemRole::SuperAdmin));
    livewire(SystemHealth::class)
        ->callAction(TestAction::make('backupNow')->table())
        ->assertNotified(__('health.backups.requested'))
        ->callAction(TestAction::make('download')->table($backup))
        ->assertFileDownloaded(basename($backup));

    expect(Cache::get(SystemHealth::BACKUP_REQUESTED))->toBeTrue();
});

it('runs a requested backup from the scheduler once', function () {
    $requested = collect(app(Schedule::class)->events())->first(fn (ScheduledEvent $event): bool => $event->description === 'backup-requested');
    expect($requested)->not->toBeNull()
        ->and($requested->filtersPass(app()))->toBeFalse();

    Cache::put(SystemHealth::BACKUP_REQUESTED, true, now()->addHour());

    expect($requested->filtersPass(app()))->toBeTrue()
        ->and($requested->filtersPass(app()))->toBeFalse();
});
