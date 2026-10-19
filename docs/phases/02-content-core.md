# Phase 2 plan — Content core

Written 2026-10-19 from `docs/PLAN.md` §3, §4, §6, §7, §10, §11 and §13, after the Phase 1 gate
(`docs/phases/01-foundation.md`). Each step ends with tests green, CI green, a staging deploy and a
hand-off, as in Phase 1.

**Gate (PLAN.md §13):** an editor publishes a four-language post end to end; Lighthouse mobile ≥ 90 on
the homepage and a post.

## Steps

| Step | Scope | Status |
|---|---|---|
| 2.1 | Translatable content foundation: `HasTranslations` pattern (`thing` + `thing_translations` with locale, title, slug, excerpt, body, SEO fields, per-language status and `published_at`, `machine_translated`, `reviewed_by`), ULIDs, Ethiopic → Latin slug transliteration with override, unique `(locale, slug)`, generic revisions table, categories and tags | done 2026-10-19 (revisions move to 2.2 with posts) |
| 2.2 | Posts (news, press release, statement, speech, announcement): Filament resource with language tabs, block editor (Filament rich editor, JSON) with library images, gallery, video, quote, call-out, table, file and PDF blocks; featured image from the media library (alt text required in en/am/om), offices/leagues/leaders/categories/tags, sticky/featured, SEO panel, word count and reading time, duplicate, bulk actions, revision history with diff and restore, tokenised preview links | done 2026-10-19 (preview links move to 2.4 with the public templates) |
| 2.3 | Editorial workflow: draft → in review → translating → scheduled → published → archived per language, submit/approve/send back with comments, scheduled publishing by the scheduler, notifications (in-app, email), domain events and a webhook skeleton | done 2026-10-19 |
| 2.4 | Public news: listing with filters (type, category, office, league, tag, dates) and pagination, detail page (dual dates, byline, reading time, featured image with credit, body blocks, tags, share bar, related posts, "also available in" chips, print view), RSS and JSON feeds per language | done 2026-10-19 (with signed previews) |
| 2.5 | SEO: titles and descriptions, canonical and hreflang per translation, JSON-LD (Organization/PoliticalParty, NewsArticle, BreadcrumbList, WebSite + SearchAction), Open Graph/Twitter cards with generated OG images, sitemaps per language with an index, robots.txt (closed on staging), 301 redirects on slug change and a redirects manager | done 2026-10-19 (WebSite + SearchAction comes with search in 2.9; pages join the sitemaps in 2.6) |
| 2.6 | Pages and menus: pages with templates (default, landing, full width), nesting and blocks; menu editor for header, footer columns and utility bar with labels per language; the header mega-menu and footer from it | done 2026-10-19 (mega-menu panels with images and the top bar come with the homepage builder in 2.8) |
| 2.7 | Leaders, offices (tree: branch → woreda → kebele, sector offices, basic organisations, map pin), leagues; directory and profile pages; organisational chart | done 2026-10-19 (directory map with every office left for later, ADR-0017) |
| 2.8 | Homepage builder: reorderable, toggleable sections (hero slider, ticker, leadership message, latest news, press releases, counters, core values, leagues spotlight, partner links; event/social/video sections show once their modules exist), limits and curated picks, per-language overrides | done 2026-10-19 (mega-menu panels and the top bar menu stay for later; event/social/video sections arrive with their modules) |
| 2.9 | Search per language (Scout database driver now, Meilisearch-ready): search page and header search, Ethiopic-aware matching, zero-result log | done 2026-10-19 (typo tolerance beyond spelling variants waits for Meilisearch) |
| 2.10 | Dashboard v1: widget registry, per-role default layouts, per-user layouts (add, remove, reorder, resize), global range/language/office filters, first widgets (KPI strip, attention needed, editorial pipeline, translation coverage, activity feed, quick actions, announcements, media storage, queue health, top content) | done 2026-10-19 (top content waits for analytics; site searches shown instead; role defaults follow permissions) |
| 2.11 | Content import from public sources (`docs/content/sources.md`): Telegram public channels of the branch and the communication office into drafts with source links, de-duplication and staff review; no scraping behind logins | done 2026-10-19 |
| 2.12 | Phase 2 gate: end-to-end four-language post on staging, Lighthouse on homepage and post, manual chapter "Publishing a post", phase report | done 2026-10-20 |

## Main decisions to record

