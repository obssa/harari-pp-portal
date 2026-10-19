# Server runbook — staging (hararipp.orcamind.dev)

Records how the staging server is set up and operated. No secrets or server names live here (the
repository is public); they are in the server `.env`, GitHub secrets, and the owner's records.

## Facts

| Item | Value |
|---|---|
| URL | https://hararipp.orcamind.dev (Cloudflare DNS + proxy, LiteSpeed origin) |
| Host | shared cPanel account with CloudLinux (host name only in the GitHub secret `DEPLOY_HOST`) |
| Site folder | `~/hararipp.orcamind.dev` (outside `public_html`; `.env` lives here, mode 600) |
| Document root | `~/hararipp.orcamind.dev/public` (changed from cPanel's default `public_html/hararipp.orcamind.dev` with `uapi SubDomain changedocroot`) |
| PHP (web) | CloudLinux `alt-php83` (8.3.35) for this domain only, set with MultiPHP; every other site on the account stays on its own version |
| PHP (CLI, cron, deploy) | `/opt/alt/php83/usr/bin/php` (GitHub variable `DEPLOY_PHP_BIN`) |
| Database | MySQL database and user `<account>_hararipp`, all privileges; password only in the server `.env` |
| Drivers | database cache, sessions and queue; Scout database driver; mail to the log until SMTP is set up (ADR-0002) |
| Deploy key | ed25519 key used only by this repository's GitHub Actions, authorised with the `restrict` option (commands and rsync only; no forwarding, no terminal) |
| Host key | pinned in the GitHub secret `DEPLOY_KNOWN_HOSTS`; fingerprints checked against the keys the owner's PC already trusted |
| Rollback snapshot | `~/releases/hararipp/prev.tar.gz` (code only, written before every deploy) |
| Leftover | `~/public_html/hararipp.orcamind.dev` (cPanel's original folder) is no longer served and can be removed |

Status: **provisioned 2026-10-18**; first release `502124372d0f` deployed by GitHub Actions.

## One-time provisioning checklist

| # | Step | How it was done | Done |
|---|---|---|---|
| 1 | Subdomain document root | `uapi SubDomain changedocroot domain=hararipp.orcamind.dev docroot=hararipp.orcamind.dev/public` | 2026-10-18 |
| 2 | PHP 8.3 for this domain only | `uapi LangPHP php_set_vhost_versions vhost=hararipp.orcamind.dev version=alt-php83`; cPanel writes the handler into `public/.htaccess` | 2026-10-18 |
| 3 | PHP extensions | web and CLI checked: pdo_mysql, intl, mbstring, gd (WebP/AVIF), zip, bcmath, exif, sodium, fileinfo, dom, curl, openssl all present; `calendar` (CLI) checked 2026-10-19 | 2026-10-18 |
| 4 | Database + user | `uapi Mysql create_database / create_user / set_privileges_on_database`, random 40-character password generated on the server | 2026-10-18 |
| 5 | Server `.env` | written on the server from the `.env.example` template, own `APP_KEY`, mode 600; login to the database verified | 2026-10-18 |
| 6 | Deploy key | generated on the owner's PC, public half appended to `~/.ssh/authorized_keys` with `restrict` (file backed up first), private half in the GitHub secret `DEPLOY_SSH_KEY` | 2026-10-18 |
| 7 | Pinned host key | `ssh-keyscan` output compared with the owner's known_hosts, stored as `DEPLOY_KNOWN_HOSTS` | 2026-10-18 |
| 8 | GitHub secrets and variables | secrets `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_PORT`, `DEPLOY_SSH_KEY`, `DEPLOY_KNOWN_HOSTS`; variables `DEPLOY_PATH`, `DEPLOY_PHP_BIN`, `DEPLOY_URL`, `DEPLOY_ENABLED=true` | 2026-10-18 |
| 9 | Cron | two lines appended (crontab backed up to `~/backups/` first; other sites' lines untouched); queue line switched to `portal:work-queues` on 2026-10-19 | 2026-10-18 |
| 10 | Cloudflare | DNS record for `hararipp` proxied; site answers over HTTPS | 2026-10-18 |

### Cron entries

```
* * * * * sleep $((RANDOM \% 60)) && cd $HOME/hararipp.orcamind.dev && /opt/alt/php83/usr/bin/php artisan schedule:run >> /dev/null 2>&1
* * * * * cd $HOME/hararipp.orcamind.dev && /opt/alt/php83/usr/bin/php artisan portal:work-queues >> /dev/null 2>&1
```

`portal:work-queues` drains every queue in `config('portal.queues')` (default, media) and stops when
they are empty or after 50 seconds, so runs never overlap. Adding a queue in code needs no cron change.

## First Super Admin (one time)

Done on 2026-10-19. For another Super Admin, run this in the cPanel terminal (or over SSH), replacing
both values in angle brackets with the real email address and full name, and keep the printed
password private; it is not stored anywhere:

```
cd ~/hararipp.orcamind.dev
/opt/alt/php83/usr/bin/php artisan portal:create-super-admin <email> --name="<full name>"
```

Then sign in at https://hararipp.orcamind.dev/admin, change the password under Profile (the email and
name can also be corrected there) and set up the authenticator app (save the recovery codes).
Two-step sign-in is optional until a Super Admin turns on **Administration → Security → Make two-step
sign-in compulsory for senior roles** (ADR-0018); turn it on once the senior staff have set up the app.
If a Super Admin is locked out with the switch on and no recovery codes, clear it on the server:
`php artisan tinker --execute="\$s = app(App\Settings\SecuritySettings::class); \$s->require_mfa = false; \$s->save();"`.

Optional sample accounts for testing each role (staging only): add `PORTAL_DEMO_ACCOUNTS=true` to the
server `.env`, run `php artisan config:cache`, then `php artisan portal:demo-accounts`. Remove them with
`php artisan portal:demo-accounts --remove`.

## How a release works

1. A push to `main` runs CI (lint, tests with coverage, asset build).
2. On green, the Deploy workflow builds the release on the runner (`composer install --no-dev`,
   `npm ci && npm run build`), records the git revision in `REVISION`, and ships Laravel's
   `public/.htaccess` as `scripts/deploy/public.htaccess`.
3. `scripts/deploy/pre-release.sh` runs on the server: maintenance mode, snapshot of the current code.
4. `rsync --delete` copies the release. `scripts/deploy/rsync-excludes.txt` lists what is never copied or
   deleted: `.env*`, `storage/`, `public/storage`, `bootstrap/cache/`, `public/.htaccess`, and files
   cPanel creates (`.well-known`, `cgi-bin`, `.user.ini`, `php.ini`, `error_log`).
5. `scripts/deploy/release.sh` runs on the server: rebuilds `public/.htaccess` as cPanel's own blocks
   (the PHP 8.3 handler) followed by Laravel's rules, moves shadowing `.env.<env>` files aside, rebuilds
   the storage skeleton, `migrate --force`, `portal:sync-permissions`, `optimize`, `storage:link`, `queue:restart`, folder
   permissions, then brings the site up.
6. `scripts/deploy/smoke.sh` checks `/up`, the home page and the `X-Robots-Tag: noindex` header on the
   live URL. A failure runs `scripts/deploy/rollback.sh` (code only) and fails the job.

These scripts were rehearsed against a simulated site folder (first deploy, second deploy, rollback,
protected files, cPanel handler block, shadow env file) before the first real deploy.

## Manual operations

Run a deploy without a new commit: GitHub → Actions → Deploy → Run workflow.

Roll back by hand (code only; migrations are not reversed, so they must be backwards compatible):

```
ssh <account>@<host>
APP_DIR=$HOME/hararipp.orcamind.dev PHP_BIN=/opt/alt/php83/usr/bin/php \
  bash $HOME/hararipp.orcamind.dev/scripts/deploy/rollback.sh
```

Logs: `~/hararipp.orcamind.dev/storage/logs/laravel-*.log` (daily, 14 days).

## Known behaviour

- Many SSH logins from one address in a few minutes can make the host's firewall drop that address for
  a while (seen from the owner's PC on 2026-10-18). Deploys come from GitHub's runners and are not
  affected; wait or ask the host to lift the block.
- Changing the domain's PHP version in cPanel rewrites the handler block in `public/.htaccess`; the next
  deploy keeps whatever cPanel wrote.
- Integration API keys (admin → Settings → Integrations) are encrypted with the server's `APP_KEY`.
  Never change `APP_KEY` in the server `.env` without re-entering every key afterwards; until then the
  integrations page shows them as not configured. PHP's `calendar` extension is required (Ethiopian
  dates) and is part of alt-php83 on the host.

## Media library (ADR-0009)

- Originals: `~/hararipp.orcamind.dev/storage/app/media/library/<uuid>/` (private, mode 750).
  Copies: `storage/app/public/library/<uuid>/conversions/`, served at `/storage/...`.
- Every deploy rewrites `storage/app/public/.htaccess` (no script files in public uploads) and
  `storage/app/public/smoke.txt` (checked by the smoke test).
- Copies are made by the queue within a minute of upload (cron, `portal:work-queues`). Remake all copies
  after changing presets: `php artisan media-library:regenerate --only-missing` (or without the flag to
  remake everything).
- PHP limits for uploads come from the domain's PHP settings in cPanel (MultiPHP INI Editor):
  `upload_max_filesize` and `post_max_size` must be at least the largest upload (50 MB for video).

## Caching, headers and audits (ADR-0010)

- Public pages send `Cache-Control: public, max-age=0, s-maxage=600` when they set no cookie. Cloudflare
  caches HTML only with a Cache Rule: in the Cloudflare dashboard for orcamind.dev, add a rule for
  hostname `hararipp.orcamind.dev` and paths starting with `/en`, `/am`, `/om`, `/har`, set "Eligible for
  cache" and "Respect origin" for Edge TTL. Never include `/admin` or `/livewire`. After publishing urgent
  changes, purge the cache for the host.
- Security headers come from the application on every PHP response; static files are served by
  LiteSpeed with the caching rule in `public/.htaccess`.
- The Lighthouse workflow audits the live `/en` and `/am` after each deploy; reports are kept 14 days as
  workflow artifacts. Run it by hand from GitHub → Actions → Lighthouse → Run workflow.
- Fonts live in `public/fonts/portal/` and are rebuilt with `python scripts/fonts/build_fonts.py`
  (needs fontTools and brotli) after updating the font packages; bump `VERSION` in the script.

## Search engines and sharing (ADR-0015)

- `/robots.txt` and the sitemaps are made by the application, not files in `public/`. Outside
  `APP_ENV=production` robots.txt refuses every crawler, so staging stays out of search results.
- Sitemaps are cached for an hour; `php artisan cache:clear` rebuilds them at once.
- Generated share images are kept in `storage/app/public/og/` (outside the deploy, so they survive
  releases). Deleting the folder is safe: images are made again on the next visit. The fonts used
  are in `resources/fonts/og/` (rebuild with `python scripts/fonts/build_og_fonts.py`).
- Redirects are checked only when a page is not found, so they never slow down normal pages.

## Site search (ADR-0020)

- Search reads the `search_text` column of each translation; it is rebuilt on every save. After a
  change to the matching rules, run `php artisan portal:search-reindex` once.
- Moving to Meilisearch later: set `SCOUT_DRIVER=meilisearch`, `MEILISEARCH_HOST` and
  `MEILISEARCH_KEY` in `.env`, `composer require meilisearch/meilisearch-php`, then
  `php artisan scout:import` for each of the five translation models.

## Content import (ADR-0022)

- `content:import` runs every 30 minutes from the scheduler and only adds drafts. Run it by hand with
  `php artisan content:import`; on the first run, `--pages=3` reads about sixty messages back.
- Problems (Telegram unreachable, changed page layout) show on Administration → Content sources in
  "Last problem"; the next run tries again.
- Imported photos go through the normal media pipeline; their copies are made by the queue.
