# Architecture decision records

Each record: context, decision, consequences. Newest last. These records win over `docs/PLAN.md`.

## ADR-0001 — Laravel 13 + Filament 5 instead of Laravel 12 + Filament 4 (2026-10-18)

**Context.** PLAN.md names Laravel 12, Filament 4 and Livewire 3. On the build date the supported lines are
Laravel 13 (PHP 8.3+), Filament 5 (stable 5.10.x, built on Livewire 4) and Pest 4. Every package the plan
names has a release that supports Laravel 13: bezhansalleh/filament-shield 4.3 (filament ^4|^5),
spatie/laravel-translatable 6.14, spatie/laravel-translation-loader 2.8, spatie/laravel-permission,
spatie/laravel-activitylog, andegna/calender 2.4 (php >= 7.3).

**Decision.** Laravel 13 on PHP 8.3, Filament 5, Livewire 4, Tailwind 4, Pest 4, Larastan 3. Composer's
platform is pinned to PHP 8.3 so the lock file always installs on the server.

**Consequences.** No framework upgrade before launch. If the hosting PHP ever drops to 8.2, Laravel 12 +
Filament 5 is the fallback (both support 8.2).

## ADR-0002 — cPanel-compatible drivers for staging (2026-10-18)

**Context.** The owner chose to run staging at https://hararipp.orcamind.dev on an existing shared cPanel
account (LiteSpeed web server, Cloudflare in front). Shared cPanel offers PHP, MySQL, cron and SSH, but no
Redis, Meilisearch, Horizon, Supervisor or Docker. The plan's production stack needs those.

**Decision.** Every infrastructure dependency is a driver chosen in `.env`, never hard-coded:

| Concern | Staging (cPanel) | Later VPS / production option |
|---|---|---|
| Cache | `CACHE_STORE=database` | `redis` |
| Sessions | `SESSION_DRIVER=database` | `redis` |
| Queue | `QUEUE_CONNECTION=database`, drained by cron `queue:work --stop-when-empty` every minute | `redis` + Horizon |
| Search | `SCOUT_DRIVER=database` (MySQL FULLTEXT / LIKE) | `meilisearch` |
| Mail | SMTP on the cPanel mail server | Mailgun / SES |
| Media | `public` disk on the account | S3-compatible (R2/B2/MinIO) |
| Full-page cache | Cloudflare edge + `Cache-Control: s-maxage` | same |
| PHP | CloudLinux `alt-php83` for this domain only (MultiPHP); the cPanel EasyApache builds stop at 8.2 | distribution PHP 8.3+ |

Tests run against MySQL 8 with array/sync drivers. Features that need Horizon (dashboard) or Meilisearch
(typo tolerance) degrade gracefully, as PLAN.md §11 already requires.

**Consequences.** No code change is needed to move to a VPS with Redis and Meilisearch; only `.env`.
cPanel stores the per-domain PHP handler in `public/.htaccess`, so the deploy never overwrites that file
and rebuilds it from cPanel's blocks plus Laravel's rules.
Long-running jobs must finish within one cron cycle (`--max-time=50`).

## ADR-0003 — Deploy by rsync from GitHub Actions, no artifact branches (2026-10-18)

**Context.** The owner wants full CI/CD from the GitHub repo to hararipp.orcamind.dev, and to be the sole
author of every commit in the repository. An existing project on the same account publishes bot-authored
artifact branches, which would add non-owner commits.

**Decision.** `.github/workflows/deploy.yml` runs after CI succeeds on `main`. It builds the release on the
runner (`composer install --no-dev`, `npm ci && npm run build`), `rsync`s it over SSH into the site folder
(never touching `.env`, `storage/`, `public/storage`, `bootstrap/cache/`), then runs
`scripts/deploy/release.sh` on the server (maintenance window with an up-trap, migrate, caches, queue
restart, permission fix, previous-release snapshot for rollback) and `scripts/deploy/smoke.sh` against the
live URL. A failed smoke test restores the previous release and fails the job. No commits are created.

**Consequences.** The deploy key lives only in GitHub secrets and the account's `authorized_keys`. The
first install (database, `.env`, cron) is a one-time provisioning step recorded in
`docs/runbook/server.md`.

## ADR-0004 — MySQL for tests, not SQLite (2026-10-18)

**Context.** Ethiopic text, JSON columns, FULLTEXT indexes and `utf8mb4_0900_ai_ci` collation behave
differently in SQLite.