- **ADR-0011 Translations.** Own `HasTranslations` concern over translation tables instead of
  spatie/laravel-translatable (JSON columns) or astrotomic/laravel-translatable: the portal needs a
  fallback chain per language (har → am → en, om → en), its own status and publish date per language,
  unique slugs per language and indexed queries per language, which neither package models directly.
- **ADR-0012 Block content.** Filament's rich editor (TipTap) stored as JSON, rendered by our own Blade
  renderer per block type, so the public HTML stays small, accessible and CSP-safe.
- **ADR-0013 Workflow.** A small state machine per translation (no package), with transitions guarded by
  permissions and recorded in the audit log.

## New tables (draft)

`posts`, `post_translations`, `categories`, `category_translations`, `tags`, `tag_translations`,
`post_category`, `post_tag`, `post_office`, `post_league`, `post_leader`, `revisions`, `preview_tokens`,
`workflow_transitions`, `comments` (internal), `webhooks`, `webhook_deliveries`, `pages`,
`page_translations`, `menus`, `menu_items`, `redirects`, `leaders`, `leader_translations`, `offices`,
`office_translations`, `leagues`, `league_translations`, `homepage_sections`, `dashboard_layouts`,
`announcements`, `search_queries`, `content_imports`. Every content table has `site_id`,
`created_by`, `updated_by`, timestamps and soft deletes.

## Public routes (draft)

`/{lang}/news`, `/{lang}/news/{slug}`, `/{lang}/press-releases`, `/{lang}/statements`, `/{lang}/speeches`,
`/{lang}/{page-path}`, `/{lang}/leaders`, `/{lang}/leaders/{slug}`, `/{lang}/offices`,
`/{lang}/offices/{slug}`, `/{lang}/leagues/{slug}`, `/{lang}/search`, `/{lang}/feed.xml`,
`/{lang}/feed.json`, `/sitemap.xml`, `/sitemaps/{lang}.xml`, `/robots.txt`, `/preview/{token}`.

## Open questions (do not block the build; defaults in brackets)

1. Official names, logo files and brand guide (PLAN.md §14) [placeholder wordmark and favicon].
2. Which bodies the leadership directory shows and in which order [Executive Committee, Secretariat,
   office heads; order set in the admin].
3. Office list and hierarchy (woredas, kebeles, sector offices, basic organisations) [entered by staff;
   none invented].
