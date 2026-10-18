# Phase 0/1 plan — confirmed stack, layout, data model, routes, admin resources

Written 2026-10-18 from `docs/PLAN.md`, adjusted by `docs/DECISIONS.md`.

## Confirmed stack

| Layer | Choice | Note |
|---|---|---|
| Runtime | PHP 8.3, Laravel 13 | ADR-0001 |
| Admin | Filament 5 (Livewire 4) + filament-shield | ADR-0001 |
| Public front end | Blade + Livewire 4 + Alpine, Tailwind 4, Vite 8 | server-rendered |
| Database | MySQL 8, utf8mb4_0900_ai_ci | tests on MySQL too (ADR-0004) |
| Cache / sessions / queue | database drivers on staging, Redis later | ADR-0002 |
| Search | Scout database driver on staging, Meilisearch later | ADR-0002 |
| Hosting | cPanel staging at hararipp.orcamind.dev, deployed from GitHub Actions | ADR-0003 |
| Tests | Pest 4, Larastan 3 (level 6, rising), Pint, coverage ≥ 80% enforced in CI | |

## Phase 1 steps (each ends with CI green, a staging deploy and a hand-off)

| Step | Scope | Status |
|---|---|---|
| 1.1 | Repo, test container, CI, deploy pipeline, holding page, first tests, docs | done 2026-10-18: live at hararipp.orcamind.dev |
| 1.2 | Filament panel, auth + required 2FA for privileged roles, RBAC with scopes, audit log, user seeding | |
| 1.3 | Locale routing `/{en,am,om,har}`, cookie/Accept-Language detection, fallback chain, DB-backed UI strings, `lang:missing`, `lang:export` | |
| 1.4 | Settings (encrypted integration keys), `sites` table, Ethiopian calendar service + Blade component | |
| 1.5 | Media library (WebP/AVIF, EXIF strip, SVG sanitising, alt per locale) | |
| 1.6 | Design tokens, self-hosted fonts, public layout, error pages, security headers + CSP nonces, styleguide, Lighthouse CI | |
| 1.7 | Coverage gate on domain code, manual chapter, phase report, tag `v0.1.0-foundation` | |

## Folder layout

```
app/
  Domain/{Content,Media,People,Social,Translation,Events,Documents}/{Models,Policies,Actions,Enums,Observers}
  Filament/{Resources,Pages,Widgets}
  Http/{Controllers/Public,Middleware}
  Livewire/
  Services/{Calendar,Translation,Sms,Social}
  Support/
  Console/Commands/
lang/{en,am,om,har}.json + lang/{en,am,om,har}/*.php
resources/{css/tokens.css,css/app.css,views/{components,layouts,pages,errors}}
scripts/{task.py,deploy/*}
docs/{PLAN.md,DECISIONS.md,CHANGELOG.md,phases/,runbook/,manual/,reviews/}
```

## Migrations planned for Phase 1

| Migration | Step |
|---|---|
| users, password_reset_tokens, sessions (skeleton) | 1.1 |
| cache, cache_locks, jobs, job_batches, failed_jobs (skeleton; used by the database drivers) | 1.1 |
| users: `locale`, `last_login_at`, MFA columns from Filament | 1.2 |
| permission tables (spatie) + `scope_type`, `scope_id` on `model_has_roles` | 1.2 |
| activity_log (spatie) | 1.2 |
| ui_translations, translation_imports, glossary_terms | 1.3 |
| settings (spatie/laravel-settings), sites | 1.4 |
| media (spatie), media_folders | 1.5 |

## Routes planned for Phase 1

| Route | Purpose | Step |
|---|---|---|
| `GET /up` | health check used by the deploy smoke test | 1.1 |
| `GET /` | holding page (1.1), then redirect to the visitor's locale (1.3) | 1.1 / 1.3 |
| `GET /{locale}` | home placeholder per locale, `locale ∈ en, am, om, har` | 1.3 |
| `/admin/*` | Filament panel | 1.2 |
| `GET /styleguide` | component styleguide, non-production only | 1.6 |

## Filament resources planned for Phase 1

Users, Roles (shield), Role × permission matrix page, Activity log (read-only), Settings page,
Media library, Media folders.

## Translation key naming scheme

Flat dotted keys in `lang/*.json`: `<area>.<thing>[.<detail>]`, lower snake case.

| Area | Examples |
|---|---|
| `site.*` | `site.name`, `site.office` |
| `nav.*` | `nav.home`, `nav.news` |
| `home.*`, `post.*`, `office.*`, … | page or content-type specific |
| `forms.<form>.*` | `forms.membership.step1.title` |
| `footer.*` | `footer.newsletter.placeholder` |
| `admin.*` | admin-only labels |
| `holding.*` | the temporary holding page |

Rules: add every key to all four files in the same commit (`har` may be `""`); placeholders use
`:name`; plurals use Laravel's `{1} …|[2,*] …`.

## Questions that block work

These are PLAN.md §14 inputs. None blocks Phase 1; they block launch quality.

1. Official branch name in all four languages, logo SVG, national brand guide (colours, fonts).
2. Harari translators and the script decision (Ethiopic recommended).
3. Amharic and Afaan Oromo reviewers for shipped UI strings (`docs/translations-review.md`).
4. Production hosting decision (in-country vs cloud) and production domain.
5. SMS gateway, transactional mail provider, social API accounts.