**Decision.** Pest runs against MySQL 8 (`harari_test`) locally in Docker and as a service container in CI.

**Consequences.** Tests need the MySQL container running; `scripts/task.py test` starts it.

## ADR-0005 — Own permission registry and role scopes instead of filament-shield (2026-10-18)

**Context.** PLAN.md lists `bezhansalleh/filament-shield` and asks for fine-grained permissions named like
`posts.publish` and `translations.har.edit`, plus scopes that narrow a role to an office, league or
language. Shield generates `Action:Model` permissions and policy stubs per resource; it cannot express
module-level permissions for screens that do not exist yet, nor scopes.

**Decision.** `App\Domain\Access\Support\PermissionRegistry` is the single list of permissions
(`module.action`, plus `translations.<locale>.edit`), `App\Domain\Access\Enums\SystemRole` holds the 13
roles of PLAN.md §8 with their default grants, and `portal:sync-permissions` (run on every deploy) creates
what is missing without undoing grants a Super Admin changed. Scopes live in a `role_scopes` table
(user, role, scope type, value) rather than extra columns on `model_has_roles`, because that table's
primary key would forbid one role scoped to two offices. Policies are hand-written and deny by default;
the Filament panel runs with `strictAuthorization()`. spatie/laravel-permission 8 does the storage and
checks; Shield is not installed.

**Consequences.** Scope-sensitive checks must call `hasPermissionInScope()` (plain `can()` means
"anywhere"). New modules add their permissions to the registry and their defaults to `SystemRole`.

## ADR-0006 — Two-factor login required only for privileged roles (2026-10-18)

**Context.** PLAN.md §7 requires TOTP two-factor login for Admin and Publisher (we add Super Admin).
Filament 5's built-in requirement is panel-wide.

**Decision.** Filament's app-authenticator MFA (with recovery codes) is enabled for everyone; the
"required" middleware is replaced by `RequireMfaForPrivilegedRoles`, which only redirects users holding a
role in `config('portal.mfa_required_roles')` to the set-up page. Secrets and recovery codes are stored
encrypted. Super Admins are created with `portal:create-super-admin`, which prints a one-time password in
the terminal of whoever runs it (never in CI logs).

**Consequences.** Other staff may opt in from their profile. Losing a phone needs an Admin to run
"Reset two-factor login", which is audited and signs the user out.

## ADR-0007 — How UI strings are stored, resolved and exported (2026-10-19)

**Context.** PLAN.md §6 keeps UI strings in `lang/*.json`, lets translators edit them in the admin
(Harari by hand), and says an import should "regenerate `lang/har.json`". Two facts shape the design:

- Laravel only falls back for PHP group files, never for JSON keys, and an empty JSON value prints the
  raw key. Harari ships with every key present and empty, so Harari pages would show `site.office`.
- Deploys copy the repository's `lang/` folder to the server, so a `lang/har.json` regenerated on the
  server would be overwritten by the next deploy and translators' work would be lost.

**Decision.**

- `App\Domain\Translation\FallbackTranslator` replaces Laravel's translator: JSON and group strings
  follow `config('portal.fallbacks')` (har → am → en, om → en, am → en), empty values count as missing,
  `trans_choice()` follows the same chain, and strings served from a fallback are recorded per request.
- Shipped strings stay in `lang/*.json` (developers, CI). Strings edited in the admin are stored in
  `ui_translations` and override the files at runtime through spatie/laravel-translation-loader
  (model `UiTranslation`: exact locale only, empty means missing). They survive every deploy.
- `php artisan lang:export` writes the live strings (files + admin edits) in the repository's exact JSON
  format: to `storage/app/translations/` on the server for download, or with `--to-lang` into `lang/`
  on a developer machine so the edits can be committed. This replaces "regenerate lang/har.json".
- `php artisan lang:missing` (CI, `--files-only`) fails when Amharic or Afaan Oromo miss a key, any
  language breaks a `:placeholder` or plural form, a locale keeps keys English removed, or code uses a
  key English lacks. Harari coverage is reported, never failed.
- spatie's loader dropped Laravel's built-in English framework strings; `AppServiceProvider` rebuilds it
  with the framework path first, as Laravel does. Amharic and Afaan Oromo framework strings
  (validation, auth, passwords, pagination) come from Laravel-Lang (MIT), kept only where placeholders
  match English (suffixes such as Oromo `:attributen` allowed), and await native review.
