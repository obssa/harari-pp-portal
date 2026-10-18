# Architecture decision records

Each record: context, decision, consequences. Newest last. These records win over `docs/PLAN.md`.

## ADR-0001 — Laravel 13 + Filament 5 instead of Laravel 12 + Filament 4 (2026-10-18)

**Context.** PLAN.md names Laravel 12, Filament 4 and Livewire 3. On the build date the supported lines are
Laravel 13 (PHP 8.3+), Filament 5 (stable 5.10.x, built on Livewire 4) and Pest 4. Every package the plan
names has a release that supports Laravel 13: bezhansalleh/filament-shield 4.3 (filament ^4|^5),
spatie/laravel-translatable 6.14, spatie/laravel-translation-loader 2.8, spatie/laravel-permission,
spatie/laravel-activitylog, andegna/calender 2.4 (php >= 7.3).

**Decision.** Laravel 13 on PHP 8.3, Filament 5, Livewire 4, Tailwind 4, Pest 4, Larastan 3. Composer's
platform is pinned to PHP 8.3 so the lock file always installs on the server.

**Consequences.** No framework upgrade before launch. If the hosting PHP ever drops to 8.2, Laravel 12 +
Filament 5 is the fallback (both support 8.2).

## ADR-0002 — cPanel-compatible drivers for staging (2026-10-18)

**Context.** The owner chose to run staging at https://hararipp.orcamind.dev on an existing shared cPanel
account (LiteSpeed web server, Cloudflare in front). Shared cPanel offers PHP, MySQL, cron and SSH, but no
Redis, Meilisearch, Horizon, Supervisor or Docker. The plan's production stack needs those.

**Decision.** Every infrastructure dependency is a driver chosen in `.env`, never hard-coded:

| Concern | Staging (cPanel) | Later VPS / production option |
|---|---|---|
| Cache | `CACHE_STORE=database` | `redis` |
| Sessions | `SESSION_DRIVER=database` | `redis` |
| Queue | `QUEUE_CONNECTION=database`, drained by cron `queue:work --stop-when-empty` every minute | `redis` + Horizon |
| Search | `SCOUT_DRIVER=database` (MySQL FULLTEXT / LIKE) | `meilisearch` |
| Mail | SMTP on the cPanel mail server | Mailgun / SES |
| Media | `public` disk on the account | S3-compatible (R2/B2/MinIO) |
| Full-page cache | Cloudflare edge + `Cache-Control: s-maxage` | same |
| PHP | CloudLinux `alt-php83` for this domain only (MultiPHP); the cPanel EasyApache builds stop at 8.2 | distribution PHP 8.3+ |

Tests run against MySQL 8 with array/sync drivers. Features that need Horizon (dashboard) or Meilisearch
(typo tolerance) degrade gracefully, as PLAN.md §11 already requires.

**Consequences.** No code change is needed to move to a VPS with Redis and Meilisearch; only `.env`.
cPanel stores the per-domain PHP handler in `public/.htaccess`, so the deploy never overwrites that file
and rebuilds it from cPanel's blocks plus Laravel's rules.
Long-running jobs must finish within one cron cycle (`--max-time=50`).

## ADR-0003 — Deploy by rsync from GitHub Actions, no artifact branches (2026-10-18)

**Context.** The owner wants full CI/CD from the GitHub repo to hararipp.orcamind.dev, and to be the sole
author of every commit in the repository. An existing project on the same account publishes bot-authored
artifact branches, which would add non-owner commits.

**Decision.** `.github/workflows/deploy.yml` runs after CI succeeds on `main`. It builds the release on the
runner (`composer install --no-dev`, `npm ci && npm run build`), `rsync`s it over SSH into the site folder
(never touching `.env`, `storage/`, `public/storage`, `bootstrap/cache/`), then runs
`scripts/deploy/release.sh` on the server (maintenance window with an up-trap, migrate, caches, queue
restart, permission fix, previous-release snapshot for rollback) and `scripts/deploy/smoke.sh` against the
live URL. A failed smoke test restores the previous release and fails the job. No commits are created.

**Consequences.** The deploy key lives only in GitHub secrets and the account's `authorized_keys`. The
first install (database, `.env`, cron) is a one-time provisioning step recorded in
`docs/runbook/server.md`.

## ADR-0004 — MySQL for tests, not SQLite (2026-10-18)

**Context.** Ethiopic text, JSON columns, FULLTEXT indexes and `utf8mb4_0900_ai_ci` collation behave
differently in SQLite.

**Decision.** Pest runs against MySQL 8 (`harari_test`) locally in Docker and as a service container in CI.

**Consequences.** Tests need the MySQL container running; `scripts/task.py test` starts it.

## ADR-0005 — Own permission registry and role scopes instead of filament-shield (2026-10-18)

**Context.** PLAN.md lists `bezhansalleh/filament-shield` and asks for fine-grained permissions named like
`posts.publish` and `translations.har.edit`, plus scopes that narrow a role to an office, league or
language. Shield generates `Action:Model` permissions and policy stubs per resource; it cannot express
module-level permissions for screens that do not exist yet, nor scopes.

**Decision.** `App\Domain\Access\Support\PermissionRegistry` is the single list of permissions
(`module.action`, plus `translations.<locale>.edit`), `App\Domain\Access\Enums\SystemRole` holds the 13
roles of PLAN.md §8 with their default grants, and `portal:sync-permissions` (run on every deploy) creates
what is missing without undoing grants a Super Admin changed. Scopes live in a `role_scopes` table
(user, role, scope type, value) rather than extra columns on `model_has_roles`, because that table's
primary key would forbid one role scoped to two offices. Policies are hand-written and deny by default;
the Filament panel runs with `strictAuthorization()`. spatie/laravel-permission 8 does the storage and
checks; Shield is not installed.

**Consequences.** Scope-sensitive checks must call `hasPermissionInScope()` (plain `can()` means
"anywhere"). New modules add their permissions to the registry and their defaults to `SystemRole`.

## ADR-0006 — Two-factor login required only for privileged roles (2026-10-18)

**Context.** PLAN.md §7 requires TOTP two-factor login for Admin and Publisher (we add Super Admin).
Filament 5's built-in requirement is panel-wide.

**Decision.** Filament's app-authenticator MFA (with recovery codes) is enabled for everyone; the
"required" middleware is replaced by `RequireMfaForPrivilegedRoles`, which only redirects users holding a
role in `config('portal.mfa_required_roles')` to the set-up page. Secrets and recovery codes are stored
encrypted. Super Admins are created with `portal:create-super-admin`, which prints a one-time password in
the terminal of whoever runs it (never in CI logs).

**Consequences.** Other staff may opt in from their profile. Losing a phone needs an Admin to run
"Reset two-factor login", which is audited and signs the user out.
