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

## ADR-0018 — Two-step sign-in compulsory only by choice (2026-10-19)

**Context.** PLAN.md §7 and §11 make two-step sign-in (authenticator app) compulsory for Super Admins,
Admins and Publishers, and the panel enforced it from step 1.2. On 2026-10-19 the owner asked for it to
be a switch: staff should be able to sign in with a password alone unless two-step sign-in is set up
or turned on.

**Decision.**

- A Super Admin-only page, Administration → Security, holds one switch: "Make two-step sign-in
  compulsory for senior roles". While it is on, the roles in `config('portal.mfa_required_roles')`
  (Super Admin, Admin, Publisher) must set up an authenticator app before using the panel, as before.
  While it is off, nobody is forced to.
- The switch starts **off**, so nobody is locked out before they have set up the app; the page shows
  how many people in those roles have not set it up yet.
- Everyone can still turn the authenticator app on or off for their own account under Profile; a
  person who has it on is always asked for the code. A Super Admin can reset someone's app from Users.
- Changes to the switch are kept in the audit log like other settings.

**Consequences.** This deviates from PLAN.md §7/§11 at the owner's request. A password alone protects
the most powerful accounts while the switch is off; the recommendation stays to turn it on once the
senior staff have set up the app. Login throttling, session sign-out and the audit log are unchanged.

## ADR-0017 — Offices, leaders and leagues (2026-10-19)

**Context.** PLAN.md §3, §4, §7 and §10 ask for an office directory (branch → woreda → kebele, sector
offices, basic organisations, head, contacts, hours, map pin), a leadership directory grouped by body
with profiles, leagues and forums with their own pages, an organisational chart, and posts linked to
offices, leagues and the people who gave a speech or statement.

**Decision.**

- A new module `app/Domain/Organisation` holds `Office`, `Leader` and `League`, each with a
  translations table (ADR-0011): names, slugs, summaries, block-editor text and, for offices, address
  and working hours per language. Each language is a draft or published; publishing needs the module's
  `manage` permission (editors and publishers by default, PLAN.md §8). There is no review workflow and
  no version history for these records; the audit log keeps every change.
- Offices form one tree at most four levels deep (branch → woreda → kebele → basic organisation),
  never looping; an office with sub-offices cannot be deleted. Heads of offices and league chairs
  point at leaders; leaders may belong to one office and one league.
- Posts link to any number of offices, leagues and leaders (`office_post`, `league_post`,
  `leader_post`); copies keep the links. Office, league and leader pages list their latest posts.
- Public pages: `/{lang}/leaders`, `/{lang}/offices` (filter by kind, search by name),
  `/{lang}/leagues`, each with a profile page at `/{slug}`, and `/{lang}/structure`, the org chart as
  nested `<details>` lists (no JavaScript). Harari readers also see entries written only in Amharic,
  marked with their language (as for news, ADR-0014). Profiles carry Person or Organization data.
- Office map pins use OpenStreetMap's embed (an `<iframe>`, lazy-loaded, no script of ours) and a link
  to the full map; the content security policy allows frames from `www.openstreetmap.org`. Staff enter
  latitude and longitude as numbers. An interactive directory map with every office (Leaflet,
  self-hosted) is left for later, as it would add about 40 KB of JavaScript.
- Leaders' phone numbers, emails and social links are shown only when entered, and are meant for
  official contacts; social links must be https.

**Consequences.** Office-scoped staff roles (an office head editing their own office and its news)
come with the scoped publishing rules later in Phase 2/3. No real names are seeded: the directory is
filled by staff or by the content import (2.11).

## ADR-0019 — Homepage builder (2026-10-19)

**Context.** PLAN.md §4 lists sixteen homepage blocks and asks that every one be a reorderable,
toggleable block with limits, curated picks and per-language overrides (§7), stored in
`homepage_sections` (§10).

**Decision.**

- One row per section and site in `homepage_sections` (key, on/off, order, settings JSON, hand-picked
  post ids). Missing rows are created with defaults the first time the homepage or the builder is
  opened, so new sections appear without a data migration.
- Sections now: news ticker, hero, leadership message, latest news, press releases and statements,
  numbers, core values, leagues spotlight and related links. Events, projects, the social hub, video
  of the week, the photo strip and newsletter sign-up join when their modules exist (Phases 3–4).
- Admin → Content → Homepage (permission `homepage.manage`): one screen where sections are dragged
  into order and opened to set them: on or off, how many to show, hand-picked posts (ticker, hero),
  the leader and message, numbers, values and links with text per language, a heading per language
  and the languages a section shows in. Texts follow the language fallback chain; a section with
  nothing to show in a language is left out.
- The hero shows hand-picked posts, else posts marked as featured, else a welcome with the party's
  name and description. Slides scroll sideways with CSS scroll snapping and numbered links; there is
  no autoplay (WCAG 2.2.2) and no JavaScript. The first slide's photo loads first. Numbers are shown
  as they are entered, without counting animations.
- The core values start with the seven in PLAN.md §4, written in English, Amharic and Afaan Oromo;
  editors can change or remove them.

**Consequences.** The holding page is gone; the homepage is built from content. The builder replaces
the "feature on homepage" idea with two tools: the post's "featured" switch and hand-picked posts.

## ADR-0020 — Site search (2026-10-19)

**Context.** PLAN.md §4 asks for site-wide search in the reader's language with typo tolerance for
Ethiopic and Latin script, §6 for Ethiopic-aware matching and Latin-typed Amharic, and §7 for search
insights (top and zero-result queries). ADR-0002 runs Scout with its database driver on the shared
host and Meilisearch later.

**Decision.**

- Laravel Scout on the five kinds of translation that have public pages: posts, pages, leaders,
  offices and leagues. Each row keeps a `search_text` column built on save from its title or name,
  summary and text, and the record's language; that column is what the database driver searches and
  what Meilisearch would index.
- Matching text is normalized the same way for stored text and queries: Ethiopic letters that sound
  alike and are written either way are made one (ሐ ኀ → ሀ, ሠ → ሰ, ዐ → አ, ፀ → ጸ, ሃ → ሀ, ኣ → አ), Ethiopic
  punctuation becomes a space, Latin letters lose case and accents. Text in Ethiopic script also
  stores its Latin spelling (the slug transliteration), so "medemer" finds መደመር.
- The `database` driver is replaced by a subclass that matches **every word** of the query in any
  order (`LIKE` per word, at most six words of two letters or more). MySQL full-text search is not
  used: it skips words shorter than three characters, which drops many Amharic words, and the shared
  host cannot change that setting. Fine for thousands of records; Meilisearch is the step after that
  and needs only `SCOUT_DRIVER=meilisearch` plus `scout:import`.
- `/{lang}/search?q=` lists results by kind (news, pages, people, offices, leagues), up to five each
  with a link to the rest, only from languages that are published, plus Amharic for Harari readers
  (ADR-0014). A search box sits in the header of every page, and the site tells search engines about
  it (WebSite + SearchAction). Answers are not cached at the edge or indexed, and each address may
  search 30 times a minute.
- Search insights: every search adds to a count per language and normalized words with the number of
  results last time (`search_terms`); nothing about the visitor is stored. Staff who see analytics read
  them under Content → Site searches, with a filter for searches that found nothing. Terms not searched
  for in a year are removed. `php artisan portal:search-reindex` rebuilds the search text.

**Consequences.** Typo tolerance beyond letter variants (missing letters, swapped vowels) waits for
Meilisearch. Events, documents and projects join search with their modules.

## ADR-0021 — Dashboard v1 (2026-10-19)

**Context.** PLAN.md §7 describes a role-aware, user-configurable widget dashboard: a widget registry
(permission, default size, data), default layouts per role, layouts per person (add, remove, reorder,
resize), global range/language/office filters, drill-down links and a long widget catalogue. Phase 2
delivers the first version with the widgets whose data exists.

**Decision.**

- The admin home is our own Filament dashboard page with three filters on top (period: 7, 30 or 90
  days, 12 months or the Ethiopian fiscal year from 1 Hamle; language; office) that every widget
  reads, and a 12-column grid (one column on phones).
- Widgets implement `App\Domain\Dashboard\Contracts\DashboardWidget` (key, title, permission, default
  width) and are listed in `App\Filament\Support\DashboardRegistry`. The registry order is the default
  layout; a role's default is the part of it the role may see, so publishers start with editorial
  widgets and admins also get operations. Super Admins editing role defaults in the admin is left for
  later.
- Each person can open **Customise dashboard** to add, remove, reorder and resize widgets (quarter,
  third, half, two thirds, full width), or **Reset**; the layout is saved in `dashboard_layouts` and
  widgets they may no longer see are dropped when it is read.
- First widgets: announcements, key figures (published in the period with the change against the
  period before and a daily trend, waiting for review, missing translations, searches with no
  results), needs attention, quick actions, editorial pipeline per language, translation coverage
  (interface texts and published posts), site searches, media storage, background work (jobs waiting
  per queue, failed jobs and a scheduler heartbeat written every minute) and recent activity. Figures
  link to the lists behind them.
- Announcements are written by Super Admins under Administration → Announcements, with a kind and
  optional start and end times.
- The widget views use Filament's own components plus a few inline styles instead of a separate admin
  theme build, so the asset pipeline and the public CSS budget are unchanged.

**Consequences.** Traffic, social, members, applications, events and tickets widgets arrive with
their modules and analytics; alerts, exports, scheduled snapshots and named dashboards come later.
Visitors and page views wait for privacy-friendly analytics (Phase 4).

## ADR-0022 — Importing stories from the public channels (2026-10-19)

**Context.** The owner asked for real content from the Harari branch's own channels first and the
Harari Government Communication Affairs Office second (docs/content/sources.md). Their Facebook pages
cannot be read without logging in; their Telegram channels have public preview pages.

**Decision.**

- `content_sources` lists the channels: the branch (`t.me/hrprosperityparty`, primary) and the office
  (`t.me/HarariGovernmentCommunication`, secondary), both on from the start at the owner's request.
  Under Administration → Content sources, admins see each channel's state and can import now; Super
  Admins can also switch a channel off, rename it and set its language. New channels are added in code
  after the branch confirms them.
- `content:import` runs every half hour. It reads `https://t.me/s/<channel>` (no account, no login,
  Telegram publishes no robots rules for it) with an identifying user agent, only messages newer than
  the last one read, the branch first. Only that address and photos on Telegram's CDN
  (`cdn*.telesco.pe`) are ever requested.
- A text message is a story; photo albums posted within two minutes of it are its photos; albums with
  no text nearby are skipped. Each story becomes a **draft** news post: the first line is the title,
  the rest the text, unchanged. Ethiopic text takes the channel's language (Amharic) unless it reads as
  Harari (three or more Harari words or ዚ-/ዪ- forms, which Amharic hardly uses); Latin text is told
  apart as Afaan Oromo or English. Up to
  ten photos are downloaded once, cleaned like any upload (ADR-0009) and credited to the channel; the
  first is the featured photo, the rest a gallery.
- Every post keeps its source: name, message link and original time. Editors see it in the post
  editor with a reminder to check names and facts; readers see "Source: …" under the story.
- A story that matches one already imported from the same channel or a higher-priority source within
  three days (sixty first words, 60 % in common after normalisation) is recorded as a duplicate, so
  reposts and the office's copies of branch stories are skipped. `content_imports` records every message read.
