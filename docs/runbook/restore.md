# Restore runbook — backups and the restore drill

How the portal is backed up, how to restore it, and how the monthly drill proves that a restore works
(PLAN.md §11, ADR-0037). No secrets live here: the archive password is in the server `.env` and must also
be kept by the owner outside the server.

## What is backed up

| What | Where in the backup | Notes |
|---|---|---|
| The whole database | `db-dumps/mysql-<database>.sql.gz` | `mysqldump --single-transaction --no-tablespaces`: consistent, no table locks |
| Media library originals and conversions | `media/`, `public/` | the files behind every picture, document and video poster |
| Form attachments and other private files | `private/` | personal data |

Not backed up: the code (it is in git and deployed by CI), `.env` (kept by the owner), caches, logs.

- **When**: every night at 02:00 Addis Ababa time (23:00 UTC), from the scheduler; old backups are
  removed at 01:40. Before every deploy that brings new migrations, the database alone is backed up first
  (`before-migrate-….zip`); if that backup fails, the deploy stops before anything changes.
- **Where**: `~/hararipp.orcamind.dev/storage/backups/harari-pp-portal/` (mode 700, outside the web root),
  plus an off-site disk once `BACKUP_OFFSITE_DISK` names one (open question 2 in the Phase 5 plan).
- **Kept**: every backup for 7 days, then one a day for 30 days, one a week for 8 weeks, one a month for 6
  months and one a year; never more than 5 GB (`BACKUP_MAX_MEGABYTES`).
- **Encrypted**: AES-256 zip with `BACKUP_ARCHIVE_PASSWORD`. Without that password no backup can be read:
  keep a copy in the owner's password manager.
- **Watched**: the health page (Administration → System health) fails the "Backups" check when the newest
  backup is older than 26 hours; `backup:monitor` checks age and size every morning; a failed backup or
  clean-up sends a portal notice to administrators.

## Restore the database (one command)

On the server, in the site folder:

```
/opt/alt/php83/usr/bin/php artisan down
/opt/alt/php83/usr/bin/php artisan portal:restore-database                     # the newest backup
/opt/alt/php83/usr/bin/php artisan portal:restore-database harari-pp-portal/2026-10-20-23-00-01.zip
/opt/alt/php83/usr/bin/php artisan migrate --force                             # if the backup is older than the code
/opt/alt/php83/usr/bin/php artisan optimize:clear && /opt/alt/php83/usr/bin/php artisan optimize
/opt/alt/php83/usr/bin/php artisan up
```

The command asks before replacing the tables; every table in the backup is dropped and recreated. Tables
the backup does not hold are left as they are. The restore is written to the audit log.

## Restore the files

```
cd ~/hararipp.orcamind.dev/storage/app
unzip -P "<BACKUP_ARCHIVE_PASSWORD>" ../backups/harari-pp-portal/<backup>.zip 'media/*' 'public/*' 'private/*'
```

Unzip into an empty folder first and move the folders into place if only some files are needed.

## Restore on a new server

1. Provision the server as in `server.md` and deploy the code (CI).
2. Write the `.env` from the owner's copy, with the same `APP_KEY` (personal data in the database is
   encrypted with it) and the same `BACKUP_ARCHIVE_PASSWORD`.
3. Copy the backup zip to `storage/backups/harari-pp-portal/`, then restore the database and the files as
   above.

## The restore drill

`portal:restore-drill` restores the newest backup into a second, empty database, checks that every table
and the migration history came back, counts the rows of the main tables, empties the drill database again
and writes the result to `storage/backups/restore-drills.json` and the audit log. The health page shows the
last drill. It runs on the first day of every month at 04:00 Addis Ababa time once the drill database is
set:

```
DB_DRILL_DATABASE=<account>_hararipp_drill
DB_DRILL_USERNAME=<account>_hararipp       # a user with all privileges on that database
DB_DRILL_PASSWORD=...
```

Creating that database is a cPanel account action (MySQL Databases → create the database and add the
existing user to it); it is waiting for the owner's go-ahead. Until then the drill is run in CI and in the
local tests against a test database (`tests/Feature/Health/BackupsTest.php`).

## Drill log

| Date | Where | Backup | Result |
|---|---|---|---|
| 2026-10-20 | local test stack (MySQL 8) | test backup | passed: all tables and migrations restored, drill database emptied |
