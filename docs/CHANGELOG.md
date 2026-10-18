# Changelog

All notable changes. Dates are Gregorian.

## Unreleased

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