- Nothing is published automatically; machine translation is not used. Stories from the
  communication office are published only once the office's permission to republish is confirmed
  (PLAN.md §14 input; editors see the source on every imported draft).

**Consequences.** Facebook and YouTube are read through their APIs with the branch's tokens in Phase 5
(Social Media Hub). Photos posted after their story was imported are skipped; editors can add them
from the source link. If Telegram changes its preview markup, imports fail visibly on the content
sources screen until the reader is updated.

## ADR-0023 — Photo galleries (2026-10-20)

**Context.** PLAN.md §4 asks for photo albums with a lightbox, responsive images and optional download
of the original, and §10 for `galleries` and `gallery_items`.

**Decision.**

- An album has a title, slug and short description per language, published per language like the
  directory (ADR-0017, now a shared `PublishedPerLanguage` concern and `WriteTranslations` action in the
  content module), a cover, the date taken and an optional office or league. Its photos are library
  images (ADR-0009) in order, each with a caption per language; removing a photo from the library
  removes it from its albums.
- The album page shows square thumbnails; each opens a full-screen view on its own address
  (`#photo-3`) using the CSS `:target` selector, with previous, next, close, caption and credit links.
  It needs no JavaScript, works with the back button and keeps the page budget; full-size images load
  only when opened.
- When an album allows it, each photo links to its original (already cleaned of location and camera
  data at upload).
- A homepage section shows up to eight photos of the newest album; Harari readers also see Amharic
  albums (ADR-0014).

**Consequences.** Swiping between photos on phones and keyboard arrows wait for an optional small script
later; the links work everywhere today.

## ADR-0024 — Videos and live broadcasts (2026-10-20)

**Context.** PLAN.md §4 asks for a video gallery of YouTube, Facebook and Telegram videos embedded in a
privacy-friendly way, playlists, a Live page and a homepage "video of the week" (§10 `videos`).

**Decision.**

- A video is an address an editor pastes (YouTube watch, youtu.be, shorts, live or embed links; a
  Facebook video; a Telegram post) with a title and description per language, published per language
  (ADR-0017), a playlist, the date recorded and an optional office or league.
- YouTube videos play on their own page in the `youtube-nocookie.com` player, which sets no cookies until
  the visitor starts it; the frame is allowed by the content security policy since step 2.2. Facebook and
  Telegram embeds load their own scripts and trackers, so those videos show a picture that links to the
  original instead.
- Listing pages never contact YouTube: when a YouTube video has no chosen picture, YouTube's picture is
  copied into the media library once, cleaned like any upload and credited to YouTube.
- Playlists are categories with the type "video" (ADR-0011); post screens only offer post categories.
- A video marked as a live broadcast has a start and an optional end time; without an end it counts as
  live for six hours. `/{lang}/live` shows the broadcast on now, the next one and recent videos; the
  video list says when something is live.
- The homepage section shows the video marked as video of the week, otherwise the newest, as a picture
  linking to its page (no player on the homepage).

**Consequences.** YouTube Live status is set by editors; reading it from the YouTube API comes with the
social hub (Phase 4).

## ADR-0025 — Events, calendars and registration (2026-10-20)

**Context.** PLAN.md §4 asks for events with a calendar (month and list), an event page with the
agenda, speakers, a map and "add to calendar", and a homepage "upcoming events" section; §10 lists
`events` and `event_registrations`. Step 3.3 builds the events; step 3.4 adds registration.

**Decision.**

- An event has a start and an optional end (or "all day"), a venue and address per language, a map pin
  or an online link (https only), an agenda per language (time and item lines), speakers chosen from the
  leaders, a cover picture and an optional office or league. Title, summary, description, venue, address
  and agenda are written per language and published per language (ADR-0017).
- Times are entered in Addis Ababa time and stored in UTC, like posts. Pages show both calendars
  through `<x-date>`.
- `/{lang}/events` lists the upcoming events, `?past=1` the earlier ones, and `?month=YYYY-MM` shows a
  month grid with the Ethiopian dates next to the Gregorian ones. Past events keep their page, marked as
  ended, without the calendar buttons.
- Each event has an `.ics` file and a Google Calendar link; `/{lang}/events/feed.ics` is a subscribable
  feed of the upcoming events. The files are written by hand to RFC 5545 (escaped text, lines folded at
  75 bytes, UTC times, dates only for all-day events), so no calendar package is added.
- The event page carries schema.org `Event` data. Events are in search and the sitemaps, and the
  homepage section shows the next few (three by default, up to six).
- The registration columns (on/off, places, waiting list, closing time) are created now so that 3.4
  only adds the registrations table.

**Consequences.** Recurring events are not modelled; an editor copies the event instead. The map is the
same OpenStreetMap frame as on office pages.

**Registration (step 3.4).**

- An editor turns registration on per event, with an optional number of places, a waiting list (on by
  default) and a closing time (otherwise the start). People register at `/{lang}/events/{slug}/register`
  with their name and a phone number or email address (at least one; Ethiopian numbers are accepted as
  typed and stored as +251…) and agree to how the details are used.
- Places are given under a row lock on the event, so the last place is never given twice. When the
  event is full, people join the waiting list if it is on. A place given back, by the person from their
  ticket or by staff, goes to the first person waiting; raising the number of places does the same.
- The same phone number or email address registers once per event; trying again sends the existing
  ticket again to the details given the first time, and the page only says so.
- Each registration has a ticket page with a random 40-character code in its address, a QR code and a
  cancel button. The QR code opens Admin → Check-in for that ticket, where staff with
  `events.registrations` let the person in with one tap; scanners that type the code also work. A ticket
  is used once.
- Messages go by email (queued, with the event as a calendar file) and by text message through an
  `SmsGateway` interface. Until a provider is chosen (PLAN.md §14), the log driver records each text
  with the number partly hidden. Reminders go once, within the 24 hours before the event (hourly job).
- Personal details are encrypted at rest (name, phone, email and ticket code); keyed hashes find
  duplicates and tickets. Queued messages are encrypted too. The audit log records status changes only.
  Registrations are deleted 180 days after the event (`PORTAL_REGISTRATION_RETENTION_DAYS`); the real
  retention period is an open legal question (PLAN.md §14).
- Forms are protected by a hidden field, a minimum and maximum time to fill them in, a rate limit (5 a
  minute, 30 an hour per address) and Cloudflare Turnstile once its keys are saved under Integrations.
  Turnstile fails closed. Form pages keep a session for CSRF and are never cached; the event page stays
  cacheable and only links to the form.
- `events.registrations` (see the list, check in, cancel, download CSV) is granted to Publishers and
  Membership Officers by default; a read-only event page lets those who cannot edit events reach the
  list.

**Consequences.** Changing the app key makes old tickets unreadable, as for all encrypted data. Custom
questions on the form come with the forms builder (step 3.7). On existing installs a Super Admin grants
`events.registrations` to further roles under Roles.

## ADR-0026 — Documents library and notices (2026-10-20)

**Context.** PLAN.md §4 asks for a library of programmes, bylaws, reports, policies, speeches and forms
(type, language, year, office; in-browser PDF viewer; download counter; version history; related
documents) and for vacancies, tenders and notices with deadlines. §10 lists `documents` and
`document_translations` and requires that members-only documents are never served without sign-in.

**Decision.**

- A document has a type, a year, the date issued, an optional office or league, related documents (up to
  eight) and, for vacancies, tenders and notices only, a deadline in Addis Ababa time. Each language has
  its own title, summary, description and file from the media library (PDF or office formats), and is
  published per language (ADR-0017).
- A language without its own file offers the file of another published language (Amharic first) and
  says which language it is in.
- Choosing a new file for a language keeps the old one as an earlier version with its number, note and
  date; the document page lists earlier versions for download, and an editor can make one current
  again (the file it replaces becomes a version too, so history only grows). Old files stay marked as
  used in the media library.
- `/{lang}/documents` lists library types with filters for type, year and office; `/{lang}/notices` shows
  open vacancies, tenders and notices by the nearest deadline, then the recently closed ones.
- PDFs open inside the document page in the browser's own viewer: PDF files may be framed by this site
  only (`frame-ancestors 'self'`, `X-Frame-Options: SAMEORIGIN`), everything else stays unframeable. No
  PDF.js: it would add about 700 KB to document pages; the download button is always there for phones
  that do not show PDFs in a page.
- Downloads go through `/{lang}/documents/{slug}/download`, which is never cached, counts once an hour per
  visitor (address and browser hashed), ignores robots, and sends the file. Reading in the viewer is not
  counted.
- The `access` column exists, but the admin offers public documents only. Members-only documents are left
  out of the library, the pages, search and sitemaps; the member portal (Phase 6) will add access to them
  and protect their files.

**Consequences.** Full-text search inside PDFs waits for Meilisearch (PLAN.md §16); search covers titles,
summaries and descriptions.

## ADR-0027 — Projects, statistics and achievements (2026-10-20)

**Context.** PLAN.md §4 asks for project cards (sector, woreda, status, budget if public, dates, photos,
progress; filter and map view), achievements by sector with statistics blocks (number, label, source,
as-of date) and charts, and a homepage "programmes and projects" section. §7 wants statistics reusable in
pages; §10 lists `projects` and `statistics` with translations.

**Decision.**

- Sectors are categories of the type "used for project sectors" (ADR-0011), so editors name and translate
  them without code.
- A project has a sector, an office or woreda, a status (planned, ongoing, completed, paused), progress
  in per cent (100 once completed), start and end dates, a budget shown only when the editor allows it,
  a map pin, a cover picture and a photo album from the gallery (ADR-0023). Title, summary, location and
  description are written and published per language.
- A statistic is a number with a label, unit and source per language, an as-of date and an optional
  https source link. It belongs to a sector (achievements page), to a project (project page), to both or
  to neither ("across the region"). Statistic languages have no publish switch: they are shown wherever
  what shows them is shown. A "Statistics" block puts chosen numbers in any page or post.
- `/{lang}/projects` lists projects with filters for sector, status and office; each project page shows
  progress, numbers, photos, the map and Project JSON-LD. `/{lang}/achievements` shows projects by
  status, the regional numbers, and per sector its numbers, recently completed projects and a bar chart
  of its projects by status drawn in HTML and CSS (no chart script).
- The homepage section shows highlighted projects first, then the latest updated (three by default).

**Consequences.** A map of all projects together needs a map script with many pins (for example Leaflet
with OpenStreetMap tiles); it is left out for now, and each project page has its own map. Budgets are in
birr.

## ADR-0028 — Forms builder, tickets and the inbox (2026-10-20)

**Context.** PLAN.md §4 asks for a contact page and forms (contact, feedback and petition, press
enquiries, vacancy applications) with spam protection, routing to an office, an automatic reply in the
person's language, a ticket number and a status lookup; §7 for a forms builder and an inbox with status,
assignee, internal notes and a response-time clock; §8 for support agents and office heads per office.

**Decision.**

- A form has a purpose, the office it routes to, an optional address to notify, the working days within
  which it should be answered, whether name and contact details are optional, and whether it is the one
  on the contact page. Title, introduction, thank-you text and automatic reply are written and published
  per language.
