# Security review (Phase 5, step 5.3)

Reviewed 2026-10-20 against PLAN.md §11 (OWASP ASVS level 2 baseline) on the code at step 5.3 and on the
staging site. "Met" means in place and covered by tests or checked on staging; open items say who acts.

## Checklist

| Area | Requirement | Status | Evidence |
|---|---|---|---|
| Forms | CSRF on every form | met | Laravel's request-forgery check on the admin and the public form pages (the only public routes with a session); public pages have no state to forge |
| Headers | Content-Security-Policy with nonces | met (public) | every public response carries a per-request nonce for scripts and styles; only `challenges.cloudflare.com` (Turnstile), `youtube-nocookie` and OpenStreetMap frames are allowed (ADR-0010, ADR-0030) |
| | | accepted (admin) | the admin's CSP allows `unsafe-inline` and `unsafe-eval`: Livewire and Alpine need them. The admin is behind sign-in, two-step sign-in and, optionally, an address allow-list |
| | HSTS, X-Frame-Options, Referrer-Policy, Permissions-Policy, nosniff, COOP | met | checked on staging: `max-age=31536000; includeSubDomains`, `DENY` (PDF viewer frames `SAMEORIGIN`), `strict-origin-when-cross-origin`, camera/microphone/geolocation/payment/usb/topics off |
| Rate limits | sign-in | met | Filament: 5 attempts a minute, two-step codes on the same form; password reset 2 a minute |
| | forms, registrations, ticket look-up, search | met | `throttle:forms` (5 a minute and 30 an hour per address), `throttle:search` (30 a minute per address) |
| | real client address | met | LiteSpeed takes the visitor's address from Cloudflare (checked in the audit log on staging: client addresses, not Cloudflare's), so limits are per visitor. The host must keep accepting that header only from Cloudflare |
| Bots | Turnstile, honeypots, time stamps | met | `SpamGuard` on every public form; Turnstile once keys are saved under Integrations |
| Uploads | MIME sniffing, re-encoding, SVG sanitising, no executables, private originals | met | `UploadInspector` and `UploadMedia` (ADR-0009): images re-encoded without EXIF, SVG sanitised, disguised scripts refused; originals and form attachments outside the web root; `uploads.htaccess` stops scripts in public uploads |
| | antivirus scan | not done (optional) | ClamAV is not available on the shared host |
| Data | encryption at rest for personal data | met | names, phones, emails, answers and notes of registrations and tickets, social credentials, webhook secrets and integration keys are encrypted with `APP_KEY`; sessions are encrypted; backups are AES-256 zips (ADR-0037) |
| | TLS 1.2+ | met (Cloudflare) | HTTPS only; HSTS. The minimum TLS version is a Cloudflare zone setting for the owner to keep at 1.2 |
| | secrets never in the repository | met | `.env` only on the server (mode 600); no host or account names in tracked files; GitHub secret scanning on the public repository |
| | dependency scanning | met | `composer audit` and `npm audit` in every CI run and every Monday (`security-audit.yml`); no Dependabot pull requests, because the repository takes no bot-authored commits |
| Accounts | two-step sign-in for privileged roles | met | compulsory for Super Admin, Admin and Publisher while Settings → Security asks for it (ADR-0018) |
| | password policy | met | at least 12 characters with upper and lower case letters and a number |
| | sessions invalidated on role change | met | `InvalidateUserSessions` on every role or permission change |
| | sign-in alerts | met | a sign-in from a network and a browser both unseen in 90 days alerts the person in the portal and by email (ADR-0038) |
| | admin IP allow-list | met (optional) | `PORTAL_ADMIN_ALLOWED_IPS`: addresses or CIDR ranges; empty by default |
| Audit | audit log kept 2 years | met | `activitylog:clean` daily, `config/activitylog.php` |
| Privacy | consent, purpose, export and deletion | partly | consent and purpose on every form and registration; export and erasure in step 5.6 |
| Errors | error reporting | met | Sentry once `SENTRY_LARAVEL_DSN` is set, without personal data or request bodies; until then the health page shows the newest errors from the log |
| Social | token expiry warnings | met | Facebook page tokens checked every morning; managers warned 14, 7 and 1 days before and when Meta refuses a token (ADR-0038) |
| Server | debug off, files private | met | `APP_DEBUG=false`; `/.env` answers 403; logs and private uploads 750, backups 700 |

## Penetration test checklist (before launch)

To be run against staging by the person doing the test, with the owner's written go-ahead:

1. Authentication: brute force on `/admin/login` stops after 5 attempts; two-step codes cannot be reused;
   password reset links expire and are single use; no account enumeration in messages.
2. Authorisation: every admin page and action refuses a role without the permission (deny by default,
   `StrictAuthorizationTest`); office- and language-scoped staff cannot open records outside their scope by
   changing an id in the address.
3. Input: public forms, search and the ticket look-up reject script and SQL injection attempts; rich text is
   sanitised on save; uploaded SVG, HTML and disguised PHP files are refused.
4. Session: cookies `Secure`, `HttpOnly`, `SameSite=Lax`; signing out ends the session on the server; a
   role change signs the person out everywhere.
5. Headers and TLS: the checks in the table above; no mixed content.
6. Information leaks: no stack traces (debug off), no `.env`, `.git`, `composer.json`, `storage/` or backup
   files reachable over the web; error pages translated and generic.
7. Rate limits: forms, registration, ticket look-up and search answer 429 when flooded.
8. Third parties: only Cloudflare Turnstile, YouTube (no-cookie, on request) and OpenStreetMap (on request)
   are loaded by the public site.

## Open items

| Item | Who |
|---|---|
| Choose an error tracker and save its DSN (`SENTRY_LARAVEL_DSN`) | owner (open question 3) |
| Keep Cloudflare's minimum TLS at 1.2 and its WAF managed rules on | owner (Cloudflare account) |
| Decide on an admin address allow-list (`PORTAL_ADMIN_ALLOWED_IPS`) once office networks are known | branch office |
| External penetration test against the checklist above | owner, before launch |