- Routing: every public page lives under `/{en|am|om|har}`; `/` redirects by cookie (`hpp_locale`,
  unencrypted), then Accept-Language, then `config('portal.default_locale')` (Amharic until the party
  decides). The redirect is `private, no-store` with `Vary: Accept-Language, Cookie` so Cloudflare never
  shares it. Pages carry hreflang for all four languages, `x-default` and a canonical URL.
- The admin panel uses each staff member's preferred language (English if none); Filament's own labels
  fall back to English because Filament ships no Amharic.

**Consequences.** Phase 4's translation screens edit `ui_translations` and use `TranslationCatalog` for
coverage and diffs. Pages whose slugs differ per language pass their own URLs to `<x-locale-meta>` and
`<x-language-switcher>`.

## ADR-0008 — Settings, sites and the Ethiopian calendar (2026-10-19)

**Context.** PLAN.md §7 lists the settings the admin manages and asks for "`site_id` on content and
settings from day one". §11 stores API keys encrypted with `Crypt`. §6 shows the Ethiopian calendar with
the Gregorian one everywhere a date appears, in an order chosen in the admin.

**Decision.**

- **Settings.** spatie/laravel-settings 3.9 with the official Filament plugin. Two groups exist now:
  - `site` (`App\Settings\SiteSettings`): default language, which calendar leads and whether the other
    is shown, contact email, phone and hotline, address and office hours per language, social links.
  - `integrations` (`App\Settings\IntegrationSettings`): SMS driver and sender, and the keys for SMS,
    Google Translate, the LLM provider, Meta, YouTube, Telegram and Turnstile.
- **Secrets.** Integration keys are encrypted at rest with Laravel's encrypter, so they depend on the
  server's `APP_KEY`. The admin never shows a stored key again. A blank field keeps the stored key, and
  "remove" clears it. The audit log records that a key changed, never its value.
- **Who.** Site settings are readable with `settings.view` (Admin) and changeable with `settings.manage`.
  Integrations need `integrations.manage`. Both "manage" permissions belong to Super Admin only.
- **Later groups.** The other groups of PLAN.md §7 arrive with the features they configure: logos with
  the media library (1.5), colours and fonts with the design tokens (1.6), mail, SEO, analytics,
  maintenance and feature flags in their phases.
- **Sites.** A `sites` table holds one default site (`harari`). `Site::current()` picks the site whose
  domain matches the request host, otherwise the default site. `App\Domain\Sites\Settings\SiteSettingsRepository`
  adds `site_id` to every settings row and scopes every read and write to the current site. Content
  tables get `site_id` from Phase 2. A queued job working for a non-default site calls
  `Site::setCurrent()` first. spatie's settings cache stays off, because its key ignores the site.
- **Calendar.** `App\Services\Calendar\EthiopianCalendar` wraps andegna/calender 2.4, which needs PHP's
  `calendar` extension (present on the server, in CI and in the test image). Dates are stored in UTC and
  converted to Addis Ababa time before conversion, so 22:30 UTC on 10 September is already Meskerem 1.
  Times use the 24-hour clock in Addis Ababa time. Ethiopian hour notation (counting from 06:00) is not
  used, since it reads ambiguously next to Gregorian dates.
- **Display.** `<x-date :date="…" />`, `@ethiopianDate(…)` and `CalendarDate::format()` for Filament
  columns all go through `EthiopianCalendar::format()`. Month names and date patterns are translation
  keys (`calendar.*`), so each language orders day, month and year its own way.

**Consequences.** Rotating `APP_KEY` makes the stored keys unreadable, so they must be re-entered (see
the runbook). A second site needs its own settings rows, written by a settings migration run with
`Site::setCurrent()`. Phase 2 content models take `site_id` from `Site::current()`.

## ADR-0009 — Media library: private originals, cleaned uploads, focal crops (2026-10-19)

**Context.** PLAN.md §7 asks for a media library with folders, alt text per language, a focal point,
usage tracking, WebP/AVIF copies and responsive sizes. §11 asks for MIME sniffing, re-encoded images,
sanitised SVGs, no executable types and uploads stored outside the web root. The host is shared cPanel:
GD only (no Imagick, no optimiser binaries), PHP memory limits, a cron-drained database queue.

**Decision.**

