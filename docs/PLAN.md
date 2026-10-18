# Harari Prosperity Party Portal — Build Plan & AI Agent Prompt

Oct 18, 2026 · @Ramin

> Markdown conversion of the 55-page PDF brief. This file is the source of truth for the build.
> Deviations agreed with the owner are recorded in `docs/DECISIONS.md` (for example Laravel 13 +
> Filament 5 instead of Laravel 12 + Filament 4, and cPanel-compatible drivers for the staging host).

## 1. Executive summary

Build a four-language (English, Amharic, Afaan Oromo, Harari) public portal plus an admin back-office for
the Harari Prosperity Party branch office, on Laravel, that outclasses the two reference sites (Dire Dawa
PP and Oromia PP) in content depth, speed, design and editorial workflow. Neither reference site offers a
real multilingual experience, a modern CMS, a social media hub or role-based editorial control; Harari PP
today appears to have no dedicated website at all, so this will be the first-of-its-kind regional PP portal.

### What we are building

- **A public portal**: news, press releases, leadership and office directory, party programme and
  documents, events, galleries, leagues (youth and women), member/volunteer onboarding, social media hub,
  contact and feedback.
- **An admin back-office** (the team calls it the CRM): content management with per-language drafts,
  publishing workflow, media library, menus, translations, social feed sources, forms inbox, members and
  volunteers, roles and audit log.
- **A translation system**: UI strings in all four languages; Harari strings maintained through an
  editable, exportable, importable JSON file; content posted per language with optional
  machine-translation assist.

### Success criteria (acceptance)

| Area | Target |
|---|---|
| Performance | Lighthouse Performance ≥ 90 mobile, LCP < 2.5 s on 3G-class connections typical in Ethiopia, TTFB < 400 ms with full-page cache |
| Languages | Every public page renders in en, am, om, har with correct Ethiopic fonts and no untranslated fallback text visible to the public (missing Harari falls back to Amharic, flagged in admin) |
| Editorial | An editor can draft, translate, schedule and publish a news post in all four languages without developer help in under 10 minutes |
| Access control | Every admin action is permission-gated; every change is in the audit log with actor, time and diff |
| Reliability | 99.9% uptime target, daily off-site backups, one-command restore tested before launch |
| Accessibility | WCAG 2.2 AA; keyboard navigable; screen-reader labelled |
| SEO | hreflang for all 4 languages, structured data (Organization, NewsArticle, Event), XML sitemaps per language, Open Graph for every shareable page |

**Stack decision (summary)**: Laravel 12 (PHP 8.3), Filament 4 admin panel, Livewire 3 + Alpine.js +
Tailwind CSS 4 on the public site, MySQL 8, Redis, Meilisearch, Laravel Horizon queues, Cloudflare in
front. Details in section 9. *(Superseded in part by ADR-0001 and ADR-0002.)*

## 2. Reference-site analysis

Both reference sites are thin: Dire Dawa PP is a WordPress/Elementor blog with languages faked as post
categories; Oromia PP is a brochure site with a separate member login. We borrow their information
architecture and go well beyond it.

### Dire Dawa Prosperity Party (diredawaprosperity.org)

