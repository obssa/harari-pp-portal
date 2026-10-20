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
| 5.4 | Performance and caching: Cloudflare cache purge on publish (when an API token is saved), cache headers reviewed per page type, the Lighthouse budget extended to listing, office and document pages | |
| 5.5 | Analytics without cookies: server-side page view counts per page and language (no personal data, no third-party script), daily rollups, the dashboard's traffic widgets and a report builder with scheduled delivery by email | |
| 5.6 | Governance: a privacy area for administrators (find and export or erase what the portal holds about a person: registrations, tickets), retention rules per data set shown and enforced, the audit-log retention | |
| 5.7 | Accessibility: an automated axe check of the main pages in CI, keyboard and screen-reader pass with fixes, the accessibility statement | |
| 5.8 | Browser smoke tests for publish, search, forms and the language switch (Pest browser testing in CI) | |
| 5.9 | Legal pages: privacy policy, terms of use and accessibility statement as pages in four languages, marked "draft for legal review" until the branch office approves them | |
| 5.10 | Launch: admin manual with screenshots (en, am), editor training notes, `docs/launch-report.md` walking the acceptance checklist, go-live runbook for the production host | |

## Open questions (do not block the build; defaults in brackets)

1. Production hosting and domain (PLAN.md §14) [staging stays on the shared host; the runbook covers a
   move].
2. Off-site backup store (an S3-compatible bucket) [server-disk backups with rotation only].
3. Sentry or another error tracker [errors stay in the server log and the admin health page].
4. Cloudflare API token for cache purges [pages expire by their cache headers].
5. Who reviews the Amharic and Afaan Oromo strings and who translates Harari [`docs/translations-review.md`].
6. Legal texts and the privacy officer's contact [draft pages marked for legal review].
7. Editor training dates [training notes and the manual].
