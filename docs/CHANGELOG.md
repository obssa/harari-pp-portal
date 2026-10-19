# Changelog

All notable changes. Dates are Gregorian.

## Unreleased

### Phase 2, step 2.1 — translatable content foundation (2026-10-19)

- Translation-table pattern for content (one row per language, fallback chain, per-language slugs that
  never change with the title), ULIDs, site ownership and editor tracking (ADR-0011).
- Readable Latin slugs for Ethiopic titles (አዲስ አበባ → `adis-abeba`), including Ethiopic numerals.
- Admin → Content → Categories (nested, ordered) and Tags, each with a tab per language; editors and
  publishers manage them (`taxonomies.manage`), authors can see them.
- Phase 2 plan in `docs/phases/02-content-core.md`; Phase 1 report completed with Lighthouse scores.

## v0.1.0-foundation — Phase 1 complete (2026-10-19)

Gate of PLAN.md §13 Phase 1 met; report in `docs/phases/01-foundation.md`.

### Phase 1, step 1.7 — phase gate (2026-10-19)

- CI checks line coverage of `app/Domain`, `app/Services` and `app/Settings` separately (≥ 80% each),
  as does `python scripts/task.py coverage`.
- Staff avatars are drawn locally from initials instead of being fetched from ui-avatars.com (no staff
  names leave the server; allowed by the admin CSP).
- Lighthouse reports are kept as workflow artifacts and the scores appear in the run summary.
- Staff manual chapter 1, "Getting started", in English and Amharic (`docs/manual/`).
- Phase 1 report with the gate evidence, a demo script and the open items.

### Phase 1, step 1.6 — design system, public layout, security headers (2026-10-19)

- Design tokens: party blue scale, Harar terracotta accent, semantic and dark-mode colours, headline
  sizes tuned for Ethiopic.
- Self-hosted Inter and Noto Sans Ethiopic (subset to the Ethiopic block, 41% smaller), preloaded per
  language.
- New public layout: skip link, today in both calendars, language switcher, header and main menu,
  footer with contact details and social links from the site settings.
- Components: button, badge, card, date tile (Ethiopian day and month with the Gregorian date).
- Translated error pages (403, 404, 419, 429, 500); the maintenance page shows all four languages.
- Security headers on every response: strict Content-Security-Policy with nonces on public pages,
  HSTS, frame, referrer, permissions and opener policies.
- Public pages carry no session cookie and tell Cloudflare it may cache them for 10 minutes; hashed
  assets and fonts are cached for a year.
- `/styleguide` (not in production), a hard-coded text check in CI, and a Lighthouse audit of the live
  site after every deploy.
- Content sources for Phase 2 recorded in `docs/content/sources.md`.

### Phase 1, step 1.5 — media library (2026-10-19)

- Admin → Content → Media library: upload up to 20 files at once (photos, SVG, documents, video,
  audio) into folders, search, filter by type, folder, tag, "missing alt text", "not used anywhere"
  and "uploaded by me", move and tag in bulk, storage used against the quota.
- Every upload is checked by its content, not its name; PHP, HTML, macro documents, oversized or
  damaged images are refused with a reason in the uploader's language.
- Photos are re-encoded on upload (upright, at most 3840 px, EXIF and location removed); SVGs are
  sanitised. Originals are stored outside the web root and served with safe headers.
- Cropped WebP copies (AVIF where the server supports it) in 16:9, 1:1 and 3:2 around a focal point
  chosen by clicking the photo; moving the point remakes the crops.
- Alt text per language (Harari falls back to Amharic), credit and tags per file; usage tracking keeps
  files in use from being deleted; every change is in the audit log.
- Media folders screen (nested folders, unique names, only empty folders can be deleted).
- `<x-picture>` component for public pages (AVIF/WebP srcsets, sizes, focal point, translated alt).
- `php artisan portal:work-queues` drains all queues (default, media) from cron.
- Deploy: private media folders, a no-scripts rule for public uploads, and a smoke check that uploads
  are served.

### Phase 1, step 1.4 — settings, sites, Ethiopian calendar (2026-10-19)

- Admin → Settings → Site: default language, which calendar leads and whether the other is shown,
  contact email, phone and hotline, address and office hours per language, social links. Admins can
  read it; only Super Admins change it.
- Admin → Settings → Integrations (Super Admin only): SMS driver and sender, and API keys for SMS,
  Google Translate, the LLM provider, Meta, YouTube, Telegram and Turnstile. Keys are encrypted at rest,
  never shown again, kept when the field is left blank, and removable.
- Settings changes appear in the audit log with before and after values; keys show only that they
  changed.
- The default language of `/` now comes from the site settings.
- A `sites` table with the default site; settings are stored per site, ready for sub-sites later.
- Ethiopian calendar service: conversion both ways in Addis Ababa time, month names and date formats in
  English, Amharic and Afaan Oromo, dual dates in the order chosen in the settings.
- The public top bar shows today's date in both calendars; admin audit log and user list show dual
  dates.

### Phase 1, step 1.3 — languages: routing, fallback, translation tooling (2026-10-19)

- Public pages under `/en`, `/am`, `/om`, `/har`; `/` picks the language from the remembered choice,
  then the browser, then the default (Amharic, configurable). Unknown languages return 404.
- hreflang links for all four languages plus `x-default`, canonical URLs, and a language switcher with
  each language named in its own script; the choice is remembered for a year.
- Fallback chain har → am → en, om → en for every string (including plurals and framework messages);
  empty Harari values show Amharic instead of raw keys.
- Strings edited in the admin (`ui_translations`) override the shipped files, survive deploys, are
  audited, and refresh immediately.
- `php artisan lang:missing` (now in CI) and `php artisan lang:export`.
- Amharic and Afaan Oromo framework messages (validation, sign-in, passwords, pagination) from
  Laravel-Lang (MIT), pending native review.
- The admin panel follows each staff member's preferred language.
- Fixed: Laravel's own English framework messages had disappeared behind the translation loader.

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