| What they have | How it is done | Our verdict |
|---|---|---|
| Menu: Home · News (አማርኛ, English, Oromifa, Somali, Arabic) · Offices (Woredas 1–9, ~15 sector offices and basic organisations) · Gallery (Video, Picture) · Leagues (Youth, Women's) · Resource · About us (About, Contact Us) | WordPress + Elementor, one category per language | Keep the menu *shape* (Offices, Leagues, Gallery, Resource) but replace language-as-category with true per-language content |
| Homepage: date bar, Facebook link, hero slider of latest posts, language-category tiles with post counts, post list with excerpts, pagination | Theme defaults | Replace with curated hero, featured story, sections by content type, leadership strip, events, social hub teaser |
| Facebook page plugin and SociableKit YouTube embed in sidebar | Third-party iframes | Build our own server-side social hub (section 5): faster, styled, cached, works when Facebook iframes are blocked |
| Footer: Prosperity Party blurb, national PP social links (Facebook, Telegram, YouTube, X), Who We Are, Be Part of Us (Join Volunteer Team), newsletter sign-up | Mailchimp-style widget | Keep all four footer blocks; add office hours, map, and legal pages |
| Office pages per woreda and per sector office | Category archives | Build an Office directory with profile, head of office, contact, and office-scoped news |
| Weak points | Untranslated UI, slow, no search, no events, no leadership pages, no documents library, mixed-language homepage | All fixed in our build |

### Oromia Prosperity Party (opp.et, member portal at oromia.opp.et)

The site blocks automated fetches, so this is from search snippets and cached text.

| What they have | Our verdict |
|---|---|
| Hero with party slogan and quote from the regional president | Keep: rotating quotes from the Harari branch head and the national PP president |
| "Medemer" philosophy section explaining the party ideology | Keep as a *Programme & Ideology* page (Medemer, party programme, manifesto, values) |
| Development works ("Hojilee Misooma") section: themed achievements | Keep as *Achievements / Projects* content type with regional stats |
| News Updates | Keep |
| Values list ("Dudhaalee": Medemer, multinationalism, democracy, national sovereignty, inclusiveness, patriotism, solidarity) | Keep as a *Core Values* block with icons, translated |
| Footer: Home, About Us, Officials, Media Library, Our Programmes, address, email, fax, social icons | Keep and extend |
| Separate member login/registration portal (oromia.opp.et) | Build a *Member & Volunteer* area inside the same Laravel app (phase 2), not a separate site |
| Weak points | Single language (Afaan Oromo), no search, no documents, no events, no accessibility work |

### National Prosperity Party and other branches

- National PP (prosperity.org.et) blocks automated access; its 2026 messaging stresses peaceful
  elections, sustainable peace, national unity, diplomacy, digital modernisation. Our Programme section
  should link to and summarise national positions.
- Amhara PP and Addis Ababa PP communicate mainly through Facebook and state media (Fana, ENA, AMECO); no
  strong standalone site was found. Confirms that a well-built regional portal is a differentiator.
- Harari PP (successor to the Harari National League, merged into PP in December 2019; PP won 32 of 36
  regional council seats in the June 2026 election) has no dedicated site found in search. Content for
  "About" should cover HNL heritage, the 2019 merger, and the current branch structure.

### Best-of-all-worlds patterns to adopt

- Leadership directory with bios, portraits and responsibilities.
- Documents & publications library (programme, manifesto, bylaws, reports, speeches) with filters and PDF viewer.
- Events calendar with registration and iCal export.
- Press centre: press releases, media contacts, downloadable logos and brand assets.
- Statements/positions tagged by topic.
- Join / volunteer / donate pathways (donation only if legally permitted; Ethiopian political-finance
  rules apply, so ship it as an optional, disabled-by-default module).
- Newsletter with double opt-in and SMS option (Ethiopian users are SMS-first).
- Accessibility statement, privacy policy, terms.
- Live/featured video (YouTube Live embed) and photo stories.
- Site-wide search across all content types and languages.

## 3. Site map and menu structure

Seven top-level items, each a mega-menu; every label is a translation key so the menu renders in all four
languages, and the whole menu is editable in admin (section 7). URL prefix carries the language:
`/en/...`, `/am/...`, `/om/...`, `/har/...` (default language configurable; `/` redirects by cookie, then
Accept-Language, then default).

| Top level | Children | Content behind it |
|---|---|---|
| Home | — | Hero, breaking-news ticker, featured story, latest news, leadership message, upcoming events, achievements counters, core values, social hub teaser, newsletter, partners |
| About | About Harari PP · History (HNL to PP) · Vision, Mission & Values · Programme & Ideology (Medemer, Manifesto) · Leadership · Organisational Structure · Branch Office · FAQs | Static pages (block editor), Leadership directory, org chart |
| News & Media | News · Press Releases · Statements · Speeches · Photo Gallery · Video Gallery · Live · Social Media Hub · Press Kit & Media Contacts | Posts by type with categories, tags, offices; media collections |
| Offices & Structures | Branch Office · Woreda/Kebele offices · Sector offices (Finance & Economy, Communication, Women & Children, Youth & Sports, Education, Health, Trade & Industry, Urban Development, Land, Agriculture & Water, Peace & Security, Public Service, Transport, Regional Council, Harar City, Haramaya University…) · Basic Organisations (መሰረታዊ ድርጅቶች) | Office directory with profile pages, head of office, contacts, map, office-scoped news |
| Leagues & Forums | Youth League · Women's League · Scholars/Professionals Forum · Diaspora Forum | League pages with their own news, leadership, events, join form |
| Programmes & Achievements | Development Projects · Green Legacy · Achievements by sector · Regional statistics · Election programme | Projects content type with status, location, photos, stats; counters |
| Get Involved | Become a Member · Volunteer · Events Calendar · Public Forum / Submit Feedback · Newsletter · Careers & Vacancies · Tenders/Notices | Forms with admin inbox; events; member portal entry |
| Resources | Documents & Publications · Laws & Policies · Reports · Downloads · Brand Assets | Document library with filters and viewer |
| Contact | Contact form · Office addresses & map · Hotline · Social links | Contact page |

**Utility bar (top strip)**: Ethiopian date and Gregorian date, language switcher (አማርኛ · Afaan Oromoo ·
መሐዲያ/Harari · English), search, social icons, accessibility toggle (font size, high contrast), member login.

**Footer**: About blurb + national PP links; Quick links; Offices; Get Involved (Join, Volunteer,
Newsletter with phone/email); Contact (address, hotline, email, map); legal (Privacy, Terms, Accessibility
statement, Sitemap); copyright with Ethiopian and Gregorian year.

**URL conventions**: `/{lang}/news/{slug}`, `/{lang}/offices/{slug}`, `/{lang}/leaders/{slug}`,
`/{lang}/events/{slug}`, `/{lang}/documents/{slug}`, `/{lang}/projects/{slug}`, `/{lang}/social`,
`/{lang}/search?q=`. Slugs are per language; a language switch on a detail page goes to the same record in
the other language, or to the listing if that translation does not exist.

## 4. Public website features, page by page

### Homepage

1. Utility bar: dual calendar date, language switcher, search, accessibility toggle, login.
2. Breaking-news ticker (admin-toggled, max 5 items).
3. Hero: curated slider (max 5 slides) of featured posts, each with image, kicker, title, excerpt, CTA;
   auto-pause on hover; swipe on mobile; LCP image preloaded.
4. Leadership message: portrait, name, title, 2-sentence quote, link to full message.
5. Latest news grid (6) with content-type badge, date in both calendars, office tag; "View all".
6. Press releases & statements column (5 latest).
7. Achievements counters (members, basic organisations, projects completed, trees planted…) animated on
   scroll, numbers managed in admin.
8. Core values strip with icons (Medemer, multinational unity, democracy, sovereignty, inclusiveness,
   patriotism, solidarity).
9. Upcoming events (3) with date tile and register button.
10. Programmes & projects highlight (3 cards).
11. Leagues spotlight (Youth, Women's) with latest post each.
12. Social Media Hub teaser: latest 6 posts across Facebook, Telegram, YouTube, X, TikTok, with link to full hub.
13. Video of the week (YouTube embed, lazy-loaded with poster image).
14. Photo gallery strip.
15. Newsletter + SMS sign-up.
16. Partner/related links (National PP, Harari Regional State, NEBE, neighbouring branches).

Every homepage section is a reorderable, toggleable block in admin (homepage builder).

### News & Media

- Listing with filters: type (news, press release, statement, speech), category, office, league, tag,
  date range; sort by newest/most read; infinite scroll or pagination; RSS and JSON feeds per language.
- Detail page: title, kicker, byline, publish/updated dates in both calendars, reading time, featured
  image with caption/credit, rich body (headings, quotes, embeds, galleries, tables, PDF embed), tags,
  offices, share bar (Facebook, Telegram, X, WhatsApp, copy link), related posts, "available in" language
  chips, print view, report-a-correction link.
- Photo gallery: albums with lightbox, EXIF-stripped, lazy-loaded responsive images (AVIF/WebP), download
  of original if allowed.
- Video gallery: YouTube/Facebook/Telegram-hosted videos embedded privacy-friendly (lite-youtube),
  categorised, playlist view; Live page shows a scheduled or ongoing YouTube Live.
- Press kit: logos (SVG/PNG), colour palette, leadership portraits, boilerplate, media contact form.

### About

- About, History (timeline block from HNL 1991 to PP 2019 to today), Vision/Mission/Values, Programme &
  Ideology (Medemer, manifesto PDF, election programme), FAQs (accordion).
- Leadership directory: grid by body (Executive Committee, Central Committee, Secretariat, Office heads);
  profile page with portrait, role, bio, responsibilities, term dates, speeches/statements by this person,
  social links.
- Organisational structure: interactive org chart (collapsible tree) generated from admin data.

### Offices & Structures

- Directory with search and filter by type (branch, woreda, kebele, sector office, basic organisation) and
  map view (Leaflet + OpenStreetMap).
- Office page: name, type, head of office (linked leader), address, phone, email, working hours, map pin,
  description, documents, office-scoped news and events, photo.

### Leagues & Forums

- Each league/forum is a mini-site: landing, leadership, news, events, gallery, documents, join form.

### Programmes & Achievements

- Project cards with sector, location (woreda), status (planned/ongoing/completed), budget if public,
  start/end, photos, progress bar; filter and map view.
- Achievements by sector with statistics blocks (number, label, source, as-of date) and charts.

### Get Involved

- Membership application form (multi-step; captures name, sex, DOB, ID type/number, woreda/kebele, phone,
  email, education, occupation, basic organisation, consent); OTP via SMS to verify phone; admin review
  queue; applicant status page via reference number.
- Volunteer sign-up with skills and availability.
- Events calendar (month/list), event detail with venue map, agenda, speakers, registration form with
  capacity and waitlist, iCal/Google Calendar export, reminders by SMS/email.
- Public feedback/petition form with category routing to the right office; ticket number; status lookup.
- Careers/vacancies and tenders/notices with deadlines and attachments.
- Newsletter preference centre (topics, language, channel).

### Resources

- Document library: type (programme, bylaw, report, policy, speech, form), language, year, office;
  in-browser PDF viewer; download counter; version history; related documents.

### Contact

- Form with spam protection (honeypot + Cloudflare Turnstile), category routing, auto-reply in the user's
  language; office address cards; hotline; map; social links.

### Site-wide

- Search (Meilisearch): instant results across all content types in the current language with typo
  tolerance for Ethiopic and Latin scripts; filters by type and date; recent searches.
- Language switcher that keeps the user on the equivalent page.
- Ethiopian calendar everywhere a date is shown (with Gregorian in parentheses, admin-configurable order).
- Cookie/consent banner (minimal, only if analytics enabled), privacy-friendly analytics (Plausible or
  Umami, self-hosted).
- Progressive Web App: installable, offline shell, cached recent news.
- Error pages (404/500/maintenance) branded and translated.
- Dark mode (optional, respects system preference).

## 5. Social Media Hub

The hub is a server-side aggregator: admins register social accounts, a scheduled job pulls the latest
posts into our database, and the public page renders them as native, styled cards, so the page is fast,
works where third-party iframes are blocked or slow, and is searchable. Nothing is posted *to* social
networks from the site in phase 1 (optional in phase 3).

**Public page `/{lang}/social`**

- Masonry/grid feed of cards: platform icon, account name, post text (expandable), media (image/video
  thumbnail), posted time (relative + absolute), engagement counts if the API returns them, "Open on
  {platform}" button.
- Filters: platform, account (branch, leagues, offices), media type, date; keyword search within fetched posts.
- Per-platform tabs plus an "All" stream; "Live now" banner when a YouTube/Facebook live is detected.
- Follow buttons for every registered account; QR codes for Telegram channels (very common in Ethiopia).
- Homepage teaser shows 6 latest; each office/league page can embed its own accounts' feed.

**Supported sources and how to fetch**

| Platform | Method | Notes |
|---|---|---|
| Facebook Page | Meta Graph API `/{page-id}/posts` with a long-lived Page access token (requires a Meta App and `pages_read_engagement`) | Fallback: admin pastes post URLs and we store oEmbed HTML |
| Telegram channel | Bot added as channel admin receives channel posts via webhook/`getUpdates`; or public channel RSS via `t.me/s/{channel}` scrape | Telegram is the most reliable and cheapest source |
| YouTube | YouTube Data API v3 `search` / `playlistItems` for channel uploads; `liveBroadcasts` for live status | Quota-friendly: poll every 15 min |
| X (Twitter) | API v2 user timeline (paid tier) | If no paid tier, fall back to manual post URLs + oEmbed |
| TikTok | TikTok Display API or oEmbed by URL | oEmbed fallback is enough |
| Instagram | Instagram Graph API via the linked Facebook Page | Optional |

**Admin side**

- Social Sources: add account, platform, credentials/token, owner (branch/league/office), fetch interval,
  enabled flag, last fetch status and error.
- Fetched posts list: hide/unhide a post, pin a post, mark as featured, edit the stored excerpt, add
  translation/caption.
- Manual entry: paste a post URL; system resolves oEmbed and stores it.
- Health dashboard: token expiry warnings, API quota, failures with retry.

**Technical notes**

- Queue job per source on a schedule (Laravel Scheduler + Horizon), idempotent on `external_id`.
- Media thumbnails downloaded and served from our CDN (never hot-link); respect each platform's branding
  and attribution rules.
- Cache the public hub for 5 minutes; invalidate on fetch.
- Store raw payload JSON for debugging; normalise to a `social_posts` table (section 10).

## 6. Multilingual strategy (en · am · om · har)

Two separate layers: **UI strings** (menus, buttons, labels, form text) live in JSON language files;
**content** (news, pages, leaders, documents) lives in per-language translation rows in the database.
English, Amharic and Afaan Oromo UI strings ship translated by the agent (reviewed by a native speaker
before launch); Harari ships as an English-keyed JSON file the team fills in manually and can
export/import at any time.

| Code | Language | Script | Direction | Fonts | Machine translation |
|---|---|---|---|---|---|
| `en` | English | Latin | LTR | Inter / system | n/a (source) |
| `am` | Amharic (አማርኛ) | Ethiopic (Ge'ez) | LTR | Noto Sans Ethiopic, Abyssinica SIL fallback | Google Cloud Translation, DeepL (no), LLM |
| `om` | Afaan Oromoo | Latin (Qubee) | LTR | Inter | Google Cloud Translation (Oromo added 2022), LLM |
| `har` | Harari (Gey Sinan, መሐዲያ) | Ethiopic primarily; Latin and Arabic-script variants exist | LTR | Noto Sans Ethiopic | **None available** — manual only |

Harari is not supported by any public machine-translation service, so the design must make human
translation painless.

### UI strings: file layout

```
lang/
  en.json        # source of truth, flat "dotted.key": "English text"
  am.json
  om.json
  har.json       # starts as a copy of en.json values = "" (empty) so every key is listed
  en/validation.php, am/validation.php ...   # Laravel framework strings
```

- Keys are stable, namespaced and descriptive: `nav.news`, `home.hero.cta`,
  `forms.membership.step1.title`, `footer.newsletter.placeholder`.
- Rule for the agent: never hard-code visible text in Blade/Livewire; always `__('key')`. A CI check
  (`php artisan lang:missing`) fails the build if any key in `en.json` is absent from `am.json`/`om.json`,
  and warns for `har.json`.
- Fallback chain: `har → am → en`; `om → en`; `am → en`. When a fallback is used, the admin Translation
  dashboard counts it as missing.
- Pluralisation and placeholders use Laravel's `:name` and `{0}|{1}` syntax; Ethiopic plural rules are
  simple (one/other).

### Harari translation workflow (admin → Translations → Harari)

1. **Grid editor**: table of key · English · Amharic (as reference) · Harari (editable); filters: missing
   only, changed since last export, by namespace; inline save; progress bar (e.g. 412/1,180 translated).
2. **Export**: download `har.json` (flat JSON, UTF-8), or `.xlsx` / `.csv` with columns key, en, am, har,
   context, so translators can work in Excel offline.
3. **Import**: upload JSON/CSV/XLSX; preview diff (added, changed, removed, invalid placeholders); choose
   "overwrite" or "fill missing only"; validation rejects rows whose `:placeholders` do not match the
   English key; import is atomic and logged; previous version kept for rollback.
4. **Publish**: imports write to the DB translation table *and* regenerate `lang/har.json`; cache is
   cleared; the public site switches instantly.
5. **Context**: each key carries an optional `context` note and a screenshot link so translators know
   where it appears.
6. The same screens work for `am` and `om` (useful for fixing machine translations).

Sample `har.json` structure is in the Appendix (section 16).

### Content translation (posts, pages, leaders, events, documents)

- Every translatable model has a `*_translations` table (one row per locale) using
  `spatie/laravel-translatable` or `astrotomic/laravel-translatable`: title, slug, excerpt, body, SEO fields.
- **Editor UX**: language tabs on every record (EN · አማ · OM · HAR). An editor picks the *source
  language* when creating (often Amharic), writes it, then either:
  - **a)** clicks **"Translate with AI"** on another tab: a queued job calls the configured provider and
    pre-fills title/excerpt/body, marked `machine_translated = true` and shown with a yellow "Needs review"
    badge; a reviewer edits and marks "Reviewed"; or
  - **b)** types/pastes the translation manually (always the path for Harari); or
  - **c)** leaves the tab empty: the post is published only in the languages that have content, and the
    public page shows "Also available in" chips for the others.
