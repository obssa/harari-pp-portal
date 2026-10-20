# Go-live runbook — from staging to the production site

For the person who puts the portal live once the branch office has chosen the production host and domain
(open question 1). Staging (`docs/runbook/server.md`) stays as it is; production is a second, separate
installation with its own database, `.env`, keys and backups. Nothing here deletes staging.

Tick each line and note the date. Steps marked **owner** need the branch office's accounts or decisions.

## 1. Before the day

| # | Step | Who |
|---|---|---|
| 1 | Production host with PHP 8.3 (extensions as in `.github/actions/setup-php`), MySQL 8 or MariaDB 10.11+, `mysqldump`, cron, SSH; at least 5 GB for media and backups | owner |
| 2 | Domain chosen and added to Cloudflare; DNS record proxied; SSL/TLS "Full (strict)", minimum TLS 1.2 | owner |
| 3 | Mailbox for outgoing mail (SMTP host, port, user, password) and the sender address | owner |
| 4 | Legal texts approved (privacy, terms, accessibility); privacy contact named | owner |
| 5 | Amharic and Afaan Oromo strings reviewed by native speakers (`docs/translations-review.md`); Harari strings entered or the Harari language hidden until ready | owner |
| 6 | Real content entered or imported on staging and checked: leaders, offices, leagues, posts, events, documents | communications staff |
| 7 | Official social media accounts and a Facebook page token | owner |
| 8 | Optional: Sentry DSN, Cloudflare API token, SMS gateway, Turnstile keys, an off-site backup bucket | owner |

## 2. Install production

1. Create the database and user; a second empty database for the restore drill.
2. Add a GitHub environment `production` with its own secrets (`DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_PORT`,
   `DEPLOY_SSH_KEY`, `DEPLOY_KNOWN_HOSTS`) and variables (`DEPLOY_PATH`, `DEPLOY_PHP_BIN`, `DEPLOY_URL`), and a
   deploy job for it that runs only on a tag (`v*`) after CI. Staging keeps deploying from `main`.
3. Write the production `.env` from `.env.example`:
   - `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://<domain>`, a new `APP_KEY`
     (`php artisan key:generate --show`, kept in the owner's password manager);
   - database, mail (`MAIL_MAILER=smtp`), `BACKUP_ARCHIVE_PASSWORD` (new, kept in the password manager),
     `DB_DRILL_*`, `SENTRY_LARAVEL_DSN` if chosen, `SESSION_SECURE_COOKIE=true`;
   - `PORTAL_DEMO_ACCOUNTS=false`; `PORTAL_ADMIN_ALLOWED_IPS` if the branch office wants it.
4. Add the two cron lines from `server.md` (scheduler and queue worker) for the production folder.
5. Deploy the release tag. The deploy runs the migrations, the permission sync and the caches.
6. `php artisan portal:create-super-admin` for the first Super Admin; sign in, set up two-step sign-in, and
   turn on Settings → Security → "Require two-step sign-in".

## 3. Content and settings

1. Copy the content from staging, either by entering it again or by restoring a staging backup into
   production (`portal:restore-database` with the staging archive password set temporarily) and then:
   `php artisan portal:sample-content --remove`, delete the demo accounts under Users, and empty the test
   data (tickets, registrations, page view counts) under Administration → Privacy or in the database.
2. Administration → Site settings: name, contact email and phones, address, office hours, social links.
3. Administration → Integrations: mail test (System health → Send a test email), Turnstile, social tokens,
   machine translation (only if the owner agreed to send texts to a provider), Cloudflare zone and token.
4. `php artisan portal:legal-pages` if the approved texts are not already in; remove the "Draft for legal
   review" callout from each page once approved.
5. Roles: give each staff member their account and role; office and language scopes where needed.

## 4. Checks before announcing

| Check | How |
|---|---|
| Health | Administration → System health: every check "Working" (mail included) |
| Backups | System health → Back up now; the backup appears; run `php artisan portal:restore-drill` once |
| Search engines | production sends no `X-Robots-Tag: noindex`; `/robots.txt` and `/sitemap.xml` open; submit the sitemap in Google Search Console and Bing |
| Cache | Cloudflare Cache Rule from `server.md` in place; a second request to `/en` shows `cf-cache-status: HIT` |
| Headers | securityheaders.com grade A or better; HTTPS only |
| Lighthouse | point `lighthouserc.json` at the production URL once; all pages ≥ 0.9 |
| Languages | open the homepage, a story, an event and the contact page in every language; no raw keys |
| Forms | send a contact message and register for an event; the email and SMS arrive; the ticket appears in the inbox |
| Monitoring | an uptime monitor on `https://<domain>/up` with alerts to the owner; Sentry receives a test error if set up |

## 5. On the day

1. Announce a short content freeze on staging.
2. Deploy the final tag to production; check `/up`.
3. Point the public domain at production (if it was not already) and purge the Cloudflare cache.
4. Walk the checks in section 4 again in five minutes.
5. Tell staff the production admin address; staging remains for training and testing, marked as a test site.

## 6. Rolling back

- A bad release: the deploy job restores the previous code automatically when the smoke test fails;
  otherwise redeploy the previous tag.
- A bad migration or data change: `php artisan down`, then `portal:restore-database` with the
  `before-migrate-…` backup the deploy made, `php artisan up` (`docs/runbook/restore.md`).
