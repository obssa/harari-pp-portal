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
| 2.8 | Homepage builder: reorderable, toggleable sections (hero slider, ticker, leadership message, latest news, press releases, counters, core values, leagues spotlight, partner links; event/social/video sections show once their modules exist), limits and curated picks, per-language overrides | |
| 2.9 | Search per language (Scout database driver now, Meilisearch-ready): search page and header search, Ethiopic-aware matching, zero-result log | |
| 2.10 | Dashboard v1: widget registry, per-role default layouts, per-user layouts (add, remove, reorder, resize), global range/language/office filters, first widgets (KPI strip, attention needed, editorial pipeline, translation coverage, activity feed, quick actions, announcements, media storage, queue health, top content) | |
| 2.11 | Content import from public sources (`docs/content/sources.md`): Telegram public channels of the branch and the communication office into drafts with source links, de-duplication and staff review; no scraping behind logins | |
| 2.12 | Phase 2 gate: end-to-end four-language post on staging, Lighthouse on homepage and post, manual chapter "Publishing a post", phase report | |

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
   [import the branch's own channel only until confirmed].