- **Per-language publishing status**: each translation has its own `status` (draft / in review /
  published) and `published_at`, so Amharic can go live now and Harari tomorrow.
- **Translation queue**: a dashboard of records missing a language, assignable to a translator role, with due dates.
- **Machine translation providers** (pluggable driver, `config/translation.php`):
  - Google Cloud Translation API v3: supports Amharic and Oromo; free tier of 500,000 characters per
    month, then about USD 20 per million characters. Good default for am/om.
  - LLM driver (Claude or GPT-class model via API): often better fluency for Amharic political prose;
    prompt includes glossary (party terms) and tone; cost is per-token, pennies per article.
  - LibreTranslate (self-hosted, free): weak or absent for Ethiopian languages; include only as an optional driver.
  - **Harari**: no provider; "Translate with AI" is hidden on the HAR tab. Optionally an LLM "draft from
    Amharic" button can be enabled later behind a flag once quality is tested with native speakers.
- **Glossary**: admin-managed term base (e.g. "Prosperity Party" = "ብልጽግና ፓርቲ" = "Paartii Badhaadhinaa"
  = Harari term) injected into MT prompts and shown to translators.

### Other locale details the agent must handle

- Ethiopian calendar (`andegna/calender` PHP package) and dual date display; Ethiopian numerals optional.
- `lang` and `hreflang` attributes per page; `x-default` to the default language; language-specific sitemaps.
- Ethiopic typography: larger line-height (1.7), font-display swap, subset fonts for am/har, avoid faux-bold.
- Search: Meilisearch with per-language indexes; Ethiopic tokenisation works on word boundaries; add
  transliteration synonyms for common Latin-typed Amharic.
- Transliteration of slugs: Ethiopic titles get Latin slugs via a transliteration table (admin can
  override); fallback to id-based slug.
- Input: on-screen hint for Ethiopic keyboards is not needed, but forms must accept Ethiopic in all
  fields, including names and addresses, with correct `maxlength` on bytes.

## 7. Admin back-office (CMS / CRM)

One Filament panel at `/admin`, itself translated (admin UI in English and Amharic at minimum), with a
dashboard, the content modules below, and the people modules that give it the "CRM" character. Everything
is permission-gated (section 8) and audited.

### Dashboard

The dashboard is a role-aware, user-configurable widget grid, not a fixed page: each role gets a sensible
default layout, every user can add, remove, resize and rearrange widgets, and every number drills down to
the records behind it. It is built on Filament's widget system extended with a `dashboard_layouts` table
(per user, per role default), a widget registry (`App\Dashboard\Widgets\*` implementing `DashboardWidget`
with `permission()`, `defaultSize()`, `data()`, `cacheTtl()`), and Livewire polling or websockets (Laravel
Reverb) for the widgets marked live.

**Principles**

- *Role-based homes*: Admin sees operations and risk; Publisher sees the editorial pipeline; Membership
  Officer sees applications and member growth; Communications Officer sees reach and social; Analyst sees
  everything read-only. Super Admin edits the default layout for each role.
- *Configurable*: drag-drop grid (12 columns, 1–4 rows tall per widget), "Add widget" gallery filtered by
  permission, save/reset layout, multiple named dashboards per user (e.g. "Election week"), shareable presets.
- *Global controls*: date range picker (today, 7d, 30d, custom, Ethiopian fiscal year), language filter,
  office/league scope filter; all widgets react to them.
- *Drill-down everywhere*: click a KPI to open the filtered list; click a chart segment to filter the page.
- *Real time where it matters*: pending items, live visitors, queue health, social fetch status update
  without reload; heavy analytics widgets cache for 5–15 minutes with a "last updated" stamp.
- *Comparisons and trends*: every KPI shows value, delta vs previous period, and a 30-day sparkline.
- *Alerts*: thresholds configurable per widget (e.g. "warn if pending applications > 50 for 48 h") with
  in-app, email and Telegram notification.
- *Export*: any widget to CSV/PNG; whole dashboard to PDF; scheduled email snapshot (daily/weekly) per role.
- *Mobile-ready*: single-column responsive layout; admin is a PWA so officers can approve and monitor from a phone.
- *Fast*: aggregate tables refreshed by scheduled jobs (hourly rollups of views, submissions,
  applications) so dashboards never run heavy queries on live tables; target < 1 s render.
- *Localised*: admin UI in English and Amharic (Afaan Oromo later), dual calendar on all date widgets.

The default Publisher layout: global filters on top (Dashboard · Range · Language · Office, "+ Add widget",
"Save layout"), six KPI tiles with sparklines (Visitors, Pending review, Missing har, Applications, Open
tickets, Events), then a 12-column grid: Attention needed, Editorial pipeline (Draft · Review · Translate ·
Scheduled · Published), Traffic by language, Applications funnel (Started · Verified · Submitted ·
Approved), Social source health, Activity feed, Queue and system health. Every card can be dragged,
resized, removed, exported or given an alert.

**Widget catalogue** (each is a class in the registry; admins can enable/disable globally)

