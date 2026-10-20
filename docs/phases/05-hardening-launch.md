# Phase 5 plan — Hardening and launch

Written 2026-10-20 from `docs/PLAN.md` §7 (dashboard, notifications, automation, governance), §11
(performance, security, reliability, accessibility) and §13, after the Phase 4 report
(`docs/phases/04-social-translation.md`). Each step ends with tests green, CI green, a staging deploy and a
hand-off, as before.

**Gate (PLAN.md §13):** the launch acceptance checklist is 100% and the site goes live. Several items depend
on the branch office (see "Open questions"); they are tracked in `docs/launch-report.md`.

## Steps

| Step | Content | Status |
|---|---|---|
| 5.1 | Notifications centre: an in-app inbox for staff (new tickets, registrations, posts to review, import results, failing sources) with read state, and per-person choices of email or in-app per kind of notice | done |
| 5.2 | Health and reliability: a health page for administrators (database, queue backlog and failures, scheduler heartbeat, disk and media quota, mail settings, last backup); `/up` stays the simple check; database and media backups to the server disk with rotation, and to an off-site store once one is configured; a documented restore drill | done; the off-site store and the drill database on staging wait for the owner |
| 5.3 | Security pass: `composer audit` and `npm audit` in CI, a review of rate limits, sessions, upload handling and headers against PLAN.md §11, the token-expiry alert for social sources, error reporting to Sentry once a DSN is saved | done; the Sentry DSN, Cloudflare's TLS setting and a penetration test wait for the owner |
| 5.4 | Performance and caching: Cloudflare cache purge on publish (when an API token is saved), cache headers reviewed per page type, the Lighthouse budget extended to listing, office and document pages | done; the Cloudflare Cache Rule and API token wait for the owner |
| 5.5 | Analytics without cookies: server-side page view counts per page and language (no personal data, no third-party script), daily rollups, the dashboard's traffic widgets and a report builder with scheduled delivery by email | done |
| 5.6 | Governance: a privacy area for administrators (find and export or erase what the portal holds about a person: registrations, tickets), retention rules per data set shown and enforced, the audit-log retention | done |
| 5.7 | Accessibility: an automated axe check of the main pages in CI, keyboard and screen-reader pass with fixes, the accessibility statement | done; the screen-reader pass in Amharic, Afaan Oromo and Harari needs testers who read them (`docs/accessibility.md`) |
| 5.8 | Browser smoke tests for publish, search, forms and the language switch (Pest browser testing in CI) | done |
| 5.9 | Legal pages: privacy policy, terms of use and accessibility statement as pages in four languages, marked "draft for legal review" until the branch office approves them | done; approval and Harari text wait for the branch office |
| 5.10 | Launch: admin manual with screenshots (en, am), editor training notes, `docs/launch-report.md` walking the acceptance checklist, go-live runbook for the production host | done; the training session waits for the branch office |

## Open questions (do not block the build; defaults in brackets)

1. Production hosting and domain (PLAN.md §14) [staging stays on the shared host; the runbook covers a
   move].
2. Off-site backup store (an S3-compatible bucket) [server-disk backups with rotation only].
3. Sentry or another error tracker [errors stay in the server log and the admin health page].
4. Cloudflare API token for cache purges [pages expire by their cache headers].
5. Who reviews the Amharic and Afaan Oromo strings and who translates Harari [`docs/translations-review.md`].
6. Legal texts and the privacy officer's contact [draft pages marked for legal review].
7. Editor training dates [training notes and the manual].

## Report (2026-10-20)

Staging: https://hararipp.orcamind.dev. Tag: `v0.5.0`.

### Gate (PLAN.md §13, Phase 5)

The launch acceptance checklist is walked item by item in `docs/launch-report.md`: 6 of 16 items met,
10 partly met. What remains for each is in the hands of the branch office (accounts, people, approvals)
or of the production host, as listed there; the steps to take on the day are in `docs/runbook/go-live.md`.
The site is therefore ready for launch from the development side, and goes live once those inputs arrive.

### What was built

| Step | Content | Commit |
|---|---|---|
| 5.1 | Notifications centre: own notices, read state, in-app or email per kind | 1fa6d01 |
| 5.2 | System health page, nightly encrypted backups, backup before migrations, restore and drill | af9228d |
| 5.3 | Security review, sign-in alerts, admin allow-list, Facebook token expiry, Sentry, weekly audit | a1fc623 |
| 5.4 | Cloudflare purge of this host on public changes, cache headers per page type, more Lighthouse pages | 1d17a41 |
| 5.5 | Cookieless page view counts, dashboard widgets, traffic reports with CSV and scheduled email | 7a35148 |
| 5.6 | Privacy requests (find, export, erase) and enforced retention | 811d321 |
| 5.7–5.9 | axe checks and browser smoke tests in CI, accessibility audit, draft legal pages | merged from `browser-tests` |
| 5.10 | Manual screenshots (English and Amharic), training notes, launch report, go-live runbook; dashboard widget widths fixed | this commit |

Decisions are recorded as ADR-0036 to ADR-0043 in `docs/DECISIONS.md`. A bug found while taking the
manual's screenshots was fixed: dashboard widgets ignored the width chosen in the layout (Filament's column
span cannot be passed in; widgets now take a public `span`).

### Quality at the end of the phase

| Measure | Value |
|---|---|
| Tests | 795 Pest tests in the main suites and 6 browser tests, all passing |
| Line coverage | 89.0% overall; `app/Domain` 88.5%, `app/Services` 91.9% (CI minimum 80% each) |
| Static analysis | Larastan level 6, no errors |
| Accessibility | axe on sixteen public pages: no serious or critical issues |
| Lighthouse | homepages, a story, an event, a document, the news and documents lists and the office directory ≥ 0.9 on mobile |
| Translations | English, Amharic and Afaan Oromo complete; Harari keys present and empty |

### For administrators on staging

1. **Administration → Roles**: the new permission of this phase (`privacy.manage`) went to its default roles
   (Super Admin, Admin) automatically; change it there if the branch office wants others to handle privacy
   requests.
2. **System health** shows mail as "Needs a look" until an SMTP account is saved in the server `.env`.
3. The legal pages were created with `php artisan portal:legal-pages`; remove the "Draft for legal review"
   notice from each once the branch office approves the text.
4. Keep `BACKUP_ARCHIVE_PASSWORD` from the server `.env` in the owner's password manager.