- Name, email and phone are always asked first (at least an email or a phone unless the form allows
  anonymous use). The form's own questions follow: short and long text, email, phone, number, date, a
  list, one choice, several choices, an agreement box or a file. Each question keeps a random key for
  life and stores its label, help and choices for every language with it, so they cannot drift apart;
  choices are matched by position. Answers keep the position and the words the person saw.
- Files (PDF, Word, OpenDocument text, JPEG or PNG up to 5 MB, checked by their bytes like library
  uploads) go to the private disk and are downloaded only from the inbox, which is logged.
- A ticket gets a random reference such as `HPP-7K3D-9Q2M` (no 0/O or 1/I). `/{lang}/tickets` finds a
  ticket by its reference and shows only its status and dates, never what was written. Lookups are rate
  limited.
- Everything a person writes is encrypted at rest, and so are queued emails that carry it. The notice to
  the office carries no personal details, only the reference and a link to the inbox.
- The inbox shows each person the tickets of the offices their role covers (a role without an office
  scope covers all offices and the tickets of no office). Staff assign tickets, change their status,
  write internal notes and reply; a reply is emailed in the person's language when they gave an address
  and stops the response clock. The answer-by date counts working days, Monday to Friday, in Addis Ababa
  time; public holidays are not counted out yet.
- Forms keep a session and CSRF protection and use the protection of ADR-0025 (hidden field, time check,
  rate limit, Turnstile).

**Consequences.** Canned replies, satisfaction ratings and a retention period for tickets are left for
later; the retention rules are a PLAN.md §14 question.

## ADR-0029 — Press kit and "Get involved" (2026-10-20)

**Context.** PLAN.md §4 asks for a press kit (logos, colour palette, leadership portraits, boilerplate,
media contact form) and a "Get involved" page leading to membership, volunteering, events, feedback,
newsletter, careers and tenders. Membership and volunteering arrive in Phase 6, newsletters in Phase 7.

**Decision.**

- The press kit is a settings page for staff with `press_kit.manage`: the boilerplate and the contact's
  name per language, a contact email and phone, logo files from the media library, and the press
  enquiry form (a form with the purpose "press enquiry", ADR-0028).
- `/{lang}/press` shows them with the identity colours and their hex codes (the design tokens, PLAN.md
  §12), downloadable portraits of the executive committee from the leadership directory, and the five
  latest press releases. Missing languages fall back as UI strings do (har → am → en, om → en).
- `/{lang}/get-involved` links the next events, the feedback and petition forms, the contact page and
  the vacancies and tenders, and shows membership, volunteering and newsletters as coming soon, without
  links, until their phases.
- Both pages stay cacheable: they link to forms instead of embedding them.

**Consequences.** Portraits are the library originals; a print-size crop per portrait is not offered.

## ADR-0030 — Maps load on request (2026-10-20)

**Context.** Office, event and project pages embedded the OpenStreetMap frame directly. It loads about
300 KB of OpenStreetMap's scripts as soon as it is near the screen, which broke the script budget of
PLAN.md §11 on the first event page audited (ADR-0010) and told OpenStreetMap about every visit.

**Decision.** The map frame starts with a small page written by the portal itself (`srcdoc`): a sentence
saying the map comes from OpenStreetMap and a "Show map" link that loads the map inside the same frame.
Nothing is fetched from OpenStreetMap until the reader chooses it, and no script is involved. The "Open a
larger map" link stays below the frame.

**Consequences.** Readers who want the map press once more. The same rule as for YouTube videos
(ADR-0024): nothing from another site loads without the reader asking.

## ADR-0031 — Social sources, fetching and moderation (2026-10-20)

**Context.** PLAN.md §4 asks for a social media hub with the posts of the branch, its offices and its
leagues; §9 for a fetcher per platform; §10 for `social_sources` and `social_posts` with a unique
`external_id` per source. Story import (ADR-0022) already reads the public Telegram channels for news
drafts; the hub is a different use of the same accounts: showing posts as they are, linking out.

**Decision.**

- A source is an account on one network (Telegram, YouTube, Facebook, TikTok, Instagram, X) with the name
  it is shown under, an optional office or league, how often it is read (15 minutes to a day, an hour by
  default) and whether it is read and shown. Credentials, when a network needs them, are stored
  encrypted on the source and never logged.
- A post keeps its text as published, the date, the link to the original, whether it has a video, its
  counts when the network gives them, and a copy of its first picture in the media library (cleaned like
  any upload, credited to the account), so the hub never loads pictures from the networks.
- Fetchers only read what the network publishes for everyone or what its API allows with the account's
  own token. Telegram is read from the public channel pages, as for story import; other networks follow
  in step 4.2. A network without a fetcher is listed but not read.
- `social:fetch` runs every fifteen minutes and reads the sources whose interval has passed; "Read now"
  reads one at once. Each fetch reads at most thirty posts. A failure is written on the source and never
  stops the others.
- Staff with `social.manage` hide a post or pin it to the top; fetching again never undoes that. Text is
  never edited: it is the account's own post.

**Consequences.** Deleting a post on the network does not remove it from the hub until staff hide it;
counts are as of the last fetch.

**Networks (step 4.2).**

- YouTube channels are read from their public feed (the latest fifteen videos with title, description,
  picture and views; no key). A channel given by its @name needs the YouTube Data API key once to find
  its id, which is then kept on the source.
- Facebook pages are read through the Graph API (v21.0) with the page's own access token, saved on the
  source (encrypted) or under Integrations. Without a token the source shows that it needs one.
- TikTok, Instagram and X are not read on their own: staff add a post by its address. Its words and
  picture come from the network's public oEmbed answer (TikTok, X, YouTube; Instagram only with the Meta
  token), otherwise from what the editor types. Addresses must be https on the network's own hosts.

## ADR-0032 — The public social hub (2026-10-20)

**Context.** PLAN.md §4 asks for a social media hub with filters, follow buttons for every account and QR
codes for the Telegram channels, a homepage teaser and the posts of offices and leagues on their pages,
within the privacy and performance rules of ADR-0010 and ADR-0024.

**Decision.**

- `/{lang}/social` shows the visible posts (from enabled accounts, not hidden), pinned ones first, then
  newest, 24 per page, with filters by network, office and league. Text is shown as the account wrote
  it (up to 420 characters, then "Open on …"), so it is not translated; `dir="auto"` keeps scripts right.
- Nothing loads from the networks: pictures are the library copies (ADR-0031), no network script,
  frame or widget is used, and every post links out to the original.
- The side column lists every account with a follow link; Telegram channels also get a QR code drawn on
  the server (`App\Support\QrCode`, shared with event tickets).
- A homepage section "On social media" (six posts by default, up to twelve) appears once there are
  posts; office and league pages show the three latest posts of their own accounts.

**Consequences.** Posts appear in the language they were written in for every reader; filters by
language are left for when accounts in several languages exist.

## ADR-0033 — Translating the interface in the admin (2026-10-20)

**Context.** PLAN.md §6 and §7 ask for a translations module: a grid of interface strings with filters
(missing, changed), context, per-language rights, history, and export and import with a Harari
round-trip. Since ADR-0007 the strings live in `lang/*.json` with admin overrides in `ui_translations`.

**Decision.**

- Administration → Languages → Interface translations shows every English key in one language at a time:
  the key, the English text, the text in that language and its status. Filters: missing, "English
  changed" (the English text differs from the one it was translated from), translated, and the part of
  the site (the first part of the key); search covers keys and both texts.
- People see the grid with `translations.view`; they change a language only with
  `translations.{language}.edit` (a Translator can be limited to one language, ADR-0006). The text must
  keep the English placeholders exactly; an empty text removes the admin's version so the shipped text,
  or the fallback language, shows again.
- Every change is kept in `ui_translation_revisions` with the old and new text, the English it was made
  from, who made it and how (editor, import, machine). The last five show in the editor; the status
  "English changed" comes from the latest revision.
- Changes take effect at once (the translation cache of the key is cleared) and `php artisan lang:export`
  still writes them back into `lang/*.json` for the repository.

**Consequences.** Strings translated in the files before this module have no revision, so they are not
flagged when English changes until someone edits them once.

**Export and import (step 4.5).**

- A language can be exported as Excel (recommended), CSV (UTF-8 with a byte-order mark, so spreadsheet
  programs read Ethiopic) or JSON in the repository's layout. Sheets have the columns key, en, the
  language and context; every cell is text.
- Importing reads the key and language columns (or the JSON pairs) and first shows a preview: what would
  be added, changed or removed, and what is refused (keys English does not have, placeholders that differ
  from English, texts over 5000 characters, files not in UTF-8). Nothing is saved until the importer
  confirms. Empty cells leave a text as it is unless "Empty cells remove the translation" is chosen.
- Applying writes each change through the same rules as the grid (origin "import") and keeps the admin's
  earlier value of every key; Administration → Languages → Translation imports rolls a whole import back.
- Importing needs `translations.import` and the right to edit that language; exporting needs
  `translations.export`. An automated test exports Harari to Excel, edits it as a translator would
  (a placeholder, a line break), imports it, and exports again with nothing lost.

## ADR-0034 — Glossary (2026-10-20)

**Context.** PLAN.md §6 asks for a glossary so that the party's names, bodies and ideas are translated
the same way everywhere; §10 lists `glossary_terms` (term per locale, notes).

**Decision.**

- A glossary term holds the approved word in every language (English required) and notes on its use.
  People with `translations.glossary` keep the glossary; everyone with `translations.view` reads it.
- The translations editor lists the glossary terms an English string contains (whole words, case aside)
  with the approved term for the language, marked as used or not.
- A translated string that contains an English glossary term while its translation lacks the approved
  term gets the status "Glossary term not used" and can be filtered. A language without an approved
  term for an entry is not flagged. The glossary warns; it never blocks saving.

**Consequences.** Matching is literal, so inflected forms in Amharic or Afaan Oromo may be flagged; the
reviewer decides. Content translations (posts, pages) are not checked yet.

## ADR-0035 — Machine translation for Amharic and Afaan Oromo (2026-10-20)

**Context.** PLAN.md §6 allows machine translation as a first draft for Amharic and Afaan Oromo, reviewed
by people, and never for Harari; §9 lists a `TranslationProvider` with Google, LLM, LibreTranslate and
null drivers; §14 leaves open whether it may be used at all and with which provider.

**Decision.**

- `MachineTranslator` is off until a Super Admin chooses a driver under Integrations (Google Cloud
  Translation, or an AI model from Anthropic or OpenAI with an optional model name) and its key is saved.
  The settings page says that text is sent to that service.
- It only translates into Amharic and Afaan Oromo; Harari is refused in code, not only in the screens.
- Placeholders such as `:date` are replaced by tokens before sending and must come back exactly once;
  otherwise the text is dropped. The AI model is asked for a formal register suitable for the party and
  given the approved glossary terms (ADR-0034); it must answer with a list of the same length.
- Interface strings: "Machine translate" per string and "Translate missing strings by machine" for a
  language (in the queue) write with origin "machine"; such strings show "Machine translated" until a
  person edits them or chooses "Mark as checked".
- Posts: "Translate from another language by machine" fills an Amharic or Afaan Oromo tab from the
  language the post was written in (title, kicker, summary, SEO texts and the words of the body, keeping
  its structure and blocks). The language is marked "Machine translated: needs a person's check" until
  someone ticks "Checked by a person".