- **Package.** spatie/laravel-medialibrary 11.23 (12.x needs PHP 8.4) with its bundled spatie/image on
  GD. intervention/image is not added: spatie/image already re-encodes, resizes and crops around a point.
- **Owner.** Library files belong to the site (`Site` implements `HasMedia`, collection `library`), so
  they exist independently of posts and pages. Content refers to them by id (e.g. `featured_image_id`)
  and records each use through `SyncMediaUsage` (`media_usages`); a used file cannot be deleted.
- **Extra columns** on `media`: `site_id`, `folder_id`, `uploaded_by`, `kind`, `alt` (JSON per
  language), `credit`, `focal_x`/`focal_y` (percent), `width`, `height`, `tags`.
- **What is accepted.** `UploadInspector` decides the type from the file's bytes (finfo); the name may
  only pick between specific types the bytes support (e.g. an Office ZIP). Allowed: JPEG, PNG, WebP, GIF;
  SVG; PDF, DOCX, XLSX, PPTX, ODT, ODS, ODP, TXT, CSV; MP4, WebM; MP3, M4A, OGG. Limits per kind are in
  `config/portal.php`. Refused: anything else (HTML, PHP, old binary Office formats), text or SVG
  containing `<?php`, Office files with macros or path tricks, images over 50 megapixels (checked from
  the header, before decoding), damaged images.
- **Cleaning.** Photos are re-encoded with GD on upload: turned upright from EXIF, scaled to a 3840 px
  longest edge, and saved again, which drops EXIF/GPS and any appended payload. Animated GIFs become
  still images. SVGs go through rhukster/dom-sanitizer (MIT; the common enshrined/svg-sanitize is GPL)
  plus a final check; SVGs that need DOCTYPE entities are refused and must be exported again.
- **Names.** The stored file name is a slug of the title plus the extension of the real type, so
  "photo.php.jpg" can never be stored as PHP. Folders on disk are the media UUID, not the id.
- **Where files live.** Originals on the private `media` disk (`storage/app/media`), served only by
  `MediaFileController` at `/media/{uuid}/{name}` with the stored type, `nosniff`, a year of caching,
  `inline` only for images, PDF, audio and video, and a sandbox CSP for SVG and text. No session or
  cookies on that route, so Cloudflare can cache it. Copies go to the `public` disk under
  `storage/library/{uuid}/conversions/`; the deploy writes an `.htaccess` there that refuses script
  files, and the smoke test checks that public uploads are still served.
- **Copies.** A 320 px preview (made at upload) and three presets cropped around the focal point:
  landscape 16:9 (480, 960, 1600), square 1:1 (320, 640), photo 3:2 (600, 1200). Each is WebP (q80) and,
  when GD can write AVIF (`MEDIA_AVIF=auto`), AVIF (q60). Widths larger than the original are skipped.
  Moving the focal point remakes the crops; versioned URLs make browsers and Cloudflare fetch them.
  No optimiser binaries are used. `<x-picture>` renders `<picture>` with AVIF/WebP srcsets, width and
  height, the focal point as `object-position`, and alt text in the page's language.
- **Queue.** Copies are made on the `media` queue. Cron runs `php artisan portal:work-queues`, which
  drains every queue listed in `config('portal.queues')`; new queues need no server change.
- **Who.** `media.view` opens the library, `media.upload` uploads and edits one's own files,
  `media.update` edits any file and manages folders, `media.delete` deletes unused files and empty folders.
  Alt text is optional in the library; Phase 2 requires English, Amharic and Afaan Oromo before an image
  becomes a featured image (Harari falls back to Amharic).

**Consequences.** Large originals cost disk space on the host; the media screen shows usage against
`MEDIA_QUOTA_GB`. PDF previews, video posters and an in-browser crop editor need Imagick or ffmpeg and
are left for a host that has them. A move to S3/R2 is a disk change (`MEDIA_DISK`,
`MEDIA_CONVERSIONS_DISK`) plus a signed-URL variant of the media route.

## ADR-0010 — Design tokens, public layout, security headers and edge caching (2026-10-19)

**Context.** PLAN.md §12 sets the visual system (party blue, a Harar accent, Inter and Noto Sans
Ethiopic, 8 px spacing, 1280 px container) and §9/§11 set the budgets (Lighthouse ≥ 90, HTML < 60 KB,
JS < 80 KB gzipped), a strict CSP with nonces, HSTS and Cloudflare caching. The admin panel is Filament,
whose Alpine components evaluate expressions at run time.