| Group | Widget | Shows | Source | Default for |
|---|---|---|---|---|
| Overview | KPI strip | Visitors, page views, published posts, pending reviews, pending applications, open tickets, upcoming events — each with delta + sparkline | Analytics rollups, DB | All |
| Overview | Attention needed | Prioritised list: posts awaiting review > 24 h, translations overdue, social source errors, failed jobs, expiring tokens, backups missed, low disk | Health + domain queries | Admin, Publisher |
| Overview | Activity feed | Who did what, filterable by module/user, with links | Activity log | Admin |
| Overview | Quick actions | New post / press release / event / document; Approve next item; Send campaign | — | All |
| Overview | Announcements | Internal notices from Super Admin to staff (release notes, deadlines) | `announcements` | All |
| Editorial | Pipeline (Kanban) | Draft → In review → Translating → Scheduled → Published, cards draggable | Posts | Editor, Publisher |
| Editorial | Editorial calendar | Month/week view of scheduled and published content, events, campaigns; drag to reschedule | Posts, events, campaigns | Publisher, Communications |
| Editorial | Translation coverage | % of UI strings and content translated per language; missing counts; overdue tasks | ui_translations, translations | Translator, Admin |
| Editorial | Top content | Most viewed/shared posts and documents in range, by language | Analytics | Publisher, Analyst |
| Editorial | Author productivity | Posts per author/office, review turnaround time | Posts, activity | Admin |
| Audience | Traffic trend | Visitors and page views over time, by language and device | Plausible/Umami API | Communications, Analyst |
| Audience | Language split | Share of sessions per locale; map of regions/cities | Analytics | Analyst |
| Audience | Acquisition | Referrers, social vs search vs direct, top campaigns (UTM) | Analytics | Communications |
| Audience | Search insights | Top site-search queries, zero-result queries (content gaps) | Meilisearch logs | Publisher |
| Social | Social overview | Followers, posts fetched, engagement per platform and account, best post | social_posts metrics | Communications |
| Social | Source health | Last fetch, errors, token expiry countdown, API quota | social_sources | Admin, Media Manager |
| People | Applications funnel | Started → OTP verified → Submitted → Approved/Rejected, with conversion and median time | membership_applications | Membership Officer |
| People | Member growth | New members per week/month by office; totals by sex and age band; renewals due | members | Membership Officer, Admin |
| People | Members map | Members and basic organisations by woreda/kebele | members, offices | Membership Officer |
| People | Volunteers | Active volunteers, hours logged, upcoming assignments | volunteers | Membership Officer |
| Engagement | Tickets & SLA | Open/overdue tickets by office, average first response, satisfaction | form_submissions | Support Agent, Office Head |
| Engagement | Campaign results | Sent, delivered, opened, clicked, unsubscribed per campaign and channel | campaign_deliveries | Communications |
| Engagement | Subscribers | Growth of email/SMS lists, confirmed vs pending, by topic/language | subscribers | Communications |
| Events | Events & registrations | Registrations vs capacity, waitlist, check-ins, upcoming agenda | events | Publisher, Membership |
| Operations | System health | App/DB/Redis/Meilisearch/queue/storage status, response time, error rate, uptime | laravel-health, Sentry, Horizon | Admin |
| Operations | Queue & jobs | Jobs per queue, failed jobs with retry, long-running | Horizon | Admin |
| Operations | Backups & security | Last backup, size, restore drill date; failed logins, locked accounts, 2FA coverage, admin sessions | laravel-backup, activity log | Super Admin |
| Operations | Media storage | Usage vs quota, largest files, unused media | media | Media Manager |
| Operations | SEO health | Indexed pages, broken links, missing meta/alt, sitemap status, Search Console clicks/impressions (optional API) | Crawler job, GSC API | Publisher |
| Custom | Custom query | Admin-defined KPI from a saved filter (e.g. "posts tagged Harar Day"), number/trend/table | Saved queries | Super Admin builds, assigns |
| Custom | Embedded | External iframe/URL card (Metabase, Looker Studio, Google Analytics) | URL | Analyst |
| Custom | Notes | Free-text markdown card for team instructions | — | All |

**Analytics & reporting module** (separate from the dashboard; deeper, exportable)

- *Content analytics*: per-post/page/document views, unique visitors, average time, scroll depth, shares
  by network, downloads; by language, office, league, author, category; compare periods.
- *Audience analytics*: traffic by locale, device, country/region/city, referrer, campaign; new vs
  returning; peak hours (to time publishing and SMS).
- *People analytics*: applications, approvals, rejections with reasons, time-to-decision, by woreda/kebele
  and officer; member demographics (aggregated, no PII in reports); volunteer hours.
- *Engagement analytics*: forms and tickets by category and office, SLA compliance; campaign performance;
  event attendance.
- *Social analytics*: fetched-post engagement trends per platform; best times; top posts; follower growth
  (where APIs allow).
- *Operational analytics*: publishing throughput, review turnaround, translation lag per language, error
  rates, uptime.
- *Report builder*: pick a dataset, dimensions, measures, filters, chart type; save as a report; share with
  roles; schedule delivery (PDF/CSV by email/Telegram daily, weekly, monthly, or before a recurring
  meeting); every report has a permalink.
- *Standard reports* shipped: Weekly editorial report, Monthly branch communications report, Membership
  monthly by woreda, Quarterly leadership summary (one-page PDF with charts), Election-period daily brief.
- *Data access*: all rollups in `analytics_*` tables; read-only SQL view for Metabase or Looker Studio
  connection; CSV/Excel export respecting role scope; API endpoints under `/api/admin/analytics`
  (Sanctum, scoped).
- *Privacy*: analytics are cookieless and aggregated; PII never appears in dashboards or reports except in
  the Members and Applications modules behind their own permissions.

### Content modules

| Module | Key features |
|---|---|
| Posts (news, press release, statement, speech, announcement) | Language tabs; block editor (TipTap/Editor.js: paragraphs, headings, images with caption/credit, gallery, video embed, quote, table, file, call-out, PDF); featured image with focal point; categories, tags, offices, leagues, leaders mentioned; status draft/in review/scheduled/published/archived per language; scheduled publishing; sticky/featured flags; SEO panel (meta title, description, OG image, canonical, noindex); revision history with diff and restore; preview link (tokenised, works for drafts); duplicate; bulk actions; word count; "Also available in" auto |
| Pages | Same editor plus page templates (default, landing, full-width); nested pages; redirect manager |
| Homepage builder | Drag-reorder sections, toggle on/off, set limits and curated picks per section, per-language overrides |
| Media library | Folders, upload (drag-drop, multi), automatic WebP/AVIF conversions and responsive sizes, image editor (crop, focal point), alt text per language, EXIF strip, usage tracking (where used), search, bulk tag, video/document support, storage quota view |
| Leaders | Name per language, portrait, body/committee, position, rank order, term, bio, contacts, social; linked office |
| Offices & structures | Hierarchical (branch → woreda → kebele; sector offices; basic organisations), head, contacts, geo-pin picker, hours, cover photo |
| Leagues & forums | League profile, leadership, own posts/events via scoping |
| Events | Multilingual, start/end (both calendars), venue + map, online link, agenda, speakers (linked leaders), registration on/off, capacity, waitlist, custom fields, attendee export (CSV), QR check-in, reminders |
| Projects & achievements | Sector, location, status, dates, budget, stats, gallery, progress |
| Statistics | Number blocks (value, label, unit, source, as-of) reusable in pages and counters |
| Documents | Multilingual metadata, file per language, type, year, office, version history, access (public/members), download counts |
| Galleries | Albums per language, bulk upload, cover, sort |
| Videos | URL (YouTube/Facebook/Telegram), auto-fetch title/thumbnail, category, live flag |
| Menus | Visual tree editor for header, footer columns, utility bar; items link to pages/posts/URLs; labels per language; visibility rules |
| Translations | UI string grid per language, import/export, missing report, glossary |
| Social sources & posts | As in section 5 |
| Forms builder | Create forms (contact, feedback, petition, vacancy application) with fields, validation, routing to office/email, Turnstile, auto-reply templates per language |
| Redirects & SEO | 301 manager, sitemap settings, robots, schema defaults, broken-link report |
| Settings | Site identity, logos, colours, default language, calendars, contact details, social links, API keys (encrypted), maintenance mode, analytics, mail/SMS providers |

### People modules (the CRM part)

