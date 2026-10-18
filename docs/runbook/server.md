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
| 3 | PHP extensions | web and CLI checked: pdo_mysql, intl, mbstring, gd (WebP/AVIF), zip, bcmath, exif, sodium, fileinfo, dom, curl, openssl all present | 2026-10-18 |
| 4 | Database + user | `uapi Mysql create_database / create_user / set_privileges_on_database`, random 40-character password generated on the server | 2026-10-18 |
| 5 | Server `.env` | written on the server from the `.env.example` template, own `APP_KEY`, mode 600; login to the database verified | 2026-10-18 |
| 6 | Deploy key | generated on the owner's PC, public half appended to `~/.ssh/authorized_keys` with `restrict` (file backed up first), private half in the GitHub secret `DEPLOY_SSH_KEY` | 2026-10-18 |
| 7 | Pinned host key | `ssh-keyscan` output compared with the owner's known_hosts, stored as `DEPLOY_KNOWN_HOSTS` | 2026-10-18 |
| 8 | GitHub secrets and variables | secrets `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_PORT`, `DEPLOY_SSH_KEY`, `DEPLOY_KNOWN_HOSTS`; variables `DEPLOY_PATH`, `DEPLOY_PHP_BIN`, `DEPLOY_URL`, `DEPLOY_ENABLED=true` | 2026-10-18 |
| 9 | Cron | two lines appended (crontab backed up to `~/backups/` first; other sites' lines untouched) | 2026-10-18 |
| 10 | Cloudflare | DNS record for `hararipp` proxied; site answers over HTTPS | 2026-10-18 |

### Cron entries

```
* * * * * cd $HOME/hararipp.orcamind.dev && /opt/alt/php83/usr/bin/php artisan schedule:run >> /dev/null 2>&1
* * * * * cd $HOME/hararipp.orcamind.dev && /opt/alt/php83/usr/bin/php artisan queue:work --stop-when-empty --max-time=50 --tries=3 >> /dev/null 2>&1
```

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
   the storage skeleton, `migrate --force`, `optimize`, `storage:link`, `queue:restart`, folder
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
