# Changelog

All notable changes. Dates are Gregorian.

## Unreleased

### Phase 4, step 4.2 — YouTube, Facebook and posts by address (2026-10-20)

- YouTube channels are read from their public feed; an @name is turned into the channel id once with
  the YouTube key (ADR-0031).
- Facebook pages are read through the Graph API with the page's access token, set per account
  ("Page access token") or under Integrations.
- "Add a post by its address" for TikTok, Instagram and X (and any post the reading missed), with the
  title and picture the network publishes for it.

### Phase 4, step 4.1 — social sources and posts (2026-10-20)

- Admin → Social media → Accounts: the Telegram, YouTube, Facebook, TikTok, Instagram and X accounts of
  the branch, its offices and leagues, read every so often (ADR-0031). Telegram channels are read from
  their public pages; "Read now" reads one at once; errors are shown per account.
- Admin → Social media → Social posts: every post read, with its first picture copied into the library;
  hide a post or pin it to the top.
- Phase 4 plan in `docs/phases/04-social-translation.md`.

## v0.3.0 — Phase 3 complete (2026-10-20)

- Sample content is on staging; Lighthouse audits a sample event and a sample document page; Phase 3
  report in `docs/phases/03-media-events-documents.md`.
- Maps on office, event and project pages load from OpenStreetMap only when the reader chooses "Show
  map" (ADR-0030); before, they pulled in about 300 KB of OpenStreetMap scripts on every visit.

### Phase 3, step 3.9 — gate preparation (2026-10-20)

- `php artisan portal:sample-content` adds clearly labelled sample events (one with registration),
  documents (programme with a PDF, report, vacancy), projects with a sector and statistics, and a
  feedback form; `--remove` deletes exactly these; refused in production.
- A test checks that every page Phase 3 adds renders in the four languages with that content.
- Manual chapter 3 "Events, documents and forms" in English and Amharic.
- Lighthouse also audits the events and documents pages.

### Phase 3, step 3.8 — press kit and Get involved (2026-10-20)

- Admin → Content → Press kit: boilerplate and contact per language, contact email and phone, logo
  files and the press enquiry form (ADR-0029).
- `/{lang}/press` with the boilerplate, logos to download, the colour palette, leadership portraits,
  recent press releases and the media contact.
- `/{lang}/get-involved` with events, feedback forms, contact and vacancies; membership, volunteering
  and newsletters are marked as coming soon.
- Both pages are in the sitemaps and can be added to menus.

### Phase 3, step 3.7 — forms builder, tickets and inbox (2026-10-20)

- Admin → Inbox and forms → Forms: build contact, feedback, petition, press and job application forms
  with questions in every language (text, choices, dates, agreement, files), the office they go to, an
  address to notify, the answer-within time and the automatic reply per language (ADR-0028).
- Public forms at `/{lang}/forms/{slug}` and the contact page `/{lang}/contact` with the chosen form,
  hotline, address, opening hours, office cards and social links; protected like event registration.
- Each message gets a reference (`HPP-XXXX-XXXX`) and an automatic reply; `/{lang}/tickets` shows its
  status to whoever has the reference, never its content.
- Admin → Inbox: tickets of one's offices with status, assignee, the response clock (answer by, overdue,
  answered after), internal notes, replies by email, attachments and the history of each ticket.
- Everything people write is encrypted at rest.

### Phase 3, step 3.6 — projects, statistics and achievements (2026-10-20)

- Admin → Content → Projects: sector, office or woreda, status and progress, dates, budget (shown only
  when allowed), map pin, cover and photo album; title, summary, location and description per language
  (ADR-0027). Sectors are categories "used for project sectors".
- Admin → Content → Statistics: a number with label, unit and source per language, as-of date and
  source link, for a sector, a project or the whole region. A new "Statistics" block shows chosen
  numbers inside any page or post.
- `/{lang}/projects` with filters by sector, status and office; project pages with progress, numbers,
  photos and map.
- `/{lang}/achievements`: projects by status, regional numbers, and per sector its numbers, recently
  completed projects and a simple chart.
- Homepage "Programmes and projects" section; projects in search, the sitemaps and the menus.

### Phase 3, step 3.5 — documents library and notices (2026-10-20)

- Admin → Content → Documents: programmes, manifestos, bylaws, reports, policies, speeches, forms and
  publications, plus vacancies, tenders and notices with a deadline; a file per language, year, date
  issued, office or league and related documents (ADR-0026).
