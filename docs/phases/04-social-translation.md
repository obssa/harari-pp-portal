# Phase 4 plan — Social hub and translation tooling

Written 2026-10-20 from `docs/PLAN.md` §4 (Social media hub), §6 (translation workflow), §7, §10 and §13,
after the Phase 3 gate (`docs/phases/03-media-events-documents.md`). Each step ends with tests green, CI
green, a staging deploy and a hand-off, as before.

**Gate (PLAN.md §13):** the hub shows live posts from at least three platforms; the Harari JSON
round-trips (export → edit in Excel → import) without loss.

## Steps

| Step | Content | Status |
|---|---|---|
| 4.1 | Social sources and posts: sources per platform owned by the branch, an office or a league; `social_posts` with text, media, permalink and metrics; a `SocialFetcher` interface; the Telegram fetcher on public channel pages (as the story import, ADR-0022); scheduled fetching with errors kept per source; moderation in the admin (hide, pin) | done 2026-10-20 |
| 4.2 | More platforms: YouTube from the channel's public feed (the Data API when a key is saved, for counts and live status); Facebook through the Graph API with a page token (no scraping behind the login); TikTok and Instagram posts added by address through oEmbed | done 2026-10-20 (X by address too) |
| 4.3 | The public hub `/{lang}/social` with platform and owner filters, follow buttons and QR codes for the Telegram channels; a homepage teaser section; the latest posts on office and league pages; images copied into the library so the hub loads nothing from the platforms | done 2026-10-20 |
| 4.4 | Translations module, editing: a grid of interface strings with filters (missing, changed since English changed, by group), context and screenshot links, per-language edit rights (`translations.{lang}.edit`), history of every change | done 2026-10-20 |
| 4.5 | Export and import: JSON, CSV and Excel per language; import with a preview of what changes, placeholder checks and refusal of unknown keys; every import kept for rollback (`translation_imports`); the Harari round-trip proven by an automated test | done 2026-10-20 |
| 4.6 | Glossary: approved terms per language with notes, shown next to strings that use them, and a check that flags strings not using an approved term | done 2026-10-20 |
| 4.7 | Machine translation for Amharic and Afaan Oromo (never Harari, PLAN.md §6): a `TranslationProvider` interface with null, Google and LLM drivers; "Translate" in the grid and in content language tabs, marked "machine translated — needs review" until a person confirms; a translation queue dashboard widget | |
| 4.8 | Phase 4 gate: hub live with three platforms, the Harari round-trip on staging, manual chapter "Translations and the social hub", report, tag `v0.4.0` | |

## Main decisions to record

- ADR-0031 social sources, fetching and moderation (what is stored, how often, what is never done).
- ADR-0032 the public hub (privacy: no platform scripts, copied images, links out).
- ADR-0033 translation editing, export/import formats and rollback.
- ADR-0034 glossary.
- ADR-0035 machine translation (drivers, what is sent, review marks, never for Harari).

## New tables (draft)

`social_sources`, `social_posts` (unique `external_id` per source, PLAN.md §10), `translation_imports`
(locale, file, added, changed, removed, status, snapshot), `glossary_terms`, `ui_translation_revisions`;
`machine_translated_at` and `reviewed_at` on content translation tables.

## Open questions (do not block the build; defaults in brackets)

1. A Facebook page access token for the branch page (Meta Graph API) [Facebook stays off until it is
   saved under Integrations; posts can be added by address].
2. A YouTube Data API key [the public channel feed: titles, dates and pictures, no view counts].
3. Which offices and leagues have their own accounts [the branch accounts only, until staff add more].
4. Whether machine translation may be used at all, and with which provider (Google or an LLM); both send
   text to the provider [off until a key is saved and a Super Admin switches it on].
5. Native speakers to review Harari, Amharic and Afaan Oromo strings (Phase 5) [`docs/translations-review.md`].
