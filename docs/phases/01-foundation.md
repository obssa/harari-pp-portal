# Phase 1 report — Foundation

Dates: 2026-10-18 to 2026-10-19. Staging: https://hararipp.orcamind.dev. Tag: `v0.1.0-foundation`.

## Gate (PLAN.md §13, Phase 1)

| Criterion | Status | Evidence |
|---|---|---|
| Admin login with 2FA | met | `/admin` sign-in; authenticator app required for Super Admin, Admin, Publisher (ADR-0006); first Super Admin created on staging 2026-10-19 |
| Roles seeded | met | 13 roles and the permission registry synced on every deploy (`portal:sync-permissions`, ADR-0005) |
| Language switch on a hello page in 4 languages | met | `/en`, `/am`, `/om`, `/har` with switcher, hreflang, remembered choice, Harari → Amharic fallback; checked by the post-deploy smoke test |
| CI green | met | Lint (Pint, Larastan level 6, translations, hard-coded text, Composer audit), tests with coverage, asset budget, deploy, Lighthouse |

## What was built

| Step | Content | Commit |
|---|---|---|
| 1.1 | Repository, test container, CI, deploy pipeline with rollback, holding page | b485fd9 |
| 1.2 | Filament admin, two-factor login, roles and scoped permissions, audit log, users and roles screens | 6ad758b |
| 1.3 | Language routing, fallback chain, admin-editable UI strings, `lang:missing`, `lang:export` | 72e98ac |
| 1.4 | Site and integration settings (encrypted keys), sites, Ethiopian calendar | 9cca3eb |
| 1.5 | Media library: safe uploads, EXIF removal, SVG sanitising, focal-point WebP/AVIF crops, folders, usage tracking | 98afbf8 |
| 1.6 | Design tokens, fonts, public layout, components, error pages, security headers, edge caching, styleguide, Lighthouse | 1ad8d6c |
| 1.7 | Domain coverage gate, local staff avatars, staff manual (en, am), this report | tagged `v0.1.0-foundation` |

Decisions that differ from PLAN.md are recorded as ADR-0001 to ADR-0010 in `docs/DECISIONS.md`
(Laravel 13 and Filament 5, cPanel drivers, rsync deploys, MySQL tests, own permission registry instead
of filament-shield, two-factor scope, translation storage, settings and sites, media library, design and
security headers).

## Quality at the end of the phase

| Measure | Value |
|---|---|
| Tests | 413 Pest tests, all passing |
| Line coverage, whole app | 96.2% (CI minimum 80%) |
| Line coverage, app/Domain / app/Services / app/Settings | 92.8% / 98.1% / 95.0% (CI minimum 80% each) |
| Static analysis | Larastan level 6, no errors |
| Translations | English, Amharic and Afaan Oromo complete (335 keys); Harari keys present and empty |
| Public CSS / JS | about 6 KB gzipped / none |
| Lighthouse (mobile, live staging) | `/en` and `/am` pass performance, accessibility and best practices ≥ 90, HTML ≤ 60 KB, JS ≤ 80 KB, CLS ≤ 0.1 (median of 3 runs; scores in each Lighthouse run's summary) |

## Demo script (staging)

1. Open https://hararipp.orcamind.dev/ in a browser set to Afaan Oromo: it opens `/om`. Switch to ሐረሪ:
   the page shows Amharic text where Harari is not yet translated; reload `/` and the choice is kept.
2. Point out today's date in both calendars at the top and the footer year (2019 (2026)).
3. Open `/en/anything`: the 404 page is in English; `/am/anything` in Amharic.
4. Open `/admin`, sign in as Super Admin, enter the authenticator code.
5. Users: create a sample Editor with a temporary password; show roles, scopes and the audit entry.
6. Site settings: add a contact email and a Telegram link; show them in the public footer.
7. Media library: upload a phone photo and a PDF, add alt text in three languages, click a focal point;
   upload a file renamed from `.php` to `.jpg` and show the refusal message.
8. Audit log: show the before/after entries of steps 5–7.
9. `/styleguide`: tokens, buttons, badges, cards, date tiles; `?lang=am` for Ethiopic typography.

## Open items carried forward

- **Inputs from the branch** (PLAN.md §14, `docs/content/sources.md`): official names in all four
  languages and the logo files; verified contact details; current leadership; confirmation of the
  official social accounts; a Facebook Page token; permission to republish the communication office's
  material.
- **Native review** of Amharic and Afaan Oromo strings listed in `docs/translations-review.md`, and the
  Amharic manual.
- **Harari** strings are empty and fall back to Amharic until translators fill them (Phase 4 tooling).
- **Super Admin account** on staging still uses the sample email it was created with; change it under
  Profile.
- **Cloudflare cache rule** for public HTML is optional and documented in the runbook.

## Next: Phase 2 — Content core

Posts with translations and the block editor, revisions and scheduled publishing, categories and tags,
leaders, offices, leagues, menus, homepage builder, dashboard v1, editorial workflow, search, SEO
(sitemaps, JSON-LD, OG images), RSS, and the public templates. Imports from the branch's public Telegram
channel follow the rules in `docs/content/sources.md`. Gate: an editor publishes a four-language post end
to end; Lighthouse ≥ 90 on the homepage and a post.
