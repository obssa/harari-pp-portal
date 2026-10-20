<?php

use Spatie\Backup\Notifications\Notifiable;
use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification;
use Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification;
use Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;
use Spatie\DbDumper\Compressors\GzipCompressor;

/*
| Backups (PLAN.md §11, ADR-0037): the database and the uploaded files (media
| library, public uploads, form attachments) every night, as one encrypted zip
| on the server disk outside the web root, kept 30 days with fewer older
| copies. An off-site copy is added by naming a second filesystem disk in
| BACKUP_OFFSITE_DISK. The code itself is in git and is not backed up.
*/

$disks = ['backups'];
if (filled(env('BACKUP_OFFSITE_DISK'))) {
    $disks[] = (string) env('BACKUP_OFFSITE_DISK');
}
$name = env('BACKUP_NAME', 'harari-pp-portal');

return [

    'backup' => [
        'name' => $name,

        'source' => [
            'files' => [
                'include' => [
                    storage_path('app/public'),
                    storage_path('app/media'),
                    storage_path('app/private'),
                ],
                'exclude' => [
                    storage_path('app/public/smoke.txt'),
                ],
                'follow_links' => false,
                'ignore_unreadable_directories' => false,
                'relative_path' => storage_path('app'),
            ],

            'databases' => [
                env('DB_CONNECTION', 'mysql'),
            ],
        ],

        'database_dump_compressor' => GzipCompressor::class,
        'database_dump_file_timestamp_format' => null,
        'database_dump_filename_base' => 'database',
        'database_dump_file_extension' => '',

        'destination' => [
            'compression_method' => ZipArchive::CM_DEFAULT,
            'compression_level' => 9,
            'filename_prefix' => '',
            'disks' => $disks,
            // With an off-site disk, a copy that fails there still keeps the local one.
            'continue_on_failure' => count($disks) > 1,
        ],

        'temporary_directory' => storage_path('app/backup-temp'),

        // AES-256. Keep this password outside the server too: without it no backup can be read.
        'password' => env('BACKUP_ARCHIVE_PASSWORD'),
        'encryption' => 'default',

        'verify_backup' => true,
        'tries' => 1,
        'retry_delay' => 0,
    ],

    // The package sends no mail of its own: portal notices go to administrators (ADR-0036, ADR-0037).
    'notifications' => [
        'notifications' => [
            BackupHasFailedNotification::class => [],
            UnhealthyBackupWasFoundNotification::class => [],
            CleanupHasFailedNotification::class => [],
            BackupWasSuccessfulNotification::class => [],
            HealthyBackupWasFoundNotification::class => [],
            CleanupWasSuccessfulNotification::class => [],
        ],
        'notifiable' => Notifiable::class,
        'mail' => [
            'to' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                'name' => env('MAIL_FROM_NAME', 'Example'),
            ],
        ],
        'slack' => ['webhook_url' => '', 'channel' => null, 'username' => null, 'icon' => null],
        'discord' => ['webhook_url' => '', 'username' => '', 'avatar_url' => ''],
        'webhook' => ['url' => ''],
    ],

    'log_channel' => null,

    'monitor_backups' => [
        [
            'name' => $name,
            'disks' => $disks,
            'health_checks' => [
                MaximumAgeInDays::class => 1,
                MaximumStorageInMegabytes::class => (int) env('BACKUP_MAX_MEGABYTES', 5000),
            ],
        ],
    ],

    'cleanup' => [
        'strategy' => DefaultStrategy::class,
        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 30,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 6,
            'keep_yearly_backups_for_years' => 1,
            'delete_oldest_backups_when_using_more_megabytes_than' => (int) env('BACKUP_MAX_MEGABYTES', 5000),
        ],
        'tries' => 1,
        'retry_delay' => 0,
    ],

];