- The dashboard's "Translation queue" shows per language the missing, changed and machine translated
  strings and the post languages still to check.

**Consequences.** LibreTranslate is left out until someone hosts it. Pages, events and the other content
types get the same post action later; the review mark does not block publishing.

## ADR-0036 — Staff notices and the notifications page (2026-10-20)

**Context.** PLAN.md §7 asks for in-app and email notifications with per-person preferences. Until now
only the editorial workflow notified staff (ADR-0013), always by both channels, and new tickets were only
mailed to an office address.

**Decision.**

- Every staff notice extends `StaffNotice` and belongs to one kind: posts and reviews, messages and
  tickets, event registrations, imports and background work, failing sources. Each person chooses per kind
  "in the portal" (the bell and Notifications), "by email", both or neither; the choice is stored on the
  user (`notification_preferences`). Defaults: both, except registrations and imports (in the portal
  only). A kind is only offered to people whose permissions it concerns.
- Recipients: new tickets go to active staff with `inbox.view` whose office scope covers the ticket
  (ADR-0028); a ticket given to someone tells that person; registrations go to `events.registrations`;
  stories imported as drafts to `posts.review`; a social account that starts failing to `social.manage`, an
  import channel to `settings.view`; a finished machine translation to the person who started it. The
  person who caused a notice never receives it.
- Notices carry no personal details of the public (no names, phone numbers or email addresses), only
  titles, references and counts; they are written in each person's admin language.
- A notice may belong to a group (one event's registrations, one failing source): the person's unread
  notice of the same group is replaced, so a busy event gives one up-to-date notice. Failing sources are
  reported when they start failing, not on every attempt.
- Notifications (`/admin/notifications`) lists the person's own notices with read and kind filters, open,
  mark as read or unread, delete, mark all as read, and "How I am notified".

**Consequences.** Notices are queued like other mail. Old notices are not deleted automatically yet; the
retention rules of step 5.6 will cover them.

## ADR-0037 — Health checks, backups and the restore drill (2026-10-20)

**Context.** PLAN.md §11 asks for daily database and media backups with 30-day retention, migrations run
after a backup, a tested one-command restore and a restore drill, and §7/§9 name `spatie/laravel-health`
and `spatie/laravel-backup`. The shared host has no monitoring of its own; errors stay in the server log
until an error tracker is chosen (step 5.3).

**Decision.**

- `spatie/laravel-health` runs the checks every five minutes from the scheduler and keeps a week of
  results: database, cache, scheduler heartbeat, background jobs (a test job per queue), jobs waiting
  (oldest over 15 minutes warns, over an hour fails), failed jobs (1 warns, 10 fail), outgoing mail
  (mailer and sender), media and uploads against `PORTAL_STORAGE_QUOTA_MB` when set, the server disk,
  backups (newest older than 26 hours fails), debug mode off and cached configuration. `/up` stays the
  plain boot check for uptime monitors.
- Administration → System health (`settings.view`) shows the latest results, runs them on request and
  sends a test email to the person asking. When a check turns from passing to failing, staff with
  `settings.view` get a "System health" notice (ADR-0036); the package's own mail is off.
- `spatie/laravel-backup` writes one AES-256 encrypted zip (`BACKUP_ARCHIVE_PASSWORD`) every night at 02:00
  Addis Ababa time to the `backups` disk (`storage/backups`, mode 700, outside `storage/app` so backups
  never contain backups): the database dump and the media library, public uploads and form attachments.
  Not the code (git) nor `.env`. Kept 7 days in full, then daily to 30 days, weekly to 8 weeks, monthly to
  6 months, yearly, under 5 GB. A second disk named in `BACKUP_OFFSITE_DISK` receives a copy; a failing
  off-site copy keeps the local one.
- The deploy runs `portal:backup-before-migrate` before `migrate`: when migrations are pending, the
  database is backed up first and a failed backup stops the release.
- `portal:restore-database` is the one-command restore (newest backup by default, asks first, writes the
  audit log); `portal:restore-drill` restores into the separate `drill` connection, checks tables and
  migrations, counts rows, empties it again and records the result, monthly once `DB_DRILL_DATABASE` is
  set. Super Admins (`backups.manage`) can start a backup ("Back up now" runs it from the scheduler within a
  minute, so a long backup is not cut off by the queue's retry time) and download one; downloads are
  audited.

**Consequences.** Backups hold personal data and are only as safe as the server account and the archive
password; the password must also be kept outside the server. The off-site store and the drill database on
staging wait for the owner (open questions 2 and the restore runbook). The test image gains the MariaDB
client, and CI creates a `harari_drill` database for the restore tests.

## ADR-0038 — Security pass: sign-in alerts, admin allow-list, token expiry, error reporting (2026-10-20)

**Context.** PLAN.md §11 lists login and IP alerts, an optional admin IP allow-list, dependency scanning,
token expiry warnings for social sources (§5) and an error tracker. The review is in
`docs/security-review.md`.

**Decision.**

- A sign-in from a network (the /24 of an IPv4 address, the /48 of an IPv6 one) and a browser both unseen
  among the person's sign-ins of the last 90 days sends them a "New sign-in to your account" notice in the
  portal and by email, with the time, the browser and system, and the address. It is a "Security" notice
  that cannot be switched off; the first sign-in of an account sends none. The queued copy is encrypted.
- `PORTAL_ADMIN_ALLOWED_IPS` (addresses or CIDR ranges) closes the whole admin, sign-in page and Livewire
  requests included, to other addresses with 403; empty by default. The client address is the one
  LiteSpeed takes from Cloudflare.
- Facebook page tokens: Meta's `debug_token` gives the end of the token and of its data access; the earlier
  counts. It is asked when a token is saved and every morning; social media managers are warned at 14, 7 and
  1 days before and when Meta refuses the token, once per stage; the token dialog shows the date.
- `sentry/sentry-laravel` reports errors once `SENTRY_LARAVEL_DSN` is set in the server `.env`, never with
  personal data or request bodies. Without it, the health page lists the newest errors from the log and
  fails its "Errors in the log" check at 50 a day (any error warns).
- Dependencies are audited in every CI run and every Monday by a workflow that only reports; Dependabot pull
  requests are not used because the repository takes no bot-authored commits.
- Logs and form attachments on the server are mode 750, backups 700.

**Consequences.** People on changing mobile networks who also change browsers may get an alert that was
them; the text says nothing needs doing then. The DSN lives in `.env`, not under Integrations, because the
SDK starts before settings are read.

## ADR-0039 — Edge cache purges and cache headers per page type (2026-10-20)

**Context.** PLAN.md §9 and §11 want pages served from Cloudflare's edge and changes visible at once.
Public pages already allow ten minutes at the edge (ADR-0010), but checked on staging Cloudflare answers
`DYNAMIC`: it does not keep HTML unless a Cache Rule says so. The Cloudflare zone is shared with other
sites of the same owner.

**Decision.**

- Under Integrations a Super Admin saves the zone id and an API token limited to "Cache Purge". A save or
  delete of anything public (posts, pages, events, documents, projects, offices, leagues, leaders, media,
  galleries, videos, categories, tags, menus, redirects, forms, homepage sections, social posts and
  accounts, interface strings) or of the site and press-kit settings queues one purge half a minute later;
  while one waits, no other is queued. Only this site's host is purged, never the whole zone. The health
  page shows whether purges are on and whether the last one was accepted. Without a token, pages refresh
  within ten minutes by their headers.
- Cache headers per page type: public pages `public, max-age=0, s-maxage=600, stale-while-revalidate=60`
  (GET and HEAD, no cookie set); pages with a person's own details (event tickets, ticket look-up and
  status) `no-store, private`; pages with forms `private, no-cache`; the sitemap its own edge time; built
  CSS, JS and fonts a year, immutable (hashed names); pictures, PDFs and videos in public uploads a day,
  because a regenerated picture keeps its address; the admin never cached.
- Lighthouse in CI also audits the news list, the documents list and the office directory.

**Consequences.** Until the owner adds the Cache Rule (`docs/runbook/server.md`), Cloudflare passes HTML
through and the origin answers every request; the headers are ready for the day it is added.

## ADR-0040 — Cookieless page view counts and traffic reports (2026-10-20)

**Context.** PLAN.md §7 and §11 ask for privacy-friendly analytics per language and content type, traffic
widgets on the dashboard and reports, with no third-party trackers. Counting on the server alone misses
every page Cloudflare serves from its cache (ADR-0039).

**Decision.**

- Public pages load one small first-party script (`resources/js/beacon.js`, the only script on them). It
  sends the page's address, the host of the referring site and the window width to `POST /pv`, without
  cookies, and sends nothing when the browser asks not to be tracked (Do Not Track, Global Privacy Control)
  or is automated. Cloudflare's country header is read on the way in.
- The server keeps daily totals only: views per day, language and page (with its section), and per day and
  language by referring site, kind of device (from the width) and country. No address, user agent,
  identifier or visit sequence is stored, so there is no personal data and no consent banner; unique
  visitors are not counted for the same reason. Bots, other sites' addresses and malformed beacons are
  ignored; the endpoint has its own rate limit and answers 204. Counts older than two years are removed.
- Dashboard widgets "Visits" (total against the previous range, a bar per day, languages and devices) and
  "Most read pages" (`analytics.view`). Insights → Traffic reports: views by page, section, language,
  referring site, device or country, for a period and a language; CSV export and scheduled reports
  (`analytics.export`). Scheduled reports go out at 07:00 Addis Ababa time, weekly on Mondays for the last
  seven days or monthly on the 1st for the month before, a separate email to each of up to ten recipients,
  with the top rows and all rows as CSV.

**Consequences.** Visitors with scripts off or with tracking protection are not counted, so figures are a
lower bound. Days are Addis Ababa days.

## ADR-0041 — Privacy requests and retention rules (2026-10-20)

**Context.** PLAN.md §11 asks for data export and deletion requests through the admin, in line with
Ethiopia's Personal Data Protection Proclamation No. 1321/2024, and retention that is shown and enforced.
The public's personal data lives in event registrations and in messages and tickets (with notes and
attachments); failed sign-ins keep the email that was tried. Names and contacts are encrypted (ADR-0025,
ADR-0028), so they cannot be searched directly.

**Decision.**

- Tickets get the same keyed hashes of email and phone as registrations (HMAC with the app key; existing
  tickets filled in by the migration). Administration → Privacy (`privacy.manage`, Admins and Super Admins)
  finds a person by email or phone, shows what was found, downloads a JSON copy (registrations, tickets with
  answers, replies and notes, attachment names, failed sign-ins) or erases it.
- Erasure keeps anonymous records so event numbers and inbox statistics still add up: names, contacts,
  hashes, answers and the text of replies and notes are removed, attachments deleted, ticket links stop
  working, failed sign-ins forget the email. Staff accounts are only reported; they are handled under
  Users. Copies in backups age out with the backups (30 days).
- Every request is logged with its kind, what was found, who handled it and when, and a keyed hash of what
  was searched instead of the email or phone; the audit log records it without personal data.
- Retention, shown on the same page and enforced every night: registrations 180 days after the event;
  resolved tickets 730 days after resolution (`PORTAL_SUBMISSION_RETENTION_DAYS`), open ones kept; audit log
  two years; staff notifications 90 days after being read and a year at most; failed jobs 30 days; backups
  30 days with fewer older copies; page view counts two years; search words a year; health results a week;
  sessions after two hours without activity.