4. Whether press releases need a separate approval from statements [same workflow for all types].
5. Machine translation provider and budget for am/om (Phase 4) [none; manual translation].
6. Permission from the communication office to republish its material (`docs/content/sources.md`)
   [both channels import drafts, as the owner asked on 2026-10-19; office stories are published only
   once the office's permission is confirmed].

## Report (2026-10-19)

Staging: https://hararipp.orcamind.dev. Tag: `v0.2.0-content-core`.

### Gate (PLAN.md §13, Phase 2)

| Criterion | Status | Evidence |
|---|---|---|
| A four-language post published end to end | met | The labelled sample story (`php artisan portal:sample-post`) is live in English, Amharic and Afaan Oromo (`/en/news/sample-a-story-in-four-languages`, `/am/news/namuna-bearat-qwaniqwawoch-yeqerebe-zena`, `/om/news/fakkeenya-oduu-afaanota-afuriin`); the Harari site lists it in Amharic, marked as such, until a Harari version is written. The editor's path (draft → review → translate → publish) is covered by the workflow tests and written up in `docs/manual/02-publishing-a-post.md` |
| Lighthouse mobile ≥ 90 on the homepage and a post | met | Median of 3 runs on live staging: `/en` performance 100, accessibility 100, best practices 96 (LCP 1.5 s); `/am` 97, 100, 96 (LCP 2.5 s); the English sample post 100, 100, 96 (LCP 1.5 s). CLS 0 everywhere; about 5 KB of HTML and no JavaScript per page |

### What was built

| Step | Content | Commit |
|---|---|---|
| 2.1 | Translations per record with fallbacks, slugs that never change, categories and tags | af9aceb |
| 2.2 | Posts with language tabs, block editor, featured photo with alt text, versions and diff, duplicate | 3f48a59 |
| 2.3 | Per-language workflow (review, send back, publish, schedule, archive), notifications, comments, signed webhooks | e0725c3 |
| 2.4 | Public news listing and post pages, Harari → Amharic listing, RSS and JSON feeds, signed previews | dfc7674 |
| 2.5 | Share cards and generated images, JSON-LD, sitemaps per language, robots, redirects manager | 6d7c2d5 |
| 2.6 | Nested pages with templates, menus for header, footer and top bar | 0691adc |
| — | Two-step sign-in made a Super Admin switch, at the owner's request (ADR-0018) | 4415566 |
| 2.7 | Offices (tree, map pin), leaders (profiles), leagues, org chart, posts linked to them | 6b193f4 |
| 2.8 | Homepage builder: ordered, switchable sections with picks, limits and per-language headings | 4ea5970 |
| 2.9 | Site search with Ethiopic-aware word matching and search insights | 983ed07 |
| 2.10 | Dashboard v1: filters, widget registry, personal layouts, ten widgets, announcements | 909fc5a |
| 2.11 | Stories from the branch and office Telegram channels imported as drafts, duplicates skipped | 055c49d |
| 2.12 | Manual chapter "Publishing a post" (en, am), sample story, this report | adf91d0, 23d5fd0, tagged `v0.2.0-content-core` |

Decisions are recorded as ADR-0011 to ADR-0022 in `docs/DECISIONS.md`. Deviations from PLAN.md: two-step
sign-in is compulsory only when a Super Admin switches it on (ADR-0018); site search uses Scout's
database driver with word matching instead of Meilisearch on the shared host (ADR-0020); the office
directory shows a map per office but no combined map yet (ADR-0017); the dashboard has no traffic or
social widgets until analytics and the social hub exist (ADR-0021).

### Quality at the end of the phase

| Measure | Value |
|---|---|
| Tests | 588 Pest tests, all passing |
| Line coverage, whole app | 90.3% (CI minimum 80%) |
| Line coverage, app/Domain / app/Services / app/Settings | 88.4% / 98.1% / 87.5% (CI minimum 80% each) |
| Static analysis | Larastan level 6, no errors |
| Translations | English, Amharic and Afaan Oromo complete (850 keys); Harari keys present and empty |
| Lighthouse (mobile, live staging, median of 3 runs) | `/en` performance 100, accessibility 100, best practices 96 (LCP 1.5 s); `/am` 97, 100, 96 (LCP 2.5 s); the English sample post 100, 100, 96 (LCP 1.5 s). CLS 0 everywhere; about 5 KB of HTML and no JavaScript per page |

### Demo script (staging)

1. Open https://hararipp.orcamind.dev/en: the homepage built from its sections (welcome or featured
   stories, latest news, core values). Use the header search for "sample".
2. Open the sample story in English, switch to አማርኛ and Afaan Oromoo with the language switcher; open
   `/har/news` and show the story listed in Amharic.
3. Show `/en/leaders`, `/en/offices`, `/en/structure` (empty until staff enter the directory).
4. Sign in at `/admin`. Dashboard: change the period and language filters; **Customise dashboard**.
5. **Posts → Imported from channels**: open an imported draft, follow its source link, compare.
6. Create a post in Amharic, submit for review, mark as reviewed, add Afaan Oromo, publish both,
   preview link for English, publish English; check the public page and the "Also available in" links.
7. **Content → Homepage**: move "Latest news" above the hero, switch on the ticker with a picked story.
8. **Administration → Security**: show the two-step sign-in switch.

### Open items carried forward

- **Inputs from the branch** (PLAN.md §14, `docs/content/sources.md`): confirmation of the official
  channels; permission from the communication office to republish (until then office stories stay
  drafts); official names in four languages and logo files; contact details; the current leadership
  and office list (none invented).
- **Native review** of the Amharic and Afaan Oromo strings in `docs/translations-review.md` and of the
  Amharic manual chapters; Harari strings are empty and fall back to Amharic.
- **Remove the sample story** before launch: `php artisan portal:sample-post --remove`.
- Later in the plan: a combined office map, Meilisearch with typo tolerance, traffic analytics and the
  widgets that need it, Super Admin editing of role default dashboards, mega-menu panels, an admin theme
  build for custom screens.

### Next: Phase 3 — Media, events, documents, projects

Galleries with a lightbox, videos and a live page; events with registration, capacity, waitlist, iCal,
QR check-in and reminders; a documents library with an in-browser PDF viewer and versions; projects and
statistics; a forms builder with an inbox, tickets, Turnstile and replies per language (PLAN.md §13).
