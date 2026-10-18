# Changelog

All notable changes. Dates are Gregorian.

## Unreleased

### Phase 1, step 1.2 — admin panel, roles, two-factor login, audit log (2026-10-18)

- Filament 5 admin panel at `/admin` with sign-in, password reset, profile and the party colour.
- Two-factor login (authenticator app with recovery codes) required for Super Admin, Admin and Publisher;
  optional for other staff.
- 92 permissions in a registry, the 13 roles of PLAN.md §8 with their default grants, and role scopes
  (office, league, language). `portal:sync-permissions` runs on every deploy and keeps Super Admin edits.
- Deny-by-default policies and Filament strict authorization; Admins cannot touch Super Admins, nobody can
  delete themselves or the last Super Admin, accounts are never purged.
- Users screen: invite by email or temporary password, roles and scopes, suspend/reactivate, send sign-in
  link, reset two-factor login, soft delete and restore. Roles screen with the role × permission matrix.
  Read-only audit log with before/after values.
- Audit trail: account and role changes with diffs (secrets excluded), role and scope grants, sign-ins,
  sign-outs, failed sign-ins (email only), every refused (403) request, with IP and browser. Entries kept
  two years. Role or scope changes and suspensions sign the person out everywhere.
- Commands: `portal:create-super-admin`, `portal:sync-permissions`, `portal:demo-accounts` (staging only).
- Admin and permission labels in English, Amharic and Afaan Oromo; Harari keys present and empty.

### Phase 1, step 1.1 — repository, CI/CD, holding page (2026-10-18)

- Laravel 13.11 skeleton on PHP 8.3 with Pest 4, Larastan (level 6) and Pint.
- Test-only Docker setup (`compose.test.yml`, `docker/php.Dockerfile`) and `scripts/task.py`; the site is
  never served locally.
- Tests run on MySQL 8 (`harari_test`); coverage 100% with an 80% minimum enforced in CI.
- CI workflow: lint (Pint, Larastan, Composer audit and validation), tests with coverage, asset build
  with size budget and npm audit.
- Deploy workflow to https://hararipp.orcamind.dev: runner-built release, maintenance mode, snapshot,
  rsync with protected paths, migrate and cache, smoke test, automatic rollback. No bot commits.
- Holding page in English, Amharic and Afaan Oromo; every non-production environment sends
  `X-Robots-Tag: noindex, nofollow`.
- First UI strings in `lang/{en,am,om,har}.json` with a consistency test.
- Docs: `docs/PLAN.md` (spec), `docs/DECISIONS.md` (ADR-0001 to 0004), Phase 0/1 plan, server runbook,
  translations review list.
- Staging provisioned on cPanel: document root `~/hararipp.orcamind.dev/public`, PHP 8.3 (CloudLinux
  alt-php83) for this domain only, MySQL database, server `.env`, cron, restricted deploy key, pinned
  host key. The deploy keeps cPanel's PHP handler in `public/.htaccess`.
- Repository made public (server and account names removed from the docs and history first).