**Consequences.** Searching by an address typed inside a form answer (rather than the contact fields) finds
nothing; the person's contact email or phone is needed.

## ADR-0042 — Browser tests: accessibility and smoke (2026-10-20)

**Context.** PLAN.md §11 asks for WCAG 2.2 AA with automated checks, and §13 for confidence in the main
paths before launch. Lighthouse already scores accessibility after each deploy, but only on a few pages
and without failing on individual serious problems. The test container has no browser.

**Decision.**

- `pestphp/pest-plugin-browser` with Playwright (Chromium, `playwright` pinned in `package.json`) runs
  `tests/Browser` in a separate CI job `browser`, after `npm run build`, so pages carry their real styles.
  The normal test suites stay browser-free.
- `AccessibilityTest` runs axe on sixteen public pages (both homepages, lists and detail pages, forms,
  search, hub, press, structure) and fails on any serious or critical violation or JavaScript error; it
  also checks that the first Tab reaches "Skip to content" and that it leads to the content.
- `SmokeTest` clicks through the language switcher, the search form, the contact form (the message is
  stored) and publishing a post from the admin, which then appears on the site.
- Browser requests reach the app through another database connection, so these tests empty the tables
  before each test (keeping the rows migrations write) instead of rolling back a transaction. Each test
  therefore costs about half a minute; checks are grouped into few tests.
- The test image gains the `sockets` extension the plugin needs; screenshots of failures are kept as a CI
  artefact for a week. The audit and the manual checks still to do are in `docs/accessibility.md`.

**Consequences.** The `browser` job is the slowest in CI (several minutes). Selectors in browser tests
must be explicit CSS (`#id`, `html > body`) or a field name: a bare word is read as text or a name.

## ADR-0043 — Draft legal pages (2026-10-20)

**Context.** PLAN.md §11 needs a privacy policy aligned with Proclamation No. 1321/2024 and an
accessibility statement; terms of use are usual. The branch office has not supplied legal texts or a
privacy contact (open question 6).

**Decision.** `portal:legal-pages` creates three ordinary pages (`/{language}/privacy`, `/terms`,
`/accessibility`) in English, Amharic and Afaan Oromo, written to match what the portal does (data kept,
retention, cookieless counting, Cloudflare, rights and how to use them), each opening with a warning
callout "Draft for legal review" until the branch office approves the text and an editor removes the
callout. The pages are linked in the footer. The command never overwrites an existing page. Harari is left
to its translator; until then the Harari footer does not list them.

**Consequences.** The drafts are not legal advice; doubtful wording is listed in
`docs/translations-review.md` for the reviewers.

## ADR-0044 — Membership applications with a phone code (2026-10-21)

**Context.** PLAN.md §4 asks for a multi-step membership application with an SMS code that verifies the
phone, a reference number and a status page; §10 for `membership_applications`; §11 for encrypted personal
data. Party membership is sensitive, and most people in the region know their birth date in the Ethiopian
calendar.

**Decision.**

- `/{language}/membership/apply` in three steps kept in the visitor's session: about the person (name, sex,
  date of birth in the Ethiopian or Gregorian calendar, ID type and number, education, occupation); where
  they live (woreda, kebele and basic organisation from the published offices, or a written address) and
  their mobile number and email; then a summary, consent and a six-digit code by SMS. The application is
  stored from the first step (for the funnel) and submitted only when the code is right.
- Codes are sent at once (not queued), stored only as a keyed hash, valid ten minutes, five tries, a new
  one after a minute, at most five per application and per phone a day; the first step has the spam
  guard and Turnstile, every step its own rate limit.
- Names, birth dates, ID numbers, addresses, phones, emails and decision reasons are encrypted; ID number,
  phone and email are also kept as keyed hashes for duplicates and privacy requests; sex and birth year
  stay readable for aggregated reports only. The audit log records status and reviewer, never details.
- The visitor is never told whether a phone or ID number applied before; officers see possible duplicates
  (step 6.2). The status page needs both the reference and the phone number and is never stored.
- Unfinished applications are deleted after a week, rejected or withdrawn ones 180 days after the decision
  (`PORTAL_CLOSED_APPLICATION_RETENTION_DAYS`).

**Consequences.** Without an SMS gateway (staging) codes go to the server log, so only staff can finish an
application there. Applicants must be 18 or over.

## ADR-0045 — Review queue, members and office scopes (2026-10-21)

**Context.** PLAN.md §5 asks for a review queue where Membership Officers approve or reject applications,
see duplicates, assign them to a woreda, keep notes and export; §8 scopes Membership Officers to offices;
§11 asks for personal data masked in lists and exports that are permissioned and logged.

**Decision.**

- Admin → Members and volunteers → Membership applications lists sent applications (not unfinished ones),
  newest first, filtered to "received" by default. Names show as first name and initials, phones with the
  middle digits hidden; the application's page shows the full details except the ID number, which shows its
  last four digits until "Show ID number" is used, and each such look is written to the history and the
  audit log.
- Officers see the applications of the woredas their role is scoped to (`OfficeScope`, now shared with the
  inbox); a role without a scope covers every woreda and the applications without one. Only those with
  `applications.review` for the woreda decide; anyone who sees an application may add a note.
- Possible duplicates among the other applications and the members: the same ID number, phone or email
  (compared by keyed hash) or the same name and date of birth (decrypted only for people with the same
  birth year and sex). They are shown on the page and in the approval prompt; the officer decides.
- Approving creates the member in the same transaction: number `HPP-` and six digits in order, never
  reused, without the office code the phase plan suggested, so a member keeps the number when moving to
  another woreda; status active; renewal due after 12 months (defaults from the phase plan). Rejecting needs a reason, which
  the applicant sees on the status page. The applicant hears the decision by SMS, and by email when given.
- Handing an application to another woreda moves it to that woreda's queue; the history (notes, decisions,
  hand-overs, ID looks) is encrypted and never edited.
- Officers of the woreda get a notice per new application (kind "Membership applications", unread notices
  per woreda combined); the notice carries only the reference and the woreda.
- Download CSV exports what the screen shows, without ID numbers, and is logged with the number of rows.

**Consequences.** A member number reveals roughly how many members joined before; acceptable for a party
card. Members are not deleted from the admin: leaving, death or erasure go through statuses and privacy
requests (step 6.3).

## ADR-0046 — Members directory, renewals and privacy for members (2026-10-21)

**Context.** PLAN.md §5 asks for a members directory with member number, office, status, joined and renewed
dates, renewal reminders, notes and a timeline, masked lists and a permissioned export; ADR-0041 handles
privacy requests for registrations and tickets only.

**Decision.**

- Admin → Members and volunteers → Members lists the members of the woredas a person covers
  (`members.view`, office-scoped as in ADR-0045), names and phones masked, searched by member number or phone
  number (through the keyed hash; encrypted names cannot be searched). Filters: status, woreda, renewal due
  within the reminder window or overdue.
- Officers with `members.create` add members by hand (source "added by staff", next number); with
  `members.update` they correct details. The form never shows the ID number in full: left empty, the number on
  file stays. A second member with the same ID number is refused. The history names the details that changed,
  never their values.
- A membership runs 12 months. Without a date given, the first renewal is due on the next anniversary of
  joining. Renewing extends from the due date, or from today when it has passed. `membership:renewals` runs
  daily at 09:00 Addis Ababa time: one SMS per period to active members whose renewal falls due within 30 days
  (`PORTAL_MEMBERSHIP_REMINDER_DAYS`), and active members more than 90 days overdue
  (`PORTAL_MEMBERSHIP_GRACE_DAYS`) become lapsed, recorded in their history by the portal.
- Status changes (active, lapsed, suspended, resigned, deceased) need a reason, kept in the history; members are
  never deleted from the admin.
- Download CSV exports the members on screen without ID numbers, logged with the number of rows.
- Privacy requests (ADR-0041) now also find sent membership applications and member records by email or phone.
  The copy includes everything held, the ID number and the history included. Erasure keeps only the reference
  or member number, status, woreda and dates; history texts are replaced; an active or lapsed member becomes
  resigned. Purged applications take their history with them.

**Consequences.** Without an SMS gateway (staging) reminders go to the SMS log. Volunteers join the privacy
search with step 6.5.

## ADR-0047 — Importing existing membership rolls (2026-10-21)

**Context.** PLAN.md §5 asks for an import of existing membership rolls from Excel or CSV with column mapping,
a dry run with errors and duplicates, apply and roll back. Rolls hold personal data, are typed by hand, mix
Amharic, Afaan Oromo and English, and often give dates in the Ethiopian calendar or only a year of birth.

**Decision.**

- Admin → Members and volunteers → Roll imports, for `members.import`. "Import a roll" takes an Excel (.xlsx,
  first sheet) or CSV file (comma or semicolon, UTF-8) of at most 5,000 rows with headings in the first row.
  The second step matches the columns (guessed from usual headings in the three languages), names the ID
  document of the numbers, the calendar of typed dates and a joining date for rows without one. OpenSpout,
  already installed with Filament, reads Excel files.
- The check reads every row with the rules of the admin form and changes nothing: offices are found by their
  name in any language, with or without the word "woreda" or "kebele", and a kebele name used in several
  woredas by the row's woreda; a four-digit year of birth is accepted; dates are day/month/year or
  year-month-day, or real spreadsheet dates (always Gregorian). Rows with a problem are left out; a row
  whose ID number is already a member's or appears earlier in the file is a duplicate and left out; a phone
  already on file only warns (families share phones). Officers scoped to woredas can only import rows of
  those woredas. The problem list keeps row numbers and reasons only, never names.
- The checked rows wait encrypted in the import record; "Add members" applies them in the queue (rows are
  checked once more for ID numbers added meanwhile) and then deletes them. Each member gets a new number and
  a history line with the file, the row and the number in the roll. Checked imports not applied within a
  week lose their rows; uploads left between the two steps are deleted after a day.
- Within 30 days an import can be rolled back: its members and their history are removed, including changes
  made since. Member numbers now come from a sequence, so numbers of rolled-back members are never given out
  again.

**Consequences.** Officers fix problems in their file and import the remaining rows again; duplicates
protect against importing a roll twice. Name and date-of-birth duplicates are not checked in imports (that
would mean decrypting every member); the review queue still checks them for applications.

## ADR-0048 — Volunteers (2026-10-21)

**Context.** PLAN.md §4 offers volunteering on the Get Involved page; §5 asks for volunteers with skills,
availability and languages, assignments to events and an hours log.

**Decision.**

- `/{language}/volunteer`: name, mobile number (required, so a coordinator can call), email, woreda, how the
  person can help (12 skills), when (weekdays, evenings, weekends, events only), languages (Harari, Amharic,
  Afaan Oromo, Somali, English, Arabic), an optional message and consent; spam guard, Turnstile and the forms
  rate limit. No code by SMS: a sign-up is an offer, not a record that confers anything. A sign-up from a
  phone that is a member's is linked to that member.
- Contact details and the message are encrypted with keyed hashes for the phone and email; the audit log
  records status, woreda and the member link only.