| Module | Key features |
|---|---|
| Membership applications | Inbox with filters, applicant profile, documents, OTP-verified phone, review/approve/reject with reasons, assign to woreda office, SMS/email notifications, export, duplicate detection |
| Members | Approved members directory (ID number, basic organisation, office, status, join date), bulk import from Excel of existing membership rolls, renewal tracking, card/ID PDF generation (optional), notes and timeline, GDPR-style data export/delete |
| Volunteers | Skills, availability, assignments to events/campaigns, hours log |
| Contacts / subscribers | Newsletter and SMS lists, topics, language, consent, bounce handling |
| Inbox (forms) | All submissions, ticket status (new/assigned/in progress/resolved), assignee, internal notes, canned replies, SLA timer |
| Campaigns | Email (Mailgun/SES) and SMS (Ethio Telecom / Africa's Talking / Twilio driver) broadcasts to segments; templates per language; send-time scheduling; delivery reports |
| Media contacts | Journalists and outlets list for press releases distribution |

### Editorial workflow

1. Author creates post in source language → status *Draft*.
2. Author submits → *In review*; Editor gets notified.
3. Editor approves translation(s) or sends back with comments (inline comments on the record).
4. Translator fills other languages (MT-assist for am/om, manual for har) → each marked *Reviewed*.
5. Publisher schedules or publishes per language; social sharing checklist shown (copy text, OG preview).
6. Post-publish: corrections create a new revision with an "Updated" note on the public page.

### Cross-cutting admin features

- Global search (Cmd+K) across records.
- Notifications in-app, email, optional Telegram bot for editors.
- Two-factor authentication (TOTP) mandatory for Admin and Publisher roles; login alerts; session
  management; password policy; account lockout.
- Audit log for every create/update/delete/login/export with before/after diff (`spatie/laravel-activitylog`).
- Soft deletes and trash with restore.
- Import/export: posts to/from WordPress WXR (so Dire Dawa-style archives can be migrated), members from
  Excel, translations as JSON/CSV.
- Scheduled jobs monitor (Horizon), queue failures, backup status (`spatie/laravel-backup`), health checks
  (`spatie/laravel-health`).
- API tokens (Sanctum) for a future mobile app, with read-only public endpoints.

### Future-proofing: how the back-office grows without a rewrite

Build the admin as a platform: modules that register themselves, a workflow engine instead of hard-coded
statuses, events and webhooks on everything, and a stable API. Each item below is cheap to lay down now
and expensive to retrofit.

| Capability | What to build now | What it unlocks later |
|---|---|---|
| Module system | Each domain (`app/Domain/*`) is a self-registering module with its own service provider, migrations, permissions, Filament resources, dashboard widgets, translation namespace, and a `module.json` manifest; a Modules admin page toggles modules on/off | Add Donations, Elections/Candidates, Petitions, Polls, Training/E-learning, Internal Documents, Procurement notices as drop-in modules |
| Content types as config | Content types defined in `config/content_types.php` (fields, taxonomies, templates, SEO defaults); posts use a JSON block body with a block registry | New types (Opinion, Interview, Fact-check, Infographic) and new editor blocks (poll, timeline, map, chart, quiz) without migrations |
| Workflow engine | States and transitions stored per content type (`workflows`, `workflow_transitions` with permission, conditions, actions); UI to edit them; default = Draft → In review → Translating → Scheduled → Published | Legal review step for statements, two-person approval for press releases, league-specific flows, SLA timers per state |
| Domain events + webhooks | Every create/update/publish/approve fires a Laravel event; Webhooks module lets admins subscribe URLs (with secret, retries, logs) to events; outgoing Telegram/Slack notifications use the same bus | Auto-post to Telegram on publish, notify national PP systems, trigger Zapier/n8n automations, sync to a mobile app |
| Automation rules | "When X then Y" builder: triggers (event, schedule, threshold), conditions, actions (notify, assign, tag, change state, send SMS, call webhook) | Auto-assign feedback by category to offices, remind reviewers, escalate stale tickets, publish weekly digest |
| Versioned public API | `/api/v1` read endpoints for all public content with locale, filters, pagination, ETags; `/api/admin/v1` for scoped writes; OpenAPI spec generated (Scramble/L5-Swagger); API keys with scopes and rate limits | Official mobile app, kiosks in offices, data sharing with regional government portals, headless front ends |
| Integration framework | Driver interfaces already planned (SMS, MT, Social, Mail) plus Integrations page storing credentials encrypted, with Test-connection buttons and health status | Add Fayda/eSignet login, Telebirr/CBE Birr payments (if donations/membership fees are permitted), WhatsApp Business API, Google Search Console, Meta Ads, Mailchimp sync |
| Multi-site / tenancy-ready | `site_id` on content and settings from day one (single site now); theme tokens, menus, domains per site | Separate sub-sites for woreda offices or leagues on sub-domains sharing one admin and member base; template for other PP branches |
| AI assistance (opt-in, logged) | `AiAssistant` service with provider driver; actions: translate (am/om), summarise to excerpt, suggest title/SEO/tags, generate alt text, tone check against glossary, draft social captions per platform; every suggestion reviewed by a human and logged | Smart search answers for visitors (RAG over published content), inbox triage and suggested replies, Harari draft translation once native speakers validate quality |
| Import/export everywhere | Each module exposes CSV/XLSX/JSON import with mapping UI and dry-run, and export respecting scope; WordPress WXR importer | Migrating other branches' sites, bulk member onboarding, archive hand-over |
| Design tokens & theming | All colours/typography in tokens; admin theme settings (logo, primary colour, fonts) applied to public and admin | Rebrand or per-league accents without code |
| Feature flags | `laravel-pennant` flags per feature and per role | Gradual rollout of new modules to pilot offices |
| Search platform | Meilisearch indexes for every module incl. admin global search | Members/applications search for officers, document full-text (PDF text extraction) |
| Notifications centre | In-app inbox with read state, preferences per channel (email, SMS, Telegram, push) per event type | Web push for editors, digest mode, escalation chains |
| Audit & retention | Activity log with diff plus retention policies per table | Compliance reporting, legal hold on content |
| Observability | OpenTelemetry traces, structured logs, Sentry, metrics endpoint | Grafana dashboards, SLOs as the branch scales |

### Governance, compliance and operations centre

A dedicated *Administration* area (Super Admin and Admin) groups the controls an international-standard
back-office needs.

- *Users & access*: user directory with status, roles, scopes, last login, 2FA state; invite by email with
  expiring link; forced password reset; session list with remote sign-out; SSO-ready (OIDC) for future
  Fayda or Microsoft/Google accounts; login history and anomaly flags (new device/country).
- *Roles & permissions*: matrix editor (role × permission), clone role, scope assignment
  (office/league/language), approval requirements per action, and a "What can this user do?" simulator.
- *Audit log explorer*: filter by user, module, action, date; view before/after diff; export; immutable
  (append-only table, hashed chain optional); retention policy.
- *Data protection*: consent texts per form and language with versioning; data subject request workflow
  (export/delete a person's data across modules); retention rules per dataset with automatic
  anonymisation; PII field inventory; breach log.
- *Content governance*: publishing checklist templates (legal, fact-check, translation complete), embargo
  dates, mandatory fields per content type, banned-terms list with warnings, link checker, media
  licence/credit requirements.
- *Configuration*: settings grouped (Identity, Languages & calendars, Contact, Social links, Mail, SMS,
  Integrations, SEO, Performance/caching, Maintenance, Feature flags); settings history with who changed
  what; export/import configuration between staging and production.
- *Operations*: Horizon, health checks, backups (run now, restore drill log), cache clear, sitemap
  regenerate, search re-index, scheduled tasks list with last/next run, error tracker link, environment
  info, release notes.
- *Localisation ops*: language enable/disable, default language, fallback chain editor, translation
  memory and glossary, translator assignments and deadlines, coverage report per language.
- *Help & training*: contextual help on every screen (editable in admin), embedded user manual, video
  walkthroughs, changelog, support request to the developer team with diagnostics attached.

## 8. Roles and permissions

Role-based access control with `spatie/laravel-permission`: permissions are fine-grained (`posts.create`,
`posts.publish`, `translations.har.edit`, `members.export` …), roles are bundles of permissions, and
**scopes** (office, league, language) narrow a role to a slice of content. Super Admin can create custom
roles from the permission list.

| Role | Can | Cannot | Scope options |
|---|---|---|---|
| Super Admin | Everything, including settings, roles, API keys, deletions, backups | — | Global |
| Admin | All content, people, menus, translations, social sources, users (except Super Admin) | Change system settings, manage API keys, purge audit log | Global |
| Publisher | Approve and publish any content in any language, schedule, feature on homepage, manage menus | Manage users, settings | Global or per office/league |
| Editor | Create/edit all content types, review submissions, manage media, send back to author | Publish, delete published content | Office/league |
| Author | Create and edit own drafts, upload media, submit for review | Publish, edit others' posts | Office/league |
| Translator | Edit translations (UI strings and content) for assigned languages, mark reviewed, import/export language files | Change source-language content, publish | Per language (e.g. `har` only) |
| Media Manager | Media library, galleries, videos, social sources | Posts, people | Global |
| Communications Officer | Press releases, press kit, media contacts, campaigns | Members, settings | Global |
| Membership Officer | Applications, members, volunteers, exports, SMS to members | Content, settings | Office (woreda) |
| Support Agent | Forms inbox and tickets, canned replies | Content, members | Office |
| Analyst | Read-only dashboards, analytics, exports | Any write | Global |
| Office Head (viewer) | Read-only view of their office's content and inbox | Any write | Office |
| Member (public) | Member portal: own profile, card, events, documents marked members-only | Admin panel | Self |

**Rules the agent must implement**

- Deny by default; every Filament resource, page, action and API route checks a policy.
- Scoped roles filter list queries automatically (an Editor scoped to the Youth League sees only Youth League posts).
- Language scope applies to translation tabs: a Harari translator sees other tabs read-only.
- Sensitive actions (publish, delete, export of personal data, role change) require 2FA-confirmed session
  and are logged with IP.
- Personal data (members, applicants) is encrypted at rest for ID numbers and phone, masked in lists,
  exportable only with `members.export`.
- Impersonation (Super Admin only) is logged and banner-flagged.
- Seeder creates the roles above with sensible permission sets; a matrix page in admin shows role ×
  permission with toggles.

## 9. Technical architecture (Laravel)

A single Laravel monolith serves the public site (server-rendered Blade + Livewire for interactive parts)
and the Filament admin panel, backed by MySQL, Redis and Meilisearch, with Horizon workers for queues and
Cloudflare in front for CDN, WAF and full-page caching. Monolith first: it is simplest to host in Ethiopia
or on a single VPS, and it can be split later.

Requests pass through Cloudflare (DNS · CDN · WAF · full-page cache · Turnstile) into the one Laravel app
(public site: Blade · Livewire · Tailwind, 4 locales, server-rendered; Filament admin: CMS · CRM · RBAC ·
audit log · translations · social sources; public API (Sanctum): read-only JSON for mobile app, member
portal auth). The app owns four data stores (MySQL 8 for content and translations, Redis 7 for cache,
queue and session, Meilisearch for search per locale, S3/MinIO object storage for media), and all slow or
external work (image conversion, machine translation, social fetching, SMS, mail, sitemap, backups) runs
in Horizon workers so public pages never wait on an outside API. External services: Social APIs
(Facebook, Telegram, YouTube, X, TikTok), Machine translation (Google Translate for am/om, LLM driver,
none for har), SMS + email (Ethio Telecom SMS, Twilio, Mailgun/SES), Ops (Sentry, Uptime Kuma, off-site backups).

### Stack

| Layer | Choice | Why |
|---|---|---|
| Runtime | PHP 8.3, Laravel 12 (LTS-style support), Octane (FrankenPHP or Swoole) optional for throughput | Current, fast, well-documented |
| Admin | Filament 4 (panels, forms, tables, notifications, Spatie plugins) | Fastest route to a polished RBAC admin with translations tabs |
| Public front end | Blade components + Livewire 3 + Alpine.js, Tailwind CSS 4, Vite | Server-rendered = fast on slow networks and SEO-friendly; Livewire only for search, filters, forms |
| Database | MySQL 8 (utf8mb4, `utf8mb4_0900_ai_ci`) or MariaDB 11 | Ethiopic text safe; JSON columns for blocks |
| Cache / queues / sessions | Redis 7 | Horizon dashboards, rate limiting, cache tags |
| Search | Meilisearch via Laravel Scout | Typo-tolerant, multi-index per language, instant |
| Media | Local disk or S3-compatible (MinIO self-hosted, or Backblaze B2/Cloudflare R2); `spatie/laravel-medialibrary` conversions; `intervention/image` | Responsive AVIF/WebP, focal crops |
| Translations | `spatie/laravel-translatable` (content), `spatie/laravel-translation-loader` (DB-backed UI strings exported to JSON), custom import/export module | DB-editable strings, JSON on disk |
| Permissions / audit | `spatie/laravel-permission`, `spatie/laravel-activitylog`, `filament-shield` | Proven RBAC + logging |
| SEO | `spatie/laravel-sitemap`, `artesaos/seotools` or `ralphjsmit/laravel-seo`, `spatie/schema-org` | hreflang, sitemaps, JSON-LD |
| Calendar | `andegna/calender` | Ethiopian calendar conversion |
| Mail / SMS | Laravel Mail (Mailgun/SES/SMTP), SMS driver interface with Ethio Telecom SMS gateway, Africa's Talking, Twilio adapters | SMS-first audience |
| Social fetchers | Guzzle/Saloon SDK clients per platform, scheduled jobs | Section 5 |
| Machine translation | Driver interface: Google Cloud Translation, Anthropic/OpenAI LLM, LibreTranslate | Section 6 |
| Auth | Laravel Fortify/Breeze (members), Filament auth (admin), TOTP 2FA, Sanctum API tokens, optional Fayda/eSignet OIDC for members later | Security |
| Testing | Pest, Laravel Dusk for critical flows, Larastan level 6+, Pint, Rector | Quality gates |
| DevOps | Docker Compose (dev), GitHub Actions CI, deploy via Laravel Forge/Ploi or Deployer to Ubuntu 24.04 VPS; Cloudflare (DNS, CDN, WAF, Turnstile); `spatie/laravel-backup` to off-site bucket; Sentry for errors; Uptime Kuma | Reliability |

### Application structure

```
app/
  Domain/             # modular: Content, Media, People, Social, Translation, Events, Documents
    Content/{Models,Policies,Actions,Enums,Observers}
    ...
  Filament/           # admin resources, pages, widgets
  Http/Controllers/Public   # thin controllers, cached views
  Livewire/           # search, filters, forms
  Services/{Translation,Social,Sms,Calendar}
  Support/            # Ethiopic transliteration, helpers
resources/views/{components,pages,emails}
lang/{en,am,om,har}.json
database/{migrations,seeders,factories}
```

### Caching and performance plan

- HTTP: Cloudflare full-page cache for anonymous GET pages (TTL 5 min, purge on publish via API);
  `Cache-Control: s-maxage` headers from Laravel.
- App: response cache (`spatie/laravel-responsecache`) keyed by locale + path, tagged by model; fragment
  caching for menus, footer, homepage sections; query caching for leaders/offices.
- Assets: Vite build with hashed files, Brotli, preloaded fonts, critical CSS inline, lazy images with
  `loading=lazy` and `decoding=async`, lite YouTube embeds, no third-party JS except Turnstile and analytics.
- Database: proper indexes on `(locale, slug)`, `(status, published_at)`, FULLTEXT where used; eager
  loading enforced (`Model::preventLazyLoading` in non-production).
- Queues: everything slow (image conversions, MT, social fetch, emails, SMS, sitemap) is queued; Horizon
  with separate queues `default`, `media`, `social`, `notifications`.
- Budget: homepage HTML < 60 KB gzipped, JS < 80 KB gzipped, LCP image < 120 KB.

### Environments and deployment

- `local` (Docker/Sail), `staging` (basic-auth protected, real data copy anonymised), `production`.
- Zero-downtime deploys (symlinked releases), migrations run with `--force` after backup,
  `php artisan down --secret` for maintenance.
- Secrets in `.env` only on servers; API keys stored encrypted in DB settings using `Crypt`.
- Daily DB + media backups, 30-day retention, monthly restore drill.
- Hosting options: Ethiopian data centre (Ethio Telecom, Safaricom ET, or local providers) for latency and
  data-residency comfort, or a Frankfurt/Nairobi VPS with Cloudflare edge; the plan works for both.

## 10. Data model (key entities)

Translatable models follow the pattern `thing` + `thing_translations(locale, title, slug, excerpt, body,
seo_*, status, published_at, machine_translated, reviewed_by)`. IDs are ULIDs; all tables have
`created_by`, `updated_by`, timestamps and soft deletes where content can be restored.

| Entity | Important fields | Relations |
|---|---|---|
| `posts` / `post_translations` | type (news, press_release, statement, speech, announcement), featured_image_id, is_featured, is_sticky, reading_time, view_count, source_locale | belongsToMany categories, tags, offices, leagues, leaders; hasMany revisions, comments(internal) |
| `pages` / `page_translations` | template, parent_id, order, blocks (JSON) | hasMany children |
| `categories`, `tags` (translatable) | type scope | — |
| `leaders` / `leader_translations` | portrait_id, body (committee), position, rank, term_start, term_end, contacts (JSON), socials (JSON) | belongsTo office; hasMany posts (as speaker) |
| `offices` / `office_translations` | type (branch, woreda, kebele, sector, basic_org), parent_id, head_leader_id, phone, email, address, lat, lng, hours (JSON), cover_image_id | tree; hasMany posts, events, documents |
| `leagues` / `league_translations` | slug, logo_id, head_leader_id | hasMany posts, events, documents |
| `events` / `event_translations` | starts_at, ends_at, timezone, venue, lat, lng, online_url, registration_enabled, capacity, waitlist, agenda (JSON) | hasMany registrations, speakers (leaders) |
| `event_registrations` | name, phone, email, status (registered, waitlisted, checked_in), qr_token | belongsTo event |
| `projects` / `project_translations` | sector, office_id, status, starts_at, ends_at, budget, progress_pct, lat, lng | hasMany statistics, media |
| `statistics` / `statistic_translations` | value, unit, label, source, as_of | polymorphic to projects/pages |
| `documents` / `document_translations` | type, year, file_id (per locale), access (public, members), version, download_count | belongsTo office/league |
| `galleries`, `gallery_items`; `videos` | provider, external_id, thumbnail, is_live, category | — |
| `media` (spatie) | conversions, alt (JSON per locale), credit, focal_x, focal_y, usage | polymorphic |
| `menus`, `menu_items` | location, parent_id, order, type (page, post, url, route), target, label (JSON per locale), visibility | tree |
| `homepage_sections` | key, enabled, order, settings (JSON), curated_ids (JSON) | — |
| `ui_translations` | group, key, text (JSON per locale), context, screenshot_url, updated_by | exported to `lang/*.json` |
| `translation_imports` | locale, file, added, changed, removed, status, snapshot (JSON) | for rollback |
| `glossary_terms` | term (JSON per locale), notes | — |
| `social_sources` | platform, handle, external_id, owner (polymorphic office/league), credentials (encrypted), interval, enabled, last_fetched_at, last_error | hasMany social_posts |
| `social_posts` | external_id, posted_at, text, media (JSON), permalink, metrics (JSON), is_hidden, is_pinned, raw (JSON) | belongsTo source |
| `forms`, `form_fields`, `form_submissions` | schema (JSON), routing (office_id/email), status, assignee_id, notes | — |
| `membership_applications` | personal fields (ID encrypted), woreda/kebele, basic_org_id, phone_verified_at, status, reviewer_id, decided_at, reason | becomes `members` row on approval |
| `members` | member_no, user_id, office_id, basic_org_id, status, joined_at, renewed_at | hasMany notes, activities |
| `volunteers`, `volunteer_assignments` | skills (JSON), availability, hours | — |
| `subscribers` | email, phone, locale, channels, topics (JSON), confirmed_at, unsubscribed_at | — |
| `campaigns`, `campaign_deliveries` | channel, segment (JSON), template, scheduled_at, stats | — |
| `users`, `roles`, `permissions`, `model_has_roles` (+ `scope_type`, `scope_id` on pivot) | 2FA secret, last_login_at, locale | spatie |
| `activity_log` | subject, causer, event, properties (old/new), ip | spatie |
| `redirects`, `settings`, `revisions` | — | — |

Constraints the agent must enforce: unique `(locale, slug)` per translatable table; only one `published`
homepage hero set per locale; `social_posts.external_id` unique per source; applications require
`phone_verified_at` before review; documents with `access = members` never served without auth.

## 11. Performance, security, reliability, SEO, accessibility

**Performance**

- Targets: Lighthouse ≥ 90 (Performance, Accessibility, Best Practices, SEO) on mobile; LCP < 2.5 s,
  INP < 200 ms, CLS < 0.1; TTFB < 400 ms cached, < 800 ms uncached.
- Test on throttled 3G in CI (Lighthouse CI budgets committed to repo).
- Pagination limits, no N+1 (Laravel Debugbar/Telescope locally), indexes verified with `EXPLAIN` on list queries.

**Security**

- OWASP ASVS L2 baseline: CSRF on all forms, strict Content-Security-Policy with nonces, HSTS,
  X-Frame-Options, Referrer-Policy, Permissions-Policy.
- Rate limiting on login, OTP, forms, search; Cloudflare WAF + Turnstile; honeypots.
- Uploads: MIME sniffing, image re-encoding, SVG sanitising, no executable types, stored outside web root
  or on object storage; antivirus scan (ClamAV) optional.
- Encryption at rest for PII fields; TLS 1.2+; secrets never in repo; dependency scanning (Composer audit, Dependabot).
- 2FA for privileged roles; password policy; session invalidation on role change; login/IP alerts.
- Admin on `/admin` with optional IP allow-list or Cloudflare Access; separate admin subdomain optional.
- Audit log retention 2 years; privacy policy aligned with Ethiopia's Personal Data Protection
  Proclamation (No. 1321/2024): consent capture, purpose statements, data export and deletion requests via admin.
- Penetration test checklist before launch; `php artisan security:check` in CI.

**Reliability**

- Health endpoint (`/up` + `spatie/laravel-health`) monitored by Uptime Kuma; alerts via Telegram/email.
- Horizon supervisors with retries and dead-letter handling; failed-job notifications.
- Daily automated backups (DB + media) to off-site bucket, 30-day retention, quarterly restore drill, documented runbook.
- Graceful degradation: if Meilisearch is down, fall back to DB LIKE search; if social APIs fail, show
  cached posts with a "last updated" note; if MT provider fails, editors still publish manually.
- Maintenance page translated; status page optional.

**SEO**

- Per-language URLs with `hreflang` (en, am, om, har, x-default), canonical tags, language sitemaps and a
  sitemap index, `robots.txt`.
- JSON-LD: Organization (PoliticalParty), NewsArticle, Event, Person (leaders), BreadcrumbList, WebSite with SearchAction.
- Open Graph and Twitter cards with auto-generated OG images (title + logo) for posts lacking images.
- Clean slugs, 301 redirects on slug change, 404 monitoring, RSS feeds, Google News-friendly markup,
  Search Console + Bing verification fields in settings.

**Accessibility (WCAG 2.2 AA)**

- Semantic landmarks, skip links, visible focus, keyboard-operable menus/sliders/lightbox, ARIA for live
  regions (ticker, search).
- Contrast ≥ 4.5:1 with the brand palette; text resizable to 200%; no information by colour alone.
- Alt text per language required for featured images (admin validation); captions for videos where
  available; transcripts field for speeches.
- Ethiopic screen-reader checks (NVDA/VoiceOver read Ethiopic text); `lang` attributes switch per element
  when mixing languages.
- Reduced-motion respect; forms with clear errors in the user's language; accessibility statement page.

**Analytics and measurement**

- Privacy-friendly analytics (Plausible/Umami, self-hosted), per-language and per-content-type dashboards
  in admin; download and share counters; UTM support for campaigns; no third-party ad trackers.

## 12. Design system and UX guidelines

Modern, clean, institutional: generous white space, strong typographic hierarchy that works in Ethiopic
and Latin scripts, one accent colour drawn from the party identity, photography-led cards, and no
decorative clutter. Mobile-first because most Ethiopian traffic is on phones over mobile data.

**Brand and colour**

- Primary: Prosperity Party blue (Dire Dawa site uses `#3151ED`; confirm official brand guide from the
  national party). Secondary: a warm accent from the Harari palette (e.g. Harar heritage ochre/terracotta)
  used sparingly for CTAs and highlights. Neutrals: slate greys. Semantic: success/warning/error.
- Tailwind tokens: `--color-primary-50…950`, `--color-accent-*`; dark-mode variants.
- Logo lock-ups: national PP mark + "Harari Branch Office" wordmark in four languages; favicon and PWA icons; OG template.

**Typography**

- Latin: Inter (variable) for UI and body; optional display serif for headlines (e.g. Fraunces) if the
  client wants a more editorial feel.
- Ethiopic: Noto Sans Ethiopic (variable, subset), with Abyssinica SIL fallback; headline sizes slightly
  reduced vs Latin to balance visual weight; line-height 1.7 body, 1.25 headings.
- Type scale: 12/14/16/18/20/24/30/36/48; body 16–18 px; max line length 70ch.

**Layout and components**

- 12-column grid, 1280 px max container, 8 px spacing scale, rounded 8–12 px, subtle 1 px borders rather than heavy shadows.
- Component library (Blade components, documented in a `/styleguide` route in non-production): buttons,
  badges (content type, language, status), cards (post, leader, office, event, document, social), hero
  slider, ticker, stats counters, timeline, accordion, tabs, breadcrumb, pagination, filters bar, search
  overlay, language switcher, date tile (Ethiopian + Gregorian), map, lightbox, PDF viewer, forms (inputs,
  OTP, file upload, stepper), toast, empty states, skeleton loaders.
- Imagery: 16:9 for posts, 1:1 for portraits, 3:2 for offices; focal-point cropping; consistent duotone overlay only on hero.
- Motion: subtle (150–250 ms), respects reduced-motion.

**UX rules**

- Language switcher always visible, labelled in each language's own script, remembers choice.
- Dates always dual-calendar; numbers formatted per locale.
- Content-type badge and office/league tag on every card so a portal with many sources stays legible.
- Max two CTAs per section; forms short, with progress indicator; SMS OTP flow under 60 seconds.
- Every list has filters, sort, and an empty state with a next action.
- Admin: Filament defaults with the brand colour, dense tables, keyboard shortcuts, inline help text per field in the admin's language.

**Deliverables for the design phase**

- Figma (or Penpot) file: tokens, components, homepage, listing, detail, leader, office, event, document,
  social hub, search, forms, member portal, admin theming; mobile and desktop.
- Styleguide page in the app generated from the same tokens, used for visual regression tests (Playwright screenshots).

## 13. Delivery phases, milestones and acceptance checklist

Nine phases (0–8) over roughly 18 weeks for a two-to-three-person team using an AI coding agent; each
phase ends with a demo on staging and the gate criteria below. Phases 0–5 (12 weeks) are the launch scope;
6–8 follow launch.

| Phase | Weeks | Scope | Gate (done when) |
|---|---|---|---|
| 0. Discovery & design | 1–2 | Confirm sitemap, content inventory, brand assets, hosting, API access (Meta, YouTube, Telegram bot, SMS gateway), Figma design | Client sign-off on sitemap + designs; accounts created |
| 1. Foundation | 2–3 | Laravel skeleton, Docker, CI, auth, RBAC, audit log, settings, locale routing, translation layer (JSON + DB), Ethiopian calendar, media library, Filament panel shell | Admin login with 2FA, roles seeded, language switch works on a hello page in 4 languages, CI green |
| 2. Content core | 4–6 | Posts, pages, categories/tags, leaders, offices, leagues, menus, homepage builder, dashboard v1 with widget registry and role defaults, workflow engine, events + webhooks skeleton, revisions, scheduled publishing, search, SEO, sitemaps, public templates | Editor publishes a 4-language post end-to-end; Lighthouse ≥ 90 on homepage and post |
| 3. Media, events, documents, projects | 7–8 | Galleries, videos, live, events + registration + SMS/email, documents library + viewer, projects + statistics, forms builder + inbox | All public pages from section 4 exist with real seed content |
| 4. Social hub & translation tooling | 9–10 | Social sources + fetchers + hub page; Harari grid editor, import/export, missing report, glossary; MT drivers for am/om with review flow | Hub shows live posts from ≥ 3 platforms; Harari JSON round-trips (export → edit in Excel → import) without loss |
| 5. Hardening & launch | 11–12 | Content migration/entry, native-speaker review of am/om strings, accessibility audit, pen-test checklist, backups + restore drill, monitoring, performance tuning, training for editors, launch | Acceptance checklist 100%; go-live |
| 6. Membership & volunteers | 13–14 | Application flow with OTP, review queue, members directory, Excel import, volunteer module, member portal (login, profile, members-only documents) | First real applications processed |
| 7. Campaigns & engagement | post-launch | Newsletter/SMS campaigns, preference centre, media contacts distribution, Telegram notifications to editors | First campaign sent with delivery report |
| 8. Extensions | post-launch | Public API for a mobile app, Fayda/eSignet login for members, posting to social networks from admin, donations module (if permitted), Octane | As prioritised |

### Acceptance checklist (launch)

- [ ] All pages in section 4 render in en/am/om/har with no raw translation keys visible
- [ ] `php artisan lang:missing` reports 0 missing for am/om; har coverage reported in admin
- [ ] Harari JSON export → edit → import round-trip tested, with rollback
- [ ] Editorial workflow (draft → review → translate → schedule → publish) demonstrated by a non-developer
- [ ] Every role in section 8 tested with a dedicated test account; denied actions return 403 and are logged
- [ ] 2FA enforced for Admin/Publisher; audit log shows diffs
- [ ] Social hub fetches from Facebook, Telegram, YouTube; token expiry alert works
- [ ] Lighthouse mobile ≥ 90 on homepage, listing, post, office, event, document pages
- [ ] WCAG 2.2 AA audit (axe + manual keyboard/screen-reader pass) with no critical issues
- [ ] hreflang, sitemaps, JSON-LD validated (Google Rich Results, hreflang checker)
- [ ] Backups running; restore drill completed and documented
- [ ] Monitoring and error tracking live; alert routing tested
- [ ] Security headers A+ (securityheaders.com), dependency audit clean, uploads hardened
- [ ] Pest test suite ≥ 80% coverage on domain logic; Dusk smoke tests for publish, search, forms, language switch
- [ ] Editor training delivered; admin user manual (with screenshots) in English and Amharic
- [ ] Privacy policy, terms, accessibility statement published in 4 languages

## 14. Inputs needed from the Harari PP team

The agent can start phases 1–2 without these, but launch quality depends on them; collect them during Phase 0.

- [ ] Official name of the branch in all four languages and the approved logo files (SVG) plus the national PP brand guide (colours, fonts, usage rules)
- [ ] Organisational chart: committees, positions, current office holders with portraits and short bios
- [ ] Full list of offices and structures (woredas/kebeles, sector offices, basic organisations) with contacts and addresses
- [ ] Core documents: party programme, manifesto, bylaws, election programme, recent reports, speeches (PDF/Word)
- [ ] Existing content to migrate (Facebook/Telegram archives, any old site export, photo and video archives)
- [ ] Social accounts to aggregate and admin access to create API apps (Meta Business, YouTube/Google Cloud, Telegram bot ownership, X developer account if paid tier is approved)
- [ ] Harari translators (two or more native speakers) and a decision on script (Ethiopic recommended; confirm if Latin or Arabic-script variants must also be shown)
- [ ] Amharic and Afaan Oromo reviewers for the shipped UI strings
- [ ] SMS gateway choice and account (Ethio Telecom SMS, Africa's Talking, or Twilio); sender ID
- [ ] Email domain and DNS access (SPF/DKIM/DMARC) and a transactional email provider
- [ ] Hosting decision (in-country vs cloud), domain(s) (e.g. `harariprosperity.org` / `.et`), Cloudflare account
- [ ] Legal: membership form fields required by party bylaws, data-retention rules, whether donations are permitted, privacy officer contact
- [ ] Launch date and any events (congress, anniversaries, Harar Day) that content should be ready for

## 15. The AI agent prompt

The master prompt lives in `CLAUDE.md` at the repository root (adapted to the decisions in
`docs/DECISIONS.md`). Drive the work phase by phase with the phase prompts below.

### 15.2 Phase kick-off prompts (send one at a time)

**Phase 0/1 — Foundation**: Read docs/PLAN.md and CLAUDE.md. Produce docs/phases/00-plan.md, then implement
Phase 1: Laravel project with Docker Compose, GitHub Actions CI (Pint, Larastan, Pest, Lighthouse CI),
Filament panel at /admin with 2FA, spatie permission + activitylog + shield, roles/permissions seeder from
PLAN.md §8, settings module, locale routing (/en /am /om /har with cookie + Accept-Language detection),
DB-backed UI translations exported to lang/*.json, `php artisan lang:missing` command, Ethiopian calendar
helper and Blade component, media library with WebP/AVIF conversions, base layout with header/footer/menu
placeholders, fonts (Inter + Noto Sans Ethiopic subset), tokens.css and /styleguide. Finish with tests and
a demo script. Stop and report when the gate in PLAN.md §13 Phase 1 is met.

**Phase 2 — Content core**: Implement Phase 2 from PLAN.md: posts (all types) with translations, block
editor, revisions, scheduled publishing, categories/tags, leaders, offices (tree), leagues, menus editor,
homepage builder, dashboard v1 (widget registry, per-role defaults, drag/resize layouts, first 10 widgets
from PLAN.md §7), workflow engine with the default editorial flow, domain events + webhooks skeleton,
public templates for home/listing/detail/leader/office/league, search per locale, SEO (hreflang, sitemaps
per locale, JSON-LD, OG images), RSS. Seed demo content in am/en/om. Verify Lighthouse mobile ≥ 90 on home
and post. Report.

**Phase 3 — Media, events, documents, projects**: galleries with lightbox, videos
(YouTube/Facebook/Telegram) and Live page, events with registration/capacity/waitlist/iCal/QR check-in and
SMS+email reminders via the SmsGateway interface (log driver for now), documents library with in-browser
PDF viewer and versioning, projects + statistics with counters, forms builder with inbox/tickets/Turnstile
and auto-replies per language. Seed demo data. Report.

**Phase 4 — Social hub & translation tooling**: SocialFetcher drivers for Facebook Graph API, Telegram
(bot + t.me/s fallback), YouTube Data API, X (optional), TikTok/Instagram via oEmbed; scheduled fetch
jobs; social_posts moderation UI; public /{locale}/social hub with filters; homepage teaser and
office/league embeds. Translations module: grid editor, missing/changed filters, context, export
JSON/CSV/XLSX, import with diff preview + placeholder validation + rollback, glossary, Translate with AI
(google + llm drivers) for am/om with review badges, translation queue dashboard. Prove the Harari
round-trip with an automated test. Report.

**Phase 5 — Hardening & launch**: complete the dashboard widget catalogue, analytics rollups + report
builder with scheduled delivery, automation rules, notifications centre and governance area; then security
headers + CSP nonces, rate limits, upload hardening, backups and health checks, Sentry, queue tuning,
response cache + Cloudflare purge on publish, accessibility audit fixes (axe + keyboard), full browser
smoke suite, performance pass to budgets, deployment scripts (zero-downtime), runbook, admin user manual
with screenshots (en + am). Walk the acceptance checklist in PLAN.md §13 and produce docs/launch-report.md.

**Phase 6 — Membership & volunteers**: membership application wizard with SMS OTP, encrypted ID fields,
review queue with approve/reject + notifications, members directory with Excel import and member number
generation, volunteer module, member portal (login, profile, card PDF, members-only documents). Add
policies, tests, and manual chapter. Report.

### 15.3 Review prompt (run after each phase)

Act as an independent reviewer. Audit the code produced in this phase against docs/PLAN.md: missing
features, hard-coded strings (grep for text in Blade not wrapped in `__()`), N+1 queries, missing policies
on routes/resources, missing tests, accessibility issues, security gaps, and performance regressions.
Produce docs/reviews/phase-NN.md with a prioritised fix list, then fix all high and medium items.

## 16. Appendix: Harari translation JSON format and sample

Two files are produced for translators: the **runtime file** `lang/har.json` (what Laravel loads: flat key
→ string) and the **workbook** (JSON or XLSX) that carries English and Amharic reference text and context
so a translator can work offline. Import accepts either; the importer reads only the `har` column/field.

**Runtime file `lang/har.json`** (flat, UTF-8, keys identical to `en.json`; empty string = not yet
translated, which triggers fallback to Amharic):

```json
{
  "nav.home": "",
  "nav.about": "",
  "nav.news": "",
  "nav.offices": "",
  "nav.leagues": "",
  "nav.programmes": "",
  "nav.get_involved": "",
  "nav.resources": "",
  "nav.contact": "",
  "home.hero.cta": "",
  "home.latest_news": "",
  "home.upcoming_events": "",
  "post.published_on": "",
  "post.also_available_in": "",
  "post.read_time": "",
  "forms.membership.title": "",
  "forms.membership.step1.title": "",
  "forms.common.submit": "",
  "footer.newsletter.title": "",
  "footer.newsletter.placeholder": "",
  "footer.copyright": ""
}
```

**Translator workbook `har-workbook.json`** (same keys, with reference text and context; `placeholders`
lists tokens that must appear unchanged in the Harari value):

```json
{
  "meta": {
    "locale": "har",
    "language": "Harari (Gey Sinan)",
    "script": "Ethi",
    "source_locale": "en",
    "reference_locale": "am",
    "exported_at": "2026-10-18T12:00:00Z",
    "exported_by": "admin@harariprosperity.org",
    "version": 3,
    "total": 1180,
    "translated": 412
  },
  "strings": [
    {
      "key": "nav.news",
      "en": "News & Media",
      "am": "ዜና እና ሚዲያ",
      "har": "",
      "context": "Main navigation, second item",
      "screenshot": "https://portal.example/admin/translations/shots/nav.png",
      "placeholders": [],
      "max_length": 24
    },
    {
      "key": "post.published_on",
      "en": "Published on :date",
      "am": "የታተመው :date",
      "har": "",
      "context": "Byline under article title; :date is replaced by a dual-calendar date",
      "screenshot": null,
      "placeholders": [":date"],
      "max_length": null
    },
    {
      "key": "post.read_time",
      "en": "{1} :min minute read|[2,*] :min minutes read",
      "am": "{1} የ:min ደቂቃ ንባብ|[2,*] የ:min ደቂቃዎች ንባብ",
      "har": "",
      "context": "Laravel pluralisation syntax must be kept: {1} ... | [2,*] ...",
      "screenshot": null,
      "placeholders": [":min"],
      "max_length": null
    }
  ]
}
```

**Import rules the agent must implement**

1. Accept `har.json` (flat) or the workbook (`strings[]`), or CSV/XLSX with a `key` column and a `har` column.
2. Normalise Unicode to NFC; trim; reject values containing HTML tags unless the English source has them.
3. Validate placeholders and plural syntax against `en`; list invalid rows in the preview and skip them.
4. Show a diff: added, changed (old → new), unchanged, removed-in-file (never deletes keys; a key missing
   from the file is left as is).
5. Options: *Fill missing only* (default) or *Overwrite all*.
6. Apply atomically in one transaction, write a snapshot to `translation_imports`, regenerate
   `lang/har.json`, clear translation cache, log the activity. Rollback restores the previous snapshot.
7. The same pipeline serves `am.json` and `om.json` so reviewers can correct machine translations in bulk.

**Content translation export (optional, phase 4)**: a per-post "Export for translation" produces a JSON
with `title`, `excerpt`, `body` (HTML with block ids), `seo_title`, `seo_description` for a target locale;
import writes the translation row as *In review*. This lets Harari translators work on articles offline too.
