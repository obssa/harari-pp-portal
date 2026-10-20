# Launch report — acceptance checklist (PLAN.md §13)

Status on 2026-10-20, at the end of Phase 5 on staging (https://hararipp.orcamind.dev). "Met" items are
built, tested in CI and checked on staging. "Waiting" items need the branch office's accounts, people or
decisions; the go-live runbook (`docs/runbook/go-live.md`) lists when each is done.

**Summary: 6 of 16 met, 10 partly met. Every part still open waits on the branch office or the production
host, not on development.**

| # | Criterion | Status | Evidence | Still needed (who) |
|---|---|---|---|---|
| 1 | All pages of section 4 render in en/am/om/har with no raw translation keys | met | Every public route renders in four languages in the feature tests (`Phase3GateTest`, locale routing tests); no visible text outside translations (`check-hardcoded-strings.py` in CI); Harari falls back to Amharic, then English, never to a key | — |
| 2 | `lang:missing` 0 for am/om; Harari coverage in the admin | met | CI step "Translations complete for am/om"; Harari coverage on the dashboard's translation widgets and the translations grid | — |
| 3 | Harari JSON round-trip with rollback | met | `TranslationImportTest`; on staging all strings export and re-import as Excel and JSON with no change (Phase 4 report); imports roll back under Translation imports | — |
| 4 | Editorial workflow demonstrated by a non-developer | partly | The workflow and its notices are built and tested (draft → review → translate → schedule → publish); the browser smoke test publishes from the admin | a session with an editor (branch office, `docs/training/editor-training.md`) |
| 5 | Every role tested with a dedicated account; denials 403 and logged | met | Policy tests per role and resource, strict authorization (`StrictAuthorizationTest`), refused requests in the audit log; one sample account per role on staging (`portal:demo-accounts`) | — |
| 6 | Two-step sign-in enforced for Admin/Publisher; audit log diffs | met | Settings → Security switch (ADR-0018) with tests; audit log with before/after values; sign-in alerts (ADR-0038) | switch it on in production (runbook) |
| 7 | Social hub fetches Facebook, Telegram, YouTube; token-expiry alert | partly | Fetchers for all three built and tested; Telegram read on staging; Facebook token expiry checked daily with warnings (ADR-0038) | the branch's official YouTube channel and Facebook page with a page token (branch office) |
| 8 | Lighthouse mobile ≥ 90 on homepage, listing, post, office, event, document | partly | Lighthouse after every deploy on the homepages, a story, an event, a document, the news and documents lists and the office directory, all ≥ 0.9 | one office page once real offices are entered |
| 9 | WCAG 2.2 AA audit (axe + manual keyboard/screen reader), no critical issues | partly | axe on sixteen pages in CI, no serious or critical issues; keyboard skip-link check; audit in `docs/accessibility.md` | screen-reader pass in Amharic, Afaan Oromo and Harari (testers who read them) |
| 10 | hreflang, sitemaps, JSON-LD validated | partly | hreflang alternates, language sitemaps and JSON-LD are in place and tested (`SeoTest`, sitemap tests) | validation in Google's Rich Results test and Search Console on the production domain (go-live) |
| 11 | Backups running; restore drill completed and documented | partly | Nightly encrypted backups on staging since 2026-10-20 and before every migration; restore and drill tested in CI; staging archive read back with its password (`docs/runbook/restore.md`) | a drill database on the host and an off-site store (owner) |
| 12 | Monitoring and error tracking live; alert routing tested | partly | Health checks every five minutes with notices to administrators (tested); newest errors on the health page | Sentry DSN and an uptime monitor on `/up` (owner) |
| 13 | Security headers A+, dependency audit clean, uploads hardened | partly | CSP with nonces, HSTS, frame, referrer and permissions policies on staging; `composer audit` and `npm audit` clean in CI and weekly; uploads re-encoded and sanitised (`docs/security-review.md`) | grade on securityheaders.com at go-live (the site blocks automated checks) |
| 14 | Pest ≥ 80% coverage on domain logic; smoke tests for publish, search, forms, language switch | met | 790 tests; coverage 89.0% overall, `app/Domain` 88.5%, `app/Services` 91.9% (CI gate 80%); browser smoke tests in CI (Pest browser testing instead of Dusk, ADR-0042) | — |
| 15 | Editor training delivered; admin manual with screenshots in English and Amharic | partly | Manual chapters 1–4 in English and Amharic with screenshots of the admin; training plan ready | the training session (branch office, open question 7) |
| 16 | Privacy policy, terms, accessibility statement published in 4 languages | partly | Published in English, Amharic and Afaan Oromo as drafts for legal review, linked in the footer (ADR-0043) | legal approval and the Harari text (branch office) |

## Waiting on the branch office or owner

1. Production host and domain; Cloudflare Cache Rule and an API token for purges.
2. Mail account for outgoing mail (staging still writes mail to the log).
3. Official social media accounts and a Facebook page token.
4. Native-speaker review of Amharic and Afaan Oromo; Harari translations of the interface and the legal pages.
5. Approval of the legal texts and the privacy contact.
6. Sentry (or another error tracker) and an uptime monitor.
7. A drill database and an off-site backup store.
8. Editor training session; screen-reader testers.

## Phase 5 steps

| Step | Content | Commit |
|---|---|---|
| 5.1 | Notifications centre with per-kind choices | 1fa6d01 |
| 5.2 | Health page, nightly backups, restore and drill | af9228d |
| 5.3 | Security pass, sign-in alerts, token expiry, Sentry | a1fc623 |
| 5.4 | Cloudflare purges and cache headers per page type | 1d17a41 |
| 5.5 | Cookieless page view counts and traffic reports | 7a35148 |
| 5.6 | Privacy requests and enforced retention | 811d321 |
| 5.7–5.9 | Accessibility checks, browser smoke tests, draft legal pages | see `git log` |
| 5.10 | Manual screenshots, training notes, this report, go-live runbook | see `git log` |