- Admin → Members and volunteers → Volunteers, for `volunteers.view` (office-scoped as in ADR-0045), names and
  phones masked in the list, filters by status, skill, availability, language and woreda, a badge for new
  sign-ups. `volunteers.manage` adds and corrects volunteers, sets the status (new, active, inactive) with an
  optional note, and plans tasks: at an event (the task defaults to the event's name and date) or on their
  own, planned, done with the hours given, or cancelled. The first task makes a new volunteer active; the
  hours of tasks done are summed on the list and the page. Tasks are cancelled, never deleted.
- Coordinators of the woreda get a notice per sign-up (kind "Volunteers", unread notices per woreda combined).
- Privacy requests find, copy (tasks and history included) and erase volunteer sign-ups; erasure keeps the
  woreda, an inactive status and the tasks with their hours. Sign-ups still "new" after a year, which nobody
  followed up, are deleted.

**Consequences.** Sign-ups are not verified; coordinators confirm by calling. The same person can sign up
twice; the member link and the phone column show it.

## ADR-0049 — Member portal (2026-10-21)

**Context.** PLAN.md §4 promises members a portal: sign-in with phone and a one-time code (no passwords), their
profile with change requests, a membership card with a QR code that verifies the member number, their event
registrations and members-only documents. Members are not staff: they have no admin account.

**Decision.**

- A separate `member` guard on the member records. `/{language}/member/sign-in` takes the mobile number on the
  record; if it belongs to an active or lapsed member, a six-digit code goes by SMS at once (ten minutes, five
  tries, a new one after a minute, five a day per number). The page never says whether the number is a
  member's. Members are never remembered on a device; sessions end after 30 minutes without activity; every
  portal page is private, never stored and not indexed. Sign-ins are in the audit log; the audit log records
  only staff as the person who made a change.
- The overview shows the member number, status, joining and renewal dates, with a notice when the renewal is
  near or the membership has lapsed.
- The card is a printable page with the party name, the member's name, number, woreda, joining date, validity
  and a QR code. The code opens `/{language}/verify/{number}/{check}`; the check is a keyed signature of the
  number, so numbers cannot be tried one by one. The check page shows only the number, initials, woreda,
  validity and whether the membership is in good standing (active or lapsed with a future due date).
- "My details" shows the record with the ID number masked. Corrections are requests, not edits: they go into
  the member's history and to the woreda's officers (notice kind "Member corrections"), who update the record;
  three requests a day at most.
- "My events" lists event registrations made with the member's phone or email, with their ticket links.
- Documents can now be marked "members only" in the admin. They are listed and downloaded only in the portal;
  their files are refused at the public media address, in every language and earlier version.
- Sign-in codes older than a day are deleted.

**Consequences.** Without an SMS gateway (staging) codes go to the SMS log, so only staff can try the portal
there. A member without a phone on file cannot sign in until an officer adds one.

## ADR-0050 — People dashboard and membership report (2026-10-21)

**Context.** PLAN.md §7 asks for a people dashboard (applications funnel, member growth by office, sex and age
band, volunteers and hours, aggregated and without personal data) and a membership report in the report
builder.

**Decision.**

- Two dashboard widgets for `members.view`, part of the default layout of the roles that hold it: "Members and
  volunteers" (active members; members who joined in the dashboard's period against the period before;
  applications waiting, with `applications.view`; active volunteers, sign-ups and hours given, with
  `volunteers.view`) and "Applications and members" (applications started in the period and how many were
  sent, approved and not approved; active members by woreda, sex and age band, with shares). They follow the
  dashboard's period and office filters.
- Admin → Members and volunteers → Membership report: members by woreda, sex, age band (18–24 … 65+, or not
  known), status, how they were added, or the month they joined; filtered by status and by joining period.
  Download CSV needs `members.export`, is logged, and shows counts under five as "<5" (and no share), so no
  one can be picked out in a small group. The screen shows exact numbers: those who see it can already see the
  members of their woredas.
- Everything is counted in the database from plain columns (woreda, sex, year of birth, status, source,
  joining date); encrypted details are never read, and every count covers only the woredas the viewer
  looks after.

**Consequences.** Age bands come from the year of birth, so a member counts one band early until their
birthday in the year. Members without a year of birth are "not known".

## ADR-0051 — Newsletter and SMS subscribers with double opt-in (2026-10-21)

**Context.** PLAN.md §2 asks for a newsletter with double opt-in and an SMS option (Ethiopian users are
SMS-first), §4 for a preference centre (topics, language, channel) and a sign-up in the footer, §10 for
`subscribers`.

**Decision.**

- `/{language}/newsletter`: email, mobile number or both, four topics (news; statements and press releases;
  events; vacancies, tenders and notices), consent. Spam guard, Turnstile and the forms rate limit, plus five
  sign-ups a day for the same details. The footer of every page has a plain GET form with an email field
  that opens this page filled in, so cached pages stay without a session.
- Double opt-in per channel: an email address is confirmed by a private link, a phone by a six-digit code
  (ten minutes, five tries). News goes only to channels chosen, confirmed and not unsubscribed. Someone
  already on the list is never told so: they get the same link or code, which leads to their preferences;
  their choices change only there.
- The preference centre (`/{language}/newsletter/preferences/{token}`, never indexed) changes topics,
  language and channels; no channel, or "Stop all news", unsubscribes, and choosing a channel again
  resubscribes. Mail apps' one-click unsubscribe (RFC 8058) posts to a session-free route; links in emails
  lead to the preference centre, so link scanners never unsubscribe anyone.
- Contact details are encrypted with keyed hashes; the private token is kept encrypted and hashed.
- Admin → Communication → Subscribers for `subscribers.view`: masked list with status, channel, topic and
  language filters; staff with `subscribers.manage` can unsubscribe someone who asked by phone or letter;
  CSV download for `subscribers.export`, logged. Staff never add subscribers.
- Privacy requests find, copy and delete subscriptions. Sign-ups never confirmed are deleted after a week.

**Consequences.** Email confirmation works with any mail account; until the branch office's SMTP account is
set, confirmation emails go to the mail log on staging and codes to the SMS log.

## ADR-0052 — Email and SMS campaigns (2026-10-21)

**Context.** PLAN.md §5 asks for email and SMS broadcasts to segments, templates per language, send-time
scheduling and delivery reports; §10 for `campaigns` and `campaign_deliveries`. Party notices also go to
members and volunteers, who did not subscribe to a newsletter.

**Decision.**

- Admin → Communication → Campaigns. `campaigns.manage` writes drafts: a name for staff, the channel (email or
  SMS), the audience and the text per language (email: subject and Markdown text without raw HTML; SMS: text,
  with its length in characters and parts, the stop link included, at most four parts). A campaign that has
  started is never changed or deleted.
- Audiences: confirmed subscribers of the channel, by topic and language; or members (by woreda and status,
  active by default) or active volunteers who have an address for the channel and did not opt out. Member and
  volunteer audiences need `members.view` or `volunteers.view` and cover only the sender's woredas, so a
  Communications Officer reaches subscribers only. Each address receives a campaign once.
- `campaigns.send` sends a test to oneself (email to one's own address, SMS to a number typed in), sends now
  after showing how many people it reaches, or schedules it (`campaigns:dispatch` every minute; a campaign that
  can no longer go returns to draft). Sending builds one delivery per recipient, holding only the keyed hash
  of the address and a private token, then a queued job sends one minute's worth at a time
  (`PORTAL_CAMPAIGN_EMAIL_PER_MINUTE`, `PORTAL_CAMPAIGN_SMS_PER_MINUTE`). Each person is checked again just
  before their message. A scheduled campaign can go back to draft; one being sent can be stopped.
- Each recipient gets the text in their language, else its fallbacks (Harari → Amharic → English), else any
  written language.
- Every email says why it was received and has an unsubscribe link plus `List-Unsubscribe` and
  `List-Unsubscribe-Post` headers; every SMS ends with "Stop: <link>". The link (`/{language}/u/{token}`,
  session-free) asks first; confirming, or a mail app's one-click POST, unsubscribes a subscriber or opts a
  member or volunteer out of campaigns (they still get messages about their own membership).

**Consequences.** Until the branch office's mail and SMS accounts are set, campaign emails go to the mail log
and SMS to the SMS log on staging. Delivery reports and click counts follow in step 7.3.

## ADR-0053 — Campaign delivery reports, clicks and opens (2026-10-21)

**Context.** PLAN.md §7 asks for campaign results (sent, delivered, opened, clicked, unsubscribed per campaign
and channel) and a subscribers widget.

**Decision.**

- Each delivery records whether it was sent, failed (with the provider's error) or skipped (the person left
  before their turn), when it was sent, its clicks and whether the person unsubscribed from it. "Delivered" as
  confirmed by the provider needs the provider's callbacks and comes with the branch office's mail and SMS
  accounts; until then "sent" means accepted for sending.
- Clicks: web links in a campaign email go through `/{language}/c/{token}/{n}`, which counts the click and
  sends the person on to the n-th link of the campaign's own text in their language. The address comes from
  the campaign, never from the link, so it cannot be used to send people elsewhere. No session or cookie.
- Opens: an invisible image (`/{language}/o/{token}.gif`) only when `PORTAL_CAMPAIGN_OPEN_TRACKING` is on.
  Off by default: many mail apps block it or load it for everyone, and it counts people without asking.
- The campaign page shows the delivery report: recipients, sent, still to send, failed, skipped, clicked and
  unsubscribed (as shares of the messages sent), opens when counted, and the figures per language. "Download
  the report" gives one line per recipient with type, language, outcome, times and clicks, never an address.
- Dashboard widgets: "Campaign results" (`campaigns.view`: the campaigns of the period with sent, click and
  unsubscribe rates) and "Subscribers" (`subscribers.view`: reachable by email and by SMS, confirmed in the
  period against the one before, waiting to confirm).

**Consequences.** Click rates count people who clicked at least once; link checkers in some mail systems may
click on their own, so rates are indications, not exact figures.

## ADR-0054 — Media contacts and press release distribution (2026-10-21)

**Context.** PLAN.md §5 asks for a list of journalists and outlets for press release distribution; §8 gives
media contacts to the Communications Officer.

**Decision.**

- Admin → Communication → Media contacts for `media_contacts.manage`: name, outlet, position, email (one
  contact per address), phone, the languages they take in order of preference, and notes for staff. Name,
  email, phone and notes are encrypted; contacts are business contacts and can be edited or deleted.
- A published press release, statement or speech has "Send to the media" on its edit page: to every contact
  who takes press releases or to those chosen. Each receives, in the first of their languages the text is
  published in (or its closest language), the title, kicker, summary and a link to the full text on the
  portal. Sent in the queue; the post's "Sent to the media" tab lists each contact, language, outcome, time
  and sender.
- Every press email carries an opt-out link and the one-click headers; `/{language}/press/opt-out/{token}`
  asks first, then stops further releases to that contact.
- Privacy requests find, copy and delete media contacts.

**Consequences.** Until the branch office's mail account is set, press emails go to the mail log on staging.

## ADR-0055 — Notices by Telegram (2026-10-21)

**Context.** PLAN.md §7 asks for alerts in the portal, by email and by Telegram; editors in the region use
Telegram more than email.

**Decision.**

- Telegram is a third channel in each person's notice preferences, beside the portal and email. It is offered
  only once the bot token is saved in Settings → Integrations (the same token the Telegram import uses) and
  the person linked their account.
- Linking: Notifications → "Connect Telegram" shows a link to the bot with a one-time code valid for a day;
  pressing Start in Telegram sends "/start CODE" to the portal, which stores the chat (encrypted) on the
  account and confirms in the chat. "/stop" in Telegram, or "Disconnect Telegram" in the portal, unlinks.
  Both are in the audit log.
- The bot's messages reach the portal through a webhook (`POST /telegram/webhook`, no session) that is read
  only when it carries the secret header given to Telegram, derived from the app key;
  `php artisan telegram:webhook` registers it once the token is saved.
- A notice by Telegram is plain text: its title, its lines and the admin link, in the person's admin language.

**Consequences.** Until the branch office creates the bot with BotFather and saves its token, Telegram stays
hidden; nothing else changes.

## ADR-0056 — Public API with keys (2026-10-21)

**Context.** PLAN.md §9 and §13 (Phase 8) ask for a versioned public API, first for the branch office's mobile
app; the open question in the Phase 8 plan settled on one key per app (the mobile app, office kiosks) and no
open access.

**Decision.**

- `/api/v1` is read-only JSON for what the public site publishes: posts and pages, events, documents
  (public ones only), offices and leaders, projects and search. Each item has the same fields the public page
  shows, the address of that page and, on a single record, the rendered text (`body_html`) and its other
  languages. Records are found by id (ULID) or slug.
- The language comes from `?lang=`, then Accept-Language, then the site's default; content falls back as on
  the site (Harari → Amharic → English) and every item says which language it is in. Unknown languages and
  kinds get 422.
- Every request needs a key in the `X-Api-Key` header. Keys are issued by Super Admins under Administration →
  API keys (`integrations.manage`), each with scopes (`content`, `events`, `documents`, `directory`,
  `projects`, `search`), a requests-per-minute limit (60 by default, at most 600), an optional expiry and
  notes. The key (`hpp_` and 40 random characters) is shown once; only an HMAC of it with the app key is
  stored, with its first 12 characters to tell keys apart. Keys are revoked, not deleted; changes are in the
  audit log; the last use is noted at most once a minute.
- Over the limit the API answers 429 with Retry-After; an address that sends 20 wrong keys in a minute is
  refused for the rest of it.
- Answers carry an ETag (304 on If-None-Match), `Cache-Control: private, max-age=60` and `Vary: X-Api-Key`,
  so Cloudflare does not keep them. No session or cookies. Errors are JSON `{"message": ...}` in the
  language asked for.
- The OpenAPI 3.1 description is at `/api/v1/openapi.json` without a key; a test checks it lists every route.
- API searches are not counted in the search insights.

**Consequences.** Nothing is open until a Super Admin issues a key. Breaking changes need `/api/v2`; new
fields may be added to v1. Changing `APP_KEY` invalidates every API key, as it does the other keyed hashes.

## ADR-0057 — Member API for the member app (2026-10-21)

**Context.** PLAN.md §13 (Phase 8) names a public API for a mobile app; the Phase 8 plan gives the app what
the member portal offers (ADR-0049): sign-in by SMS code, the card with its QR check address, the member's
event registrations and members-only documents.

**Decision.**

- The app sends its API key (scope `members`) on every request. `POST /api/v1/member/sign-in` with the phone
  sends the same six-digit code as the portal (ten minutes, five tries, five codes a day, a minute between
  codes) and always answers 202 with the same message, so it never tells whether a number is a member's.
  `POST /api/v1/member/token` with the phone, the code and a device name returns a member token
  (`hppm_` and 48 random characters), shown once; the app sends it as `Authorization: Bearer`.
- Tokens are stored as an HMAC with the app key, the device name encrypted. They last 90 days
  (`PORTAL_MEMBER_TOKEN_DAYS`); a member is signed in on at most five devices, the oldest sign-in going
  first. A token stops working at once when the member is suspended, expelled, resigned or erased, when the
  member signs out (`DELETE /api/v1/member/token`), or when an officer chooses "Sign out of the member app"
  on the member's page (noted in the member's history). Erasure deletes the tokens.