**Decision.**

- **Tokens.** `resources/css/tokens.css` defines the Tailwind 4 theme: `primary-50…950` around
  `#3151ED` (600), `accent-50…950` around Harar terracotta `#B4532A` (600), semantic success, warning and
  danger, and surface/ink/line/link colours that switch with `prefers-color-scheme: dark`. Headings use
  `heading-1/2/3` utilities whose size is multiplied by `--heading-scale` (0.92 for Ethiopic).
- **Fonts.** Self-hosted in `public/fonts/portal/` with the version in the file name (cached a year),
  built by `scripts/fonts/build_fonts.py`: Inter (Latin, Latin Extended, variable weight) and Noto Sans
  Ethiopic subset to the main Ethiopic block U+1200–U+137F (198 KB → 117 KB). Phones that already have
  Noto Sans Ethiopic use it locally. Inter is preloaded everywhere, the Ethiopic font on am/har pages.
  Ethiopic text gets line-height 1.7.
- **Public CSS only scans public templates** (`resources/views` without `filament/`, `app/View`):
  about 22 KB raw, 6 KB gzipped. No JavaScript is shipped yet.
- **Layout.** Skip link, utility bar (today in both calendars, language switcher), header with the
  wordmark and main menu, `<main id="content">`, footer with about, contact and social blocks read from
  the site settings (blocks without data are left out; only `https://` social links are shown).
  Components: `x-ui.button`, `x-ui.badge`, `x-ui.card`, `x-ui.date-tile`, `x-picture`, `x-date`.
  Search and accessibility controls arrive with their features rather than as dead placeholders.
- **Error pages** (403, 404, 419, 429, 500, 503) are translated and do not need the database: the
  language comes from the URL prefix, then the cookie, then the browser. The 503 page is pre-rendered by
  `artisan down --render` at deploy time, so it shows all four languages.
- **Security headers** (`SecurityHeaders`, every response): `X-Content-Type-Options`,
  `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy`
  (camera, microphone, geolocation, payment, USB, topics off), `Cross-Origin-Opener-Policy`, and HSTS
  (one year, subdomains) over HTTPS.
- **CSP.** Public pages: `script-src` and `style-src` are `'self'` plus a per-request nonce (Vite tags
  carry it; inline code must use `@nonce`), `style-src-attr 'unsafe-inline'` for one-off positions such
  as focal points, no `unsafe-eval`, `object-src 'none'`, `frame-ancestors 'none'`, YouTube's
  privacy-enhanced domain allowed for embeds. Admin and Livewire paths get a relaxed policy with
  `'unsafe-inline' 'unsafe-eval'` because Filament needs them; they sit behind sign-in and two-factor
  login. A CSP set by a controller (the SVG sandbox) is kept.
- **Edge caching.** Public pages, `/`, the styleguide and media files run without the session
  middleware, so they set no session cookie. `CachePublicPages` marks an anonymous GET 200 that sets no
  cookie as `public, max-age=0, s-maxage=600, stale-while-revalidate=60`; anything else is
  `private, no-cache`. Pages with forms (Phase 3) opt back into the session. Cloudflare only caches HTML
  once a Cache Rule tells it to respect origin headers (runbook). A page served from the edge cache
  repeats its nonce until it expires; nonces stay a second line of defence behind output escaping.
