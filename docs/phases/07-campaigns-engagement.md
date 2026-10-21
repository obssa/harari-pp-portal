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
| 7.4 | Media contacts: journalists and outlets with beats, languages and email; send a published press release or statement to chosen contacts in their language with a link to the full text; delivery log; opt-out link | |
| 7.5 | Telegram notices: staff link their Telegram account to the portal's bot with a one-time code; "Telegram" becomes a channel in notice preferences beside in-app and email; the bot token is an integration setting | |
| 7.6 | Manual chapter, first sample campaign on staging with its delivery report, phase report | |

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