- `GET /member/me` gives the member's details and card (number, valid, valid until, and the verification
  address for the QR code), `/member/registrations` the registrations made with the phone or email on the
  record with their tickets, `/member/documents` the members-only documents, downloaded through
  `/member/documents/{key}/download` and noted in the audit log as in the portal.
- Member answers are `Cache-Control: private, no-store`. Sign-in steps also use the membership rate limit
  per address.
- JSON answers keep Ethiopic text and slashes unescaped, which halves the size of Amharic answers.

**Consequences.** The portal and the app share the sign-in code, the registrations query and the
members-only file handling. Publishing the app itself (stores, developer accounts) is for the branch office.

## ADR-0058 — Sign-in with Fayda through eSignet (2026-10-21)

**Context.** PLAN.md §11 and §13 (Phase 8) ask for Fayda/eSignet login for members. eSignet is the National ID
Program's OpenID Connect provider; the branch office must be registered with it as a relying party, which it
is not yet. The plan says a member is matched by their Fayda number, but eSignet gives each relying party its
own identifier for a person (`sub`), not the Fayda number.

**Decision.**

- OpenID Connect with the authorization code, PKCE (S256) and `private_key_jwt`: the portal signs its client
  assertion with its own RSA key, made on the server by a Super Admin under Settings → Integrations
  ("Create the Fayda signing key") and stored encrypted; the page shows only the public key as a JWK (its
  `kid` is the RFC 7638 thumbprint) and the one return address, `/member/fayda/callback`, to register.
- The eSignet address, the client id and the `acr_values` (default `mosip:idp:acr:generated-code`) are
  saved there too; sign-in is offered only when it is switched on and all of them and the key are saved. The
  OpenID configuration and keys are read from eSignet's well-known addresses and kept for an hour; a token
  signed with a key not seen before makes the portal read the keys again once.
- The id_token is checked (signature, issuer, audience, nonce, expiry with a minute's leeway) and the userinfo
  answer, signed by eSignet, must be for the same `sub`. State, nonce and verifier live in the session for
  ten minutes; the language travels with them.
- Deviation from PLAN.md: a member is matched by the Fayda `sub`, linked once, not by the Fayda number. The
  link is made from the member portal while signed in ("Link my Fayda ID"), or on the first Fayda sign-in
  when the phone Fayda holds is the phone of exactly one member who may use the portal. Only a keyed hash of
  `sub` is stored; one Fayda identity belongs to one member. Linking and unlinking are in the member's
  history and the audit log; erasure removes the link.
- Fayda sign-in is for the member portal; the member app keeps the SMS code (ADR-0057) until the app adds
  its own eSignet flow.

**Consequences.** Until the branch office is registered, nothing changes for members. Replacing the key stops
Fayda sign-in until the new public key is registered.

## ADR-0059 — Sharing posts to Facebook and Telegram from the admin (2026-10-21)

**Context.** PLAN.md §13 (Phase 8) asks for posting to social networks from the admin. The Phase 8 plan chose
the Facebook page and the Telegram channel; X/Twitter needs a paid API and is left out.

**Decision.**

- A published post's edit page has **Share** for staff with the new `social.share` permission (Publisher,
  Communications Officer, Admin; the Media Manager keeps the sources but does not edit posts, PLAN.md §8): choose the Facebook pages and
  Telegram channels among the social sources (ADR-0031), one language the post is live in, and optionally own
  words; otherwise the title and summary in that language. The post's link is always added.
- Each account gets its own row in `social_shares` (platform, account, language, text, status, the shared
  post's id and address, error, who and when), sent through the queue one after the other; one refused account
  does not stop the others. The post's **Shared** tab lists them; each share is in the audit log.
- Facebook: `POST /{page}/feed` on the Graph API with `message` and `link`, using the page token kept with the
  source or the one under Integrations; the token needs the `pages_manage_posts` permission. Telegram: the
  portal's bot posts with `sendMessage` to `@channel` (or a numeric chat id); the bot must be an administrator
  of the channel.
- Nothing is shared automatically on publishing: staff choose when and where.

**Consequences.** Until the branch office gives a page token allowed to publish and makes the bot an
administrator of the channel, shares fail with the platform's reason shown in the tab; nothing else changes.

## ADR-0060 — Donations module, off by default (2026-10-21)

**Context.** PLAN.md §13 (Phase 8) lists a donations module "if permitted". Ethiopian political-finance rules
and the party's own decision govern whether and how a party branch may receive gifts; the branch office has
no online payment (Telebirr, CBE Birr) merchant account.

**Decision.**

- **Settings → Donations** (Super Admin only, `settings.manage`) holds the switch, off by default, with the
  currency, the smallest pledge, the party's bank accounts and the texts per language. While off, `/donate`
  answers 404 and nothing is shown anywhere.
- The donate page takes a pledge: name, phone and/or email, amount, an optional purpose, "do not name me in
  public lists", and consent, with the spam checks of the other public forms. The donor gets a reference
  (`DON-XXXX-XXXX`, letters and digits that cannot be confused) to write on the bank transfer, on the page
  with the accounts and instructions, and by SMS when they gave a phone.
- Names and contacts are encrypted and found by keyed hashes; the audit log keeps amounts, status and
  receipts only. **Communication → Donations** (`donations.view` / `donations.manage`, Admins by default)
  lists pledges with this year's totals; staff record the payment the bank shows (amount, day, bank
  reference), which gives the next receipt number (`RCPT-000001`, never reused) and thanks the donor by SMS,
  or cancel a pledge with a reason. Pledges are never deleted.
- A printable receipt, in the donor's language, opens from the donation's page at
  `/admin/donation-receipts/{id}` for staff who may see donations, behind the admin address restriction, and
  each print is in the audit log.
- Payments go through a `PaymentGateway` interface; bank transfer is the only driver. A Telebirr or CBE Birr
  driver later returns the provider's payment page from `start()` and records payments from the provider's
  callback with the same action.
- Privacy requests find and copy donations; erasure removes the donor's name and contacts but keeps the
  amounts and receipt numbers the accounts need.

**Consequences.** The module is complete but closed. Before switching it on, the branch office needs the
party's and its legal advisers' approval, the bank accounts, and the texts in each language.

## ADR-0061 — Performance review and Octane readiness (2026-10-21)

**Context.** PLAN.md §9 and §13 (Phase 8) name Laravel Octane. The staging host is shared cPanel (ADR-0002),
where no long-running PHP process may run; the production host is not chosen yet (PLAN.md §14).

**Decision.**

- **No Octane on shared hosting.** PHP there is started per request by LiteSpeed, and the queue is drained by
  cron. On a production VPS, Octane (FrankenPHP or Swoole) with Redis, a Supervisor-run queue worker and
  `--max-requests` is possible without code changes; the decision is taken with the production host.
- **The code is kept ready for long-running workers:**
  - no request state in static properties: a test fails on any new one outside the Filament configuration,
    with two allowed (a fixed transliteration table and the language resolver set at boot);
  - the current site, the one static that held request state, now lives in a scoped container instance,
    which Octane and the queue worker reset for every request and job;
  - settings are scoped by the settings package; the app registers no singletons of its own;
  - a test serves request after request from one application instance, as a worker does, and checks that
    language, site and addresses follow each request.