- A new file keeps the old one as an earlier version with a note; earlier versions can be downloaded
  and made current again.
- `/{lang}/documents` with filters by type, year and office; each document page shows PDFs in the
  page, the download button with type, size and version, earlier versions and related documents.
  Readers of a language without its own file get another language's file, with a note.
- `/{lang}/notices` lists open vacancies, tenders and notices by deadline and the recently closed ones.
- Downloads are counted (once an hour per visitor, robots left out); documents are in search, the
  sitemaps and the menus.

### Phase 3, step 3.4 — event registration (2026-10-20)

- Events can take registrations: places, a waiting list that moves up by itself, and a closing time.
  Visitors register with a name and a phone number or email address; Ethiopian numbers are accepted
  as typed (ADR-0025).
- Each registration gets a ticket page with a QR code, an "Add to calendar" link and a way to give the
  place back. Staff scan the code into Admin → Check-in and let people in with one tap.
- Confirmation, waiting-list, moved-up, cancellation and day-before reminder messages by email and by
  text message (written to the log until an SMS provider is chosen).
- Admin: a Registrations tab on each event with check-in, cancel and CSV download, for staff with the
  registrations permission (Publishers and Membership Officers by default).
- Names, phone numbers and email addresses are encrypted and deleted 180 days after the event. Forms
  are protected by a hidden field, a time check, rate limits and Cloudflare Turnstile once configured.

### Phase 3, step 3.3 — events and calendars (2026-10-20)

- Admin → Content → Events: start and end in Addis Ababa time (or all day), venue and address, map pin
  or online link, an agenda and summary per language, speakers from the leaders, cover picture, office
  or league; each language published with one switch (ADR-0025).
- `/{lang}/events` lists upcoming events, with past events and a month view in both calendars; each
  event page shows the agenda, speakers and map, with "Add to calendar" (.ics) and Google Calendar.
- `/{lang}/events/feed.ics` lets anyone subscribe to the upcoming events in their calendar app.
- Homepage "Upcoming events" section; events in search, the sitemaps and the menus.

### Phase 3, step 3.2 — videos and live (2026-10-20)

- Admin → Content → Videos: paste a YouTube, Facebook or Telegram address, give a title and description
  per language, a playlist, the date recorded, the office or league; mark the video of the week; mark a
  live broadcast with its start (and end) time (ADR-0024).
- `/{lang}/videos` with playlists; each video page plays YouTube with the privacy-enhanced player
  (nothing from YouTube loads until the page is opened) and links to Facebook and Telegram videos.
  YouTube pictures are copied into the library once, so listings load nothing from YouTube.
- `/{lang}/live` shows the broadcast that is on now, the next one, and recent videos.
- Homepage "Video of the week" section; video playlists are categories "used for video playlists";
  menus can link to videos and the live page; videos are in the sitemaps.
- Live broadcast times and dashboard announcement times are entered in Addis Ababa time, like
  post schedules.

### Phase 3, step 3.1 — photo galleries (2026-10-20)

- Admin → Content → Photo albums: albums of library photos in order, a caption per language for each
  photo, a cover (the first photo unless chosen), the date taken, the office or league, and whether
  visitors may download the full-size photos. Each language is published with one switch (ADR-0023).
- `/{lang}/gallery` lists the albums; each album page shows its photos in a grid and opens them one by
  one with previous and next, captions and credits, without any JavaScript.
- A homepage section shows photos from the newest album; menus can link to the gallery; albums are in
  the sitemaps.
- Phase 3 plan in `docs/phases/03-media-events-documents.md`.

### Imported stories: Harari and publishing (2026-10-20)

- Stories written in Harari are recognised (own words and the ዚ-/ዪ- forms) and arrive in the Harari
  tab instead of the Amharic one.
- Very short messages are never taken for duplicates.
- `php artisan portal:publish-imported` publishes the imported stories that are still drafts, as a
  Super Admin, once someone has decided they may go live: `--dry-run` lists them, `--harari=<id>`
  moves a story from the Amharic tab to Harari first, `--skip=<id>` leaves one as a draft.

## v0.2.0-content-core — Phase 2 complete (2026-10-20)

Gate of PLAN.md §13 Phase 2 met; report in `docs/phases/02-content-core.md`.

### Phase 2, step 2.12 — phase gate (2026-10-20)

- Staff manual, chapter 2: "Publishing a post" in English and Amharic (`docs/manual/02-...`).
- `php artisan portal:sample-post` publishes one labelled sample story in English, Amharic and Afaan
  Oromo for checking staging end to end (refused in production; `--remove` deletes it).
