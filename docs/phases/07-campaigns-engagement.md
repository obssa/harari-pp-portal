# Phase 7 plan — Campaigns and engagement

Written 2026-10-21 from `docs/PLAN.md` §2 (newsletter with double opt-in and SMS option), §4 (newsletter
preference centre, footer and homepage sign-up), §5 (contacts and subscribers, campaigns, media contacts),
§7 (campaign results and subscriber widgets, Telegram alerts), §8 (Communications Officer) and §10
(`subscribers`, `campaigns`, `campaign_deliveries`), after Phase 6 (`docs/phases/06-membership-volunteers.md`,
tag `v0.6.0`). The branch office's accounts (mail, SMS gateway, Telegram bot) are gathered at the end, as the
owner decided on 2026-10-21; until then email goes to the mail log and SMS to the SMS log on staging.

**Gate (PLAN.md §13, Phase 7):** first campaign sent with a delivery report. On staging this is shown with
sample subscribers and the log drivers; real sending needs the mail and SMS accounts.

## Steps

| Step | Content | Status |
|---|---|---|
| 7.1 | Subscribers: newsletter sign-up by email and/or phone (topics, language, channel) on its own page, in the footer and on the Get Involved page; double opt-in (a link by email, a code by SMS); preference centre and one-click unsubscribe by a private link; admin list (masked, filters, export with `subscribers.export`); privacy requests and retention extended to subscribers | done |
| 7.2 | Campaigns: email (subject and text per language) and SMS (text per language, length and parts shown) to segments (subscribers by channel, topic and language; members by woreda and status; active volunteers), audience count before sending, test send to oneself, send now or at a set time, queued in batches within sending limits, unsubscribe link and header in every email, permission `campaigns.send` | done |
| 7.3 | Delivery reports: per recipient queued, sent, failed or unsubscribed; click counts through signed redirect links; optional open counting (off by default); campaign results page and CSV; dashboard widgets "Campaign results" and "Subscribers" | done |
| 7.4 | Media contacts: journalists and outlets with beats, languages and email; send a published press release or statement to chosen contacts in their language with a link to the full text; delivery log; opt-out link | done |
| 7.5 | Telegram notices: staff link their Telegram account to the portal's bot with a one-time code; "Telegram" becomes a channel in notice preferences beside in-app and email; the bot token is an integration setting | done (live with the branch office's bot) |
| 7.6 | Manual chapter, first sample campaign on staging with its delivery report, phase report | done |

## Open questions (do not block the build; defaults in brackets)

1. Email sending [the branch office's SMTP account; Mailgun or Amazon SES later through Laravel's mail drivers].
2. SMS gateway [the log driver until Ethio Telecom or Africa's Talking is chosen; the same account as membership].
3. Open tracking [off: invisible images are blocked by many mail apps and count people without asking;
   clicks are counted through the portal's own links].
4. Party notices to members and volunteers [allowed as campaigns to those segments, always with an opt-out
   link; members who opt out get no campaigns, only messages about their own membership].
5. Sending limits [email 200 a minute, SMS 30 a minute; set in `.env`].
6. Telegram bot [created by the branch office with BotFather; staff link their own account].
7. Unconfirmed sign-ups [deleted after 7 days].
8. Media contacts [business contacts kept for press work; every email carries an opt-out link].

## Report (2026-10-21)

Staging: https://hararipp.orcamind.dev. Tag: `v0.7.0`.

### Gate (PLAN.md §13, Phase 7)

"First campaign sent with delivery report" is shown on staging: a sample SMS campaign, labelled
"(staging test)", went through the real queue to the sample subscriber who signed up and confirmed by code
earlier, and its report reads 1 recipient, 1 sent, none failed or skipped; the message carried its stop link,
which opens the opt-out page. Real campaigns need the branch office's mail (SMTP) and SMS accounts; until then
messages go to the mail and SMS logs.

### What was built

| Step | Content | Commit |
|---|---|---|
| 7.1 | Newsletter and SMS sign-up with double opt-in, preference centre, one-click unsubscribe, footer form, subscribers list | f0d60b7 |
| 7.2 | Email and SMS campaigns to subscribers, members or volunteers; test, send now or later, batches within limits, stop links | 3f016de |
| 7.3 | Delivery reports, click counts through the portal's links, optional open counting, dashboard widgets | 1fa276e |
| 7.4 | Media contacts and "Send to the media" for press releases, statements and speeches, with a log and opt-out | c142f30 |
| 7.5 | Staff notices by Telegram with account linking by one-time code and a secret-checked webhook | 906e004 |
| 7.6 | Manual chapter 6 in English and Amharic with screenshots, staging sample campaign, this report | this commit |

Decisions are recorded as ADR-0051 to ADR-0055 in `docs/DECISIONS.md`. Open question 3 (open tracking)
kept its default: off, with clicks counted through the portal's own links.

### Quality at the end of the phase

| Measure | Value |
|---|---|
| Tests | 879 Pest tests in the main suites and 6 browser tests, all passing |
| Line coverage | 89.3% overall; `app/Domain` 88.9%, `app/Services` 91.9% (CI minimum 80% each) |
| Static analysis | Larastan level 6, no errors |
| Accessibility | axe on the public pages including the newsletter page: no serious or critical issues |
| Translations | English, Amharic and Afaan Oromo complete; Harari keys present and empty |

### For administrators on staging

1. **Administration → Roles**: the Communications Officer writes and sends campaigns to subscribers and keeps
   media contacts; member and volunteer audiences need the membership permissions. `subscribers.export`
   stays with Admin and Super Admin.
2. After saving the Telegram bot token in **Settings → Integrations**, run `php artisan telegram:webhook` on
   the server once (see the server runbook).
3. Sample data from this phase is labelled: the subscriber 0900000505 and the campaign "Sample first campaign
   (staging test)". Remove the subscriber with a privacy request before launch.

### Waiting for the branch office

- The mail account (SMTP) for confirmations, campaigns and press releases, and the SMS gateway account.
- The Telegram bot created with BotFather.
- Media contacts: the journalists and newsrooms the branch office works with.
- Legal approval of the newsletter consent text and of campaigns to members and volunteers.