- **N+1 review:** lazy loading already fails in development and tests and is logged on staging. Tests now check
  that the news, events, documents, leaders, offices and projects listings, and the matching API listings,
  make no more queries for eight records than for two.
- **Slow queries:** a request or job that spends more than a second in the database
  (`PORTAL_SLOW_QUERIES_MS`) is logged with where it happened and the query that crossed the limit.

**Consequences.** Moving to a VPS with Octane needs configuration only (ADR-0002 drivers plus `octane:start`).

## ADR-0062 — Brand from the party logo and Harar; new public design (2026-10-21)

**Context.** PLAN.md §12 left the brand colour open ("confirm the official brand guide"); the portal used
`#3151ED`, the blue of another branch's site. The owner supplied the Prosperity Party logo (a ring from gold to
sky blue around two hands holding three figures, in sky blue, gold and magenta, over the Amharic and English
names) and approved a design canvas ("Harari PP Portal Design") that grounds the logo's colours in Harar's:
the plaster and terracotta of the Jugol walls and the woven basket plates (sagaro) on the walls of Harari houses.

**Decision.**

- Colours (`resources/css/tokens.css`): Prosperity Blue `#0B6CA8` (primary 600, 5.6:1 on white) with night
  blue `#083A5C` (900) for heroes and figure bands; Prosperity Gold `#F5B800` for calls to action, always with
  ink text; Basket Magenta `#C2185B` for the Women's League and speeches; Jugol Terracotta `#B4532A` (accent)
  for kickers, statements and Harar sections; Jugol Plaster `#F7F2E9` for warm section grounds instead of
  grey; Ink `#14161A` for text, the top bar and the footer. Sky blue `#29A8E0` appears only in patterns. The
  admin uses the same blue.
- Layout: a dark top bar (both calendars, hotline, the four languages, member login), a white header with the
  round logo, search and a gold "Join the party" button, the main menu on its own row with full-width panels
  (native `<details name>`, no script), and an ink footer under a band woven in the logo's colours. On phones
  and tablets the menu sits behind one "Menu" button and lists every section in full.
- Until an editor builds a header menu in Admin → Menus, the design's sections are shown: About, News &
  Media, Offices, Leagues, Programmes, Get involved, Resources and Contact, each pointing at the site's own
  listings (filtered by post, office or document type where that helps). Donations appear only once they
  are switched on.
- Homepage sections follow the canvas: featured stories on night blue, the leader's message in a gold ring,
  figures on night blue with coloured edges, values with plate icons, events on plaster, a Harar heritage
  band (a wall of basket plates), league cards in blue and magenta. Post types carry their colour on badges.
- Headings are heavier and tighter (800, negative tracking); Ethiopic keeps its smaller heading scale.
- Favicon from the logo: the emblem (hands, figures and rays) on a white disc at 16–96 px, where the text and
  ring cannot be read; the whole round logo for phones (`apple-touch-icon.png`), installed apps and the
  header and footer (`public/brand/`).

- Pages open on a band (`<x-page-hero>`) with breadcrumb, title and lead in the tone the canvas gives
  each section; detail pages put their title on the plaster band; filters are chips (`<x-ui.chip>`).

**Consequences.** Every page takes the new colours from the tokens. The design canvas remains the reference
for later pages; the national party's official vector logo replaces the raster copy when the branch supplies
it.

## ADR-0063 — Content pack from public sources; the branch channel publishes (2026-10-21)

**Context.** The owner asked for the site to be filled with real, current content gathered online,
leaders and images included, and chose to publish everything at once, each item with its source, and
to correct it in the admin afterwards. The earlier rules (docs/content/sources.md) kept imports as
drafts and leaders unlisted until the branch confirmed them. There is no admin login or server shell
available to the developer for loading content by hand, and the branch's news is written in Amharic.

**Decision.**

- `database/content/pack.json` holds the content found on 2026-10-21 in public sources (the branch's
  Telegram channel, the regional government's channel, ENA, Fana, the election board, UNESCO, the
  statistics service): the branch office and the nine woredas, the women's and youth wings, the
  leaders whose positions are published, with five portraits that are captioned or attached to posts
  about that person alone, three pages (About, History, Harar), Harari Tourism Day, the leader's
  message and four sourced figures for the homepage, and the branch's Telegram link.
- `php artisan content:load-pack` runs on every deploy after the migrations. It loads each record once,
  remembered by its key in `content_pack_items`, as the first Super Admin, published; what editors
  later change or delete is never put back. Settings and homepage sections an editor already set stay.
- Leaders carry the public source of their position (`source_url`, `source_date`), shown on the
  profile. A new group, "Regional government and councils", lists the region's president, deputy
  presidents and council speakers, who are not party bodies.
- Left out, for the branch to confirm: officers whose party posts are unclear after the October 2026
  reshuffle (two different deputy heads are named; three officers moved to council or government
  posts), photos that could only be inferred, and any contact details, since none are published.
- Content sources get "Publish stories at once". It is on for the branch's own channel only: its
  stories go live when imported, dated when they were posted, and the drafts imported before are
  published once by the loader. The government office's stories stay drafts until it grants
  permission to republish.
- Posts written only in Amharic are listed on the English and Afaan Oromo pages too, marked
  "In አማርኛ" and linking to the Amharic page, as Harari pages already did (ADR-0014,
  `portal.content_fallbacks`).

**Consequences.** The site shows real people and facts, each traceable to a public source; mistakes
are corrected in the admin and stay corrected. Names in English are transliterations where no
official spelling exists; the Afaan Oromo pages and terms are listed in docs/translations-review.md.
The branch confirms the open points in docs/content/sources.md.

## ADR-0064 — The admin in the party's brand (2026-10-22)

**Context.** After the public redesign (ADR-0062) the owner asked for a state-of-the-art design for the
admin dashboard too. The admin is Filament 5 with its stock look and widget views styled inline. A
compiled Filament theme would need Filament's CSS from `vendor/` at build time, which the asset job
and the host build do not have.

**Decision.**

- `public/brand/admin.css`, plain CSS loaded after Filament's (whose styles sit in cascade layers, so
  plain rules win without `!important`), styles Filament's hook classes: a night-blue sidebar with gold
  group labels and a gold edge on the current item, a faint basket plate at its foot; warm plaster
  ground; white cards with soft shadows; stats with coloured top edges in the logo's colours; primary
  buttons in the brand blue with white text (Filament's own pick was a pale shade with dark text).
- Sign-in and other simple pages sit on night blue with a basket plate; the card carries the round logo.
- The brand block (round logo, name, "Admin") replaces the plain name; fonts are the public site's
  self-hosted Inter and Noto Sans Ethiopic, so no font request leaves the site.
- The dashboard opens on a welcome band: greeting, today's date in both calendars, a link to the public
  site and the page's own actions. Quick actions are tiles; widget views use shared `hpp-*` classes
  instead of inline styles.

**Consequences.** No folder under `public/` may share its name with a route (a `public/admin` folder
made the server answer `/admin` with 403); a test checks this. Upgrading Filament may rename a hook class; the admin brand test and a look at the
dashboard after each Filament upgrade catch it. Dark mode keeps the dark sidebar and darkens the ground
and cards.

## ADR-0065 — Photos of Harar, one story card, league and office pages that read well (2026-10-22)

**Context.** The owner found the news section disorganised (stories with and without photos side by
side, uneven heights), the Women's Wing page poorly laid out (an empty column, a huge chair card), and
asked for real photos of Harar — Jugol and the administration — and pages of international standard.

**Decision.**

- Seven photos of Harar from Wikimedia Commons, all under open licences (CC BY 2.0/3.0, CC BY-SA
  2.0/4.0): Harar Gate, the Jugol panorama, a Harari home hung with basket plates, a Jugol alley, the
  wall at Buda Gate, the Grand Mosque and the Harari Culture and Tourism Bureau building. They ship as
  AVIF and WebP in several widths with a JPEG fallback (`public/images/harar/`, `App\Support\HararPhotos`,
  `<x-harar-photo>`), and every use shows the author, licence and a link to the source
  (`<x-harar-credit>`), as the licences require.
- The homepage opens on Harar Gate under night blue when no story is featured; the heritage band is a
  photo mosaic with three UNESCO facts. The About, History and Harar pages get the panorama, the bureau
  building and the wall as landing photos through the content pack, which now has "updates": each one
  is applied once and fills only what is still empty, so an editor's choice stays.
- One story card everywhere (`<x-news-card>`): a 16:10 picture or, without a photo, a placeholder in the
  post type's colour with the party's logo and a basket plate; labels on the picture; the title and
  summary clamped; the date at the foot, so rows line up. On the homepage the newest story leads across
  two columns and two rows.
- Leagues have a colour (`leagues.accent`, chosen in the admin; the women's wing magenta, the youth wing
  blue). A league page opens in that colour with its chair beside the name, then an overview with the
  wing's sourced recent work, the leadership as compact rows, and ways to take part. Office pages put
  their sub-offices in the main column as tiles and their people as compact rows beside them.

**Consequences.** The Harar photos are the site's own files; replacing one means new files and a new
entry in `HararPhotos`. Story cards no longer depend on every post having a photo.

## ADR-0066 — Motion, and a leadership page that leads with the region's government (2026-10-22)

**Context.** The owner asked for the homepage opening to carry the basket-plate circles and more motion,
for the leadership page to reach the same standard, and for the region's government and councils to
come first, then the secretariat and the rest.

**Decision.**

- Motion that never hides text: things slide up and sharpen from a slight blur but are never
  transparent, so contrast holds at every frame and the headline counts as painted at once (no
  Lighthouse penalty). The Harar Gate photo zooms slowly, the plates turn very slowly, the welcome
  rises in steps, and cards and tiles rise into place as they scroll in (CSS scroll timelines where the
  browser has them, still elsewhere). Nothing moves for visitors who ask for reduced motion.
- The leadership page opens on night blue with links to each group and its count; the head of the
  branch office is featured; the groups follow in `LeaderBody::displayOrder()`: the region's government
  and councils, then the party's bodies (executive and central committee, secretariat, office heads,
  wings), each with a short description. People without a photo get initials on night blue with a
  basket plate instead of a blank circle. The page ends with how the branch is organised.

**Consequences.** The enum keeps its order for the admin; only the public page reorders.

## ADR-0067 — Membership applications confirmed by SMS, email or not at all (2026-10-22)

**Context.** Applications to join were confirmed only by a code sent by SMS (ADR-0044). The branch has no
SMS gateway yet, so nobody can finish an application on staging; the owner asked for SMS verification
to be switchable from the admin, with email only or no verification as the other choices.

**Decision.**

- Administration → Membership (`MembershipSettings::verification`): "A code by SMS" (default),
  "A code by email" or "No code". Super Admins change it; those who may view settings see it; every
  change is recorded like other settings.
- By SMS nothing changes. By email, the email address is required and the phone optional; the code
  (`ApplicationCodeMail`, sent at once) goes to the address and `email_verified_at` is set. With no code,
  the last step asks only for consent and submits (`ApplyForMembership::submit`); officers check the
  details when they review. The "application received" message goes by SMS when SMS is in use,
  otherwise by email when an address was given.
- The status page finds an application by its reference with either the phone or the email address.

**Consequences.** Without a code the portal cannot prove a phone or address belongs to the applicant;
the review step carries that check. The member portal's sign-in still uses SMS codes (or Fayda).