- Lighthouse also audits a post page after every deploy.
- Imports also skip a story the same channel posts twice.

### Phase 2, step 2.11 — stories from the public channels (2026-10-19)

- Every half hour, new stories from the branch's Telegram channel and the regional communication
  office's channel arrive as **draft** news posts: title and text as written, photos downloaded and
  credited, the source link and original time kept (ADR-0022). Nothing is published automatically.
- The office's reposts of branch stories are recognised and skipped.
- Posts → filter "Imported from channels"; the post editor shows where a story came from with a
  reminder to check names and facts; published stories show "Source: …" with the link.
- Administration → Content sources: admins see when each channel was last read and any problem, and
  can import now; Super Admins can switch a channel on or off and set its language.

### Phase 2, step 2.10 — dashboard v1 (2026-10-19)

- The admin home is a dashboard of widgets with a period (7, 30, 90 days, 12 months or this fiscal
  year), language and office filter on top (ADR-0021).
- Widgets: announcements, key figures with the change against the period before, needs attention,
  quick actions, editorial pipeline per language, translation coverage, site searches, media storage,
  background work and recent activity. Each person sees only the widgets their role allows.
- **Customise dashboard**: add, remove, reorder and resize widgets; **Reset** goes back to the standard
  layout for the role.
- Administration → Announcements (Super Admins): notices shown on every staff dashboard, with start and
  end times.

### Phase 2, step 2.9 — site search (2026-10-19)

- A search box in the header of every page and a search page (`/{lang}/search`) finding news, pages,
  people, offices and leagues published in the reader's language; Harari readers also find Amharic
  items (ADR-0020).
- Every word typed must appear, in any order; Amharic spelling variants match (ሐ/ሀ/ኀ, ሠ/ሰ, ዐ/አ,
  ፀ/ጸ), and Amharic words typed in Latin letters work too ("medemer" finds መደመር).
- Admin → Content → Site searches (staff who see analytics): what visitors searched for, how often,
  and which searches found nothing.
- Search engines are told about the site search; search answers are never cached or indexed.

### Phase 2, step 2.8 — homepage builder (2026-10-19)

- The homepage is built from content: a hero with hand-picked or featured stories (or a welcome),
  breaking-news ticker, message from the leadership, latest news, press releases and statements,
  numbers, core values, leagues and forums, and related links (ADR-0019).
- Admin → Content → Homepage: drag the sections into order, switch each on or off, choose how many
  items to show, hand-pick posts for the ticker and hero, write the leader's message, numbers, values
  and links in each language, change a section's heading per language and limit it to some languages.
- The core values start with Medemer, multinational unity, democracy, national sovereignty,
  inclusiveness, patriotism and solidarity in English, Amharic and Afaan Oromo.

### Phase 2, step 2.7 — offices, leaders and leagues (2026-10-19)

- Admin → Organisation: offices (kind, office above, head, phone, email, map pin, cover photo, and per
  language name, summary, text, address and working hours), leaders (body, order, office, league, term,
  portrait, public contacts and social links, and per language name, position, introduction and
  biography) and leagues and forums (chair, logo, cover photo, contacts). Each language is published
  with one switch (ADR-0017).
- Posts can be linked to offices, leagues and the people who gave a speech or statement; copies keep
  the links.
- `/{lang}/leaders`: the leadership grouped by Executive Committee, Central Committee, Secretariat,
  heads of offices and leagues; each person has a profile with term, contacts, social links and the
  posts they appear in.
- `/{lang}/offices`: the office directory with a filter by kind and a search by name; each office page
  shows the head, contacts, working hours, a map pin, the leadership, the offices under it and its
  news.
- `/{lang}/leagues`: leagues and forums with their chair, leadership and news.
- `/{lang}/structure`: the organisational chart built from the office tree.
- Menu items can point to the leadership, office and league directories and the structure; all of
  them are in the sitemaps, with structured data on each profile.

### Two-step sign-in made a switch (2026-10-19)

- Administration → Security (Super Admins only): "Make two-step sign-in compulsory for senior roles".
  Off at first: everyone can sign in with their password alone. When on, Super Admins, Admins and
  Publishers must set up an authenticator app before using the admin, as before (ADR-0018).
- The page shows how many people in those roles have not set up the app yet; changes to the switch are
  in the audit log.
- Anyone can still turn the authenticator app on or off for their own account under Profile.