- **Static files** under `public/` with `.js`, `.css` or `.woff2` names (hashed builds, versioned
  fonts, Filament's `?v=` assets) are sent with `Cache-Control: public, max-age=31536000, immutable`.
- **Styleguide** at `/styleguide` (404 in production) shows tokens and components; `?lang=` switches
  the language. It is developer-facing, so its labels are English and it is exempt from the
  hard-coded string check.
- **Checks.** `scripts/check-hardcoded-strings.py` (CI lint job) fails when a template shows text not
  passed through `__()`. The Lighthouse workflow audits `/en` and `/am` on the live site after each
  deploy (mobile; performance, accessibility and best practices ≥ 0.9; HTML ≤ 60 KB and JS ≤ 80 KB
  transferred; CLS ≤ 0.1). SEO is not scored on staging because it is noindex.

- **Admin avatars** are SVG initials drawn on the server (`InitialsAvatarProvider`) rather than
  Filament's default ui-avatars.com images, which would send staff names to a third party.

**Consequences.** Logos are placeholders (a neutral favicon in party colours) until the party supplies
its brand files (PLAN.md §14). Adding Livewire components to public pages will need either Livewire's
CSP-safe mode or a review of the public policy.

## ADR-0011 — Translations of content in translation tables, with our own concern (2026-10-19)

**Context.** PLAN.md §6 and §10 store each translatable record as `thing` plus `thing_translations`
(one row per language) with its own slug, status and publish date, and the portal resolves missing
languages along a chain (Harari → Amharic → English, Afaan Oromo → English).

**Decision.**

- `App\Domain\Content\Concerns\HasTranslations` (plus the `TranslatableModel` contract) gives a model
  `translations()`, `translation($locale, fallback)`, `translatedLocales()`, `putTranslation()` and the
  scopes `withTranslation()` (eager-loads the language and its fallbacks, no N+1) and `translatedIn()`.
  spatie/laravel-translatable (JSON columns) cannot index or uniquely constrain slugs per language, and
  astrotomic/laravel-translatable has one fallback locale rather than a chain; neither models a status
  per language.
- Translation rows extend `App\Domain\Content\Models\Translation`. Slugs are unique per language
  (`unique(locale, slug)`), made from the title or name when left empty, and never change when the title
  changes; only an editor's edit changes a slug (redirects for changed slugs arrive in step 2.5).
- `App\Domain\Content\Support\Slugger` writes Ethiopic in Latin letters with a readable table
  (ሐረር ጁጎል → `harer-jugol`, የሴቶች ሊግ → `yesetoch-lig`) and reads Ethiopic numerals (፳፻፲፱ → 2019).
  Laravel's built-in Amharic transliteration (`beletsegenaa`) was too far from how names are written.
- Content records use ULIDs (`HasUlids`), belong to a site (`BelongsToSite`, a global scope on the
  current site) and record `created_by`/`updated_by` (`TracksEditors`). The audit log subject and the
  media usage owner became string columns so they can point at ULID records.
- In the admin, `App\Filament\Support\Translatable` renders one tab per language (in each language's own
  script), fills and saves the rows, removes a language whose main field is emptied, requires the main
  field in at least one language, and checks slug uniqueness per language.

**Consequences.** Every content model of Phase 2 onward uses this pattern; categories and tags are the
first. Public pages show only languages a record has (with the fallback rules decided per page type in
step 2.4).

## ADR-0012 — Posts: block content, per-language status, revisions (2026-10-19)

**Context.** PLAN.md §7 asks for a block editor (paragraphs, headings, images with caption and credit,
gallery, video, quote, table, file, call-out), a featured image with focal point and alt text per
language, per-language status and publish date, revision history with diff and restore, duplicate and
bulk actions. The public pages must stay small, accessible and within the strict CSP (ADR-0010).

**Decision.**

- **Editor.** Filament's rich editor (TipTap) stores the body as JSON per language. Toolbar: bold,
  italic, link, H2/H3, quote, lists, table, undo/redo. File attachments are off: images come from the
  media library through custom blocks, so every image is cleaned, cropped and described (ADR-0009).
- **Blocks** (`App\Domain\Content\Blocks`): photo (library image, caption, credit, normal/wide), gallery
  (up to 24 library photos), video (YouTube only, embedded from youtube-nocookie.com and lazy-loaded),
  highlighted note (information/important/good news), file (library document, audio or video). PDF
  viewing in the page waits for the documents module (Phase 3).
- **Rendering.** `BodyRenderer` renders the JSON with Filament's `RichContentRenderer` (escaped text,
  our Blade views per block), gives plain text for counts and search, and lists the library media used.
  Word count covers Latin and Ethiopic words; reading time assumes 200 words a minute.
