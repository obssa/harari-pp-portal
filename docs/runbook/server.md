# Server runbook — staging (hararipp.orcamind.dev)

Records how the staging server is set up and operated. No secrets or server names live here (the repository is public); they are in
the server `.env`, GitHub secrets, and the owner's password manager.

## Facts

| Item | Value |
|---|---|
| URL | https://hararipp.orcamind.dev (Cloudflare DNS + proxy, LiteSpeed origin) |
| Host | shared cPanel account (host name kept only in the GitHub secret `DEPLOY_HOST` and the owner's records) |
| Site folder | `~/hararipp.orcamind.dev` (cPanel document root: `~/hararipp.orcamind.dev/public`) |
| PHP | 8.3 for this vhost (cPanel MultiPHP Manager); CLI binary recorded in GitHub variable `DEPLOY_PHP_BIN` |
| Database | MySQL, database and user created in cPanel (names in the server `.env`) |
| Drivers | database cache, sessions and queue; Scout database driver (ADR-0002) |
| Rollback snapshot | `~/releases/hararipp/prev.tar.gz` (code only, written before every deploy) |

Status: **not provisioned yet**. Fill in the "Provisioned" column of the checklist below as each item is done.

## One-time provisioning checklist

| # | Step | How | Provisioned |
|---|---|---|---|
| 1 | Subdomain `hararipp.orcamind.dev` with document root `hararipp.orcamind.dev/public` | cPanel → Domains → Create a new domain (uncheck "share document root"), or `uapi SubDomain addsubdomain domain=hararipp rootdomain=orcamind.dev dir=hararipp.orcamind.dev/public` | |
| 2 | PHP 8.3 for that domain | cPanel → MultiPHP Manager, or `uapi LangPHP php_set_vhost_versions vhost=hararipp.orcamind.dev version=ea-php83` | |
| 3 | PHP extensions present | `/opt/cpanel/ea-php83/root/usr/bin/php -m` must list pdo_mysql, intl, mbstring, gd, exif, zip, bcmath, fileinfo, dom | |
| 4 | MySQL database + user with all privileges | cPanel → MySQL Databases (names get the cPanel account prefix, `<account>_`) | |
| 5 | Server `.env` | copy `.env.example` to `~/hararipp.orcamind.dev/.env`, fill DB and mail values, `APP_KEY` from `php artisan key:generate --show` (any machine), `chmod 600 .env` | |
| 6 | Deploy key for GitHub Actions | new ed25519 key used only by this repo; public half appended to `~/.ssh/authorized_keys`; private half stored as GitHub secret `DEPLOY_SSH_KEY` | |
| 7 | Pinned host key | `ssh-keyscan -p <port> <host>` checked against the key already trusted on the owner's PC, stored as secret `DEPLOY_KNOWN_HOSTS` | |
| 8 | GitHub secrets and variables | secrets `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_PORT`, `DEPLOY_SSH_KEY`, `DEPLOY_KNOWN_HOSTS`; variables `DEPLOY_PATH`, `DEPLOY_PHP_BIN`, `DEPLOY_URL`, then `DEPLOY_ENABLED=true` | |
| 9 | Cron (append, never replace the existing crontab) | see below | |
| 10 | Cloudflare | DNS record for `hararipp` proxied; SSL mode Full (strict) once the origin certificate (AutoSSL) is issued | already resolves |

### Cron entries

```
* * * * * cd $HOME/hararipp.orcamind.dev && /opt/cpanel/ea-php83/root/usr/bin/php artisan schedule:run >> /dev/null 2>&1
* * * * * cd $HOME/hararipp.orcamind.dev && /opt/cpanel/ea-php83/root/usr/bin/php artisan queue:work --stop-when-empty --max-time=50 --tries=3 >> /dev/null 2>&1
```

Append them with `(crontab -l; cat new-lines) | crontab -` and make sure the file ends with a newline.
Other sites on the account have their own entries; never overwrite them.

## How a release works

1. A push to `main` runs CI (lint, tests with coverage, asset build).
2. On green, the Deploy workflow builds the release on the runner (`composer install --no-dev`,
   `npm ci && npm run build`) and records the git revision in `REVISION`.
3. `scripts/deploy/pre-release.sh` runs on the server: maintenance mode, snapshot of the current code.
4. `rsync --delete` copies the release. `scripts/deploy/rsync-excludes.txt` lists what is never copied or
   deleted: `.env*`, `storage/`, `public/storage`, `bootstrap/cache/`, and files cPanel creates
   (`.well-known`, `cgi-bin`, `.user.ini`, `php.ini`, `error_log`).
5. `scripts/deploy/release.sh` runs on the server: moves shadowing `.env.<env>` files aside, rebuilds the
   storage skeleton, `migrate --force`, `optimize`, `storage:link`, `queue:restart`, folder permissions,
   then brings the site up.
6. `scripts/deploy/smoke.sh` checks `/up`, the home page and the `X-Robots-Tag: noindex` header on the
   live URL. A failure runs `scripts/deploy/rollback.sh` (code only) and fails the job.

These scripts were rehearsed against a simulated site folder (first deploy, second deploy, rollback,
protected files, shadow env file) before the first real deploy.

## Manual operations

Run a deploy without a new commit: GitHub → Actions → Deploy → Run workflow.

Roll back by hand (code only; migrations are not reversed, so they must be backwards compatible):

```
ssh <account>@<host>
APP_DIR=$HOME/hararipp.orcamind.dev PHP_BIN=/opt/cpanel/ea-php83/root/usr/bin/php \
  bash $HOME/hararipp.orcamind.dev/scripts/deploy/rollback.sh
```

Logs: `~/hararipp.orcamind.dev/storage/logs/laravel-*.log` (daily, 14 days).