### Phase 2, step 2.6 — pages and menus (2026-10-19)

- Admin → Content → Pages: static pages (About, History, Vision …) with a tab per language, the block
  editor, a layout (text page, wide page, landing page with a large photo), an optional page above
  (up to three levels) and a featured photo. Publishers publish each language with one switch.
- Pages live at their nested address in each language (`/en/about/history`); a page found under the
  wrong parent is sent to its real address, a renamed page keeps its old address working, and pages
  can never take the address of a fixed section such as `/news` (ADR-0016).
- Admin → Content → Menus: the main menu, the footer links and the top bar, each with items (and one
  level of sub-items) pointing to a page, a post, the news listing, a site path or an https address,
  with a label per language. Items whose page or post is not published in a language are left out.
- The header shows the main menu (Home and News until one is made; sub-items open without
  JavaScript); the footer shows the quick links.
- Published pages are listed in the sitemaps.

### Phase 2, step 2.5 — search engines and sharing (2026-10-19)

- Every public page carries Open Graph and Twitter card tags and structured data describing the party
  (PoliticalParty, with its social accounts and contacts from Settings); news posts add NewsArticle
  and BreadcrumbList data (ADR-0015).
- Share images: posts use their featured photo; pages and posts without one get a generated image
  (1200×630, site colours, title in Latin or Ethiopic script), made once and kept.
- Sitemaps: `/sitemap.xml` lists one sitemap per language (`/sitemaps/{lang}.xml`) with the home and
  news pages and every published post, each with its other languages.
- `/robots.txt` points to the sitemap on the live site and closes the whole site to search engines
  everywhere else (staging included).
- Redirects (Admin → Content → Redirects, publishers): permanent or temporary moves from an old
  address to a new one, with a visit counter. Changing the address of a published post adds one
  automatically; chains and loops are prevented.

### Phase 2, step 2.4 — public news pages (2026-10-19)

- `/{lang}/news`: news, press releases, statements, speeches and announcements with filters by type,
  category and tag, cards with type, date in both calendars and summary, and a translated pager.
  Harari pages also list Amharic posts that have no Harari version, marked as Amharic (ADR-0014).
- `/{lang}/news/{slug}`: kicker, title, summary, dates, reading time, featured photo with credit,
  the text with its photos, galleries, videos, notes and files, topics, "also available in", share
  links (no third-party scripts) and related posts; the language switcher goes to the same post or to
  the news listing.
- RSS (`/{lang}/feed.xml`) and JSON Feed (`/{lang}/feed.json`) per language.
- Signed preview links (7 days) for any language of a post, drafts included, from the post editor.
- "News" in the main menu; reading styles for post text.

### Phase 2, step 2.3 — editorial workflow (2026-10-19)

- Workflow buttons in each language tab of a post: submit for review, send back (with a required
  comment), mark as reviewed, publish, schedule, unpublish, archive and reopen; each person only sees
  the moves they may make from the current status (ADR-0013).
- Scheduled languages go live within a minute of their time (`content:publish-due` in the scheduler).
- Internal comments on posts; send-back reasons are kept with the post.
- Notifications in the admin bell and by email: reviewers when a post is submitted, publishers when it
  is reviewed, the author when it is sent back, published, scheduled, taken offline or archived.
- Every move is in the audit log and keeps a version.
- Webhooks (Super Admin): signed HTTPS notifications to other systems when a post is published,
  scheduled, taken offline or archived, with a delivery log and retries; private addresses refused.

### Phase 2, step 2.2 — posts (2026-10-19)

- Admin → Content → Posts: news, press releases, statements, speeches and announcements, with a tab
  per language (title, kicker, address, summary, text, status, publication date, search-engine fields).
- Block editor with headings, lists, quotes, tables and links, plus library photos with caption and
  credit, photo galleries, YouTube videos (privacy-enhanced, lazy-loaded), highlighted notes and files.
- Each language has its own status (draft, in review, translating, scheduled, published, archived);
  only publishers publish, schedule or archive; translators change the text only.
- Featured image from the media library; it needs alt text in every live language among English,
  Amharic and Afaan Oromo. Images and files used by a post cannot be deleted from the library.
- Categories, tags, "featured" and "pinned" flags; word count and reading time per language.
- Versions of every language with a paragraph-by-paragraph comparison and restore (text only; status,
  date and address stay). Duplicate a post as a new draft. List with languages and statuses, filters
  for type, status, category and missing language, soft delete and restore.

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