- **Saving** goes through `SavePost` (one transaction): at least one language must have a title; only
  `posts.publish` publishes, schedules or archives, and a published language edited by a non-publisher
  stays published; translators change text only (type, image, categories, tags and flags need
  `posts.update`, or `posts.update_own` on one's own post); a featured image needs alt text in each live
  language among English, Amharic and Afaan Oromo (Harari falls back to Amharic); publishing without a
  date stamps the current time; media used as featured image or in the body is recorded
  (`SyncMediaUsage`), so it cannot be deleted from the library.
- **Revisions** (`revisions` table, any content type): a version of a language is kept when its text,
  slug, status or date changed since the last version. Comparing shows changes paragraph by paragraph;
  restoring brings back the text only (title, kicker, summary, body, SEO texts), never the status, date
  or address, and is itself a new version.
- Blocks live in the domain although they extend a Filament class: the same classes define the editor
  form and the public HTML.

**Consequences.** Scheduling, review and send-back move to the workflow of step 2.3; the public news
pages (2.4) render bodies through `BodyRenderer` only.

## ADR-0013 — Editorial workflow, notifications and webhooks (2026-10-19)

**Context.** PLAN.md §7 describes the editorial flow (author drafts and submits, an editor approves or
sends back with comments, translators fill other languages, a publisher schedules or publishes per
language), notifications for editors, and §9 domain events with webhooks.

**Decision.**

- **State machine per language** (`App\Domain\Content\Workflow`), no package. Moves: submit (draft or
  translating → in review; anyone who may edit the post), send back (in review → draft; `posts.review`,
  comment required), mark reviewed (records reviewer and time; `posts.review`), publish, schedule (a
  future time), unpublish, archive and reopen (`posts.publish`). A move is refused unless it starts
  from an allowed status and the person may make it; the admin only shows the moves that are possible.
- **Status in the form is read-only**; it changes only through the workflow buttons in each language
  tab. Publishers can still set the publication date shown on the site (for example when entering older
  news). `SavePost` keeps its status rules for imports.
- **Scheduled publishing**: `content:publish-due` runs every minute from the scheduler and publishes
  languages whose time has passed.
- **Records**: each move is in the audit log (`workflow`, with from/to status and comment), keeps a
  version, and send-back comments are stored as internal comments (shown on the post, never public).
- **Notifications** (database for the admin bell, and email): submitted → reviewers; reviewed →
  publishers; sent back, published, scheduled, unpublished, archived → the author. Never the person who
  acted, never suspended accounts. Each person reads them in their admin language.
- **Events and webhooks**: every move dispatches `ContentTransitioned`. Webhooks (Super Admin,
  `integrations.manage`) subscribe to `post.published`, `post.scheduled`, `post.unpublished`,
  `post.archived`; deliveries are queued, signed (`X-Portal-Signature: sha256=` HMAC of the body),
  limited to public HTTPS addresses (no private, loopback or link-local IPs, no credentials in the URL,
  no redirects), retried three times, and logged. The payload carries ids and public facts only. The
  secret is stored encrypted and shown once.

**Consequences.** Scoped review (an office's editor reviewing only that office's posts) uses the
existing role scopes once posts carry offices (step 2.7). A DNS answer can change between the address
check and the request; deliveries contain only public information, which limits the impact.

## ADR-0014 — Public news pages, language fallback for posts, previews and feeds (2026-10-19)

**Context.** PLAN.md §3–4 define `/{lang}/news` and `/{lang}/news/{slug}`, filters, "available in"
chips, a language switch that lands on the same post or the listing, RSS and JSON feeds per language,
and tokenised previews of drafts. Harari will have little content of its own for a long time.

**Decision.**

- **Listings** show posts live in the page's language. For languages listed in
  `portal.content_fallbacks` (Harari → Amharic) a post without a translation of its own appears in the
  fallback language, marked "In አማርኛ", with `lang="am"` and a link to its Amharic page. A post page
  itself exists only in languages the post is written in; the language switcher and hreflang link to
  the post where it exists and to the news listing elsewhere.
- **Filters** use query strings (`?type=press_release`, `?category=…`, `?tag=…`) so slugs never collide
  with type names; sticky posts first, then newest; 12 per page with a translated previous/next pager.
- **Post page**: kicker, title, summary, dual-calendar date, reading time, featured image (eager, high
  priority, for LCP), body through `BodyRenderer`, topics, "also available in", share links (plain
  links, no third-party scripts) and up to three related posts from the same categories.
- **Feeds**: `/{lang}/feed.xml` (RSS 2.0 with `content:encoded`) and `/{lang}/feed.json` (JSON Feed
  1.1), the 20 newest posts live in exactly that language.
- **Previews**: signed URLs valid for seven days (`/preview/posts/{post}/{language}`), any status,
  sent with `noindex` and `private, no-store`; editors get the link from the post editor. Revoking a
  link early means changing `APP_KEY`, which also signs the rest of the site, so links are kept short.
- Public news pages are stateless and cacheable at the edge like the home page (ADR-0010).

**Consequences.** Search engines see Harari listings that contain Amharic items; their canonical pages
stay Amharic, so no duplicate content is created. SEO metadata, sitemaps and redirects follow in 2.5.

## ADR-0015 — Search engines and sharing: metadata, sitemaps, robots, redirects, OG images (2026-10-19)

**Context.** PLAN.md §11 SEO: hreflang and canonical URLs (done in ADR-0007), language sitemaps with an
index, robots.txt, JSON-LD (PoliticalParty, NewsArticle, BreadcrumbList), Open Graph and Twitter cards
with generated images for posts without a photo, 301 redirects on slug change and a redirect manager.

**Decision.**

- **`<x-seo>`** in the public layout: Open Graph (site name, type, title, description, URL, locale and
  alternate locales, image) and Twitter card tags in the page's language, and one JSON-LD graph per
  page (the party as `PoliticalParty` with its social profiles and contacts from the site settings,
  plus the page's own nodes). The JSON-LD script carries the CSP nonce and escapes `<`, `>` and `&`.
- **Post pages** add `NewsArticle` (headline, description, language, dates, image, word count, the
  party as author and publisher) and `BreadcrumbList`, with `og:type=article`.
- **Sharing images**: the post's featured photo (1600 px crop) when it has one; otherwise a generated
  1200×630 PNG in the party colours with the type and title, drawn with GD from static bold TrueType
  instances of Inter and Noto Sans Ethiopic (`resources/fonts/og`, built by `build_og_fonts.py`;
  text is split into Latin and Ethiopic runs because GD has no font fallback). Images are made once per
  title and kept on the public disk; other pages share one image per language.
- **Sitemaps**: `/sitemap.xml` (index) and `/sitemaps/{lang}.xml` with the home page, the news listing
  and every live post, each with `xhtml:link` alternates for the languages it exists in; cached for an
  hour.
- **robots.txt** is served by the application (the static file was removed): everything disallowed
  outside production; in production the admin, Livewire and previews are disallowed and the sitemap
  is announced.
- **Redirects**: when a published post's slug changes, the old address redirects (301) to the new one;
  older redirects are re-pointed so there are no chains, and a redirect from the new address is removed
  so there are no loops. Publishers and admins add or edit redirects by hand (`redirects.manage`).
  Unknown addresses are looked up before the 404 page is shown; hits are counted.

**Consequences.** Search Console verification fields and search (WebSite + SearchAction) arrive with
step 2.9. Generated images for staging live in `storage/app/public/og` and are safe to delete.

## ADR-0016 — Pages and menus (2026-10-19)

**Context.** PLAN.md §3 and §7 ask for nested static pages (About, History, Vision …) with templates
and the block editor, and menus for the header, footer and utility bar with labels per language.

**Decision.**

- **Pages** use the translation pattern (ADR-0011) and the block editor (ADR-0012). Templates: text
  page, wide page, landing page with a large photo. Nesting goes at most three levels deep and can never
  loop. A page's address is the chain of its and its ancestors' slugs in that language
  (`/en/about/history`); a request with the right last slug but wrong parents gets a 301 to the real
  address. Page routes are registered last in each language, so fixed sections such as `/news` always
  win, and a page can never take the address of a fixed section (`news`, `offices`, `events` …):
  it gets `-page` added. A language of a page is published or not with one switch held by
  `pages.publish`; pages have no review workflow (posts do, ADR-0013). Pages keep versions and
  record their media usage. Changing the address of a published page adds a redirect from the old
  one (ADR-0015); its sub-pages follow through the wrong-parent redirect.
- **Menus** are one record per place (header, footer, top bar) holding a small tree (items with up to
  one level of sub-items, at most 12 per level) instead of a `menu_items` table: menus are short, edited
  as a whole and read whole. Items point at a page, a post, the news listing, a site path or an
  `https://` address, with a label per language (following the fallback chain). Items whose page or
  post is not live in a language are left out of that language. Resolved menus are cached for ten
  minutes and cleared when a menu or page is saved. Until a header menu exists, Home and News show.
- Sub-items open with a native `<details>` disclosure (keyboard and screen-reader friendly, no
  JavaScript).

**Consequences.** Mega-menu panels with images and the utility bar items come with the homepage
builder (2.8). Pages are added to the sitemaps in this step.
