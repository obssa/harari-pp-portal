# Phase 8 plan — Extensions

Written 2026-10-21 from `docs/PLAN.md` §13 (Phase 8: public API for a mobile app, Fayda/eSignet login for
members, posting to social networks from the admin, donations module if permitted, Octane), §9 (extension
points: versioned public API, integration framework, webhooks), §11 (auth) and §14, after Phase 7
(`docs/phases/07-campaigns-engagement.md`, tag `v0.7.0`). PLAN.md lists this phase "as prioritised": each step
is built so it stays off until the branch office provides what it needs (accounts, registrations, legal
approval). Those inputs are gathered at the end, as the owner decided on 2026-10-21.

**Gate (PLAN.md §13, Phase 8):** as prioritised. Here: each extension works end to end on staging with sample
data or provider test doubles, and is switched off (or limited to staff) until its inputs arrive.

## Steps

| Step | Content | Status |
|---|---|---|
| 8.1 | Public API `/api/v1`: read-only JSON for posts, pages, events, documents, offices, leaders, projects and search, per language, with filters, pagination, ETags and cache headers; API keys with scopes, rate limits and a last-used time, managed in the admin; OpenAPI description | done |
| 8.2 | Member API for a mobile app: sign-in by SMS code giving a revocable token, the member's card data and QR check address, own registrations, members-only documents | done |
| 8.3 | Fayda/eSignet sign-in for members (OpenID Connect with PKCE and signed client assertion): configured under Integrations, off until the branch office is registered as a relying party; a member is matched by their Fayda number | done (matched by the Fayda `sub`, ADR-0058) |
| 8.4 | Posting to social networks from the admin: share a published post to the Facebook page and the Telegram channel in a chosen language, with a log and the post's link; uses the tokens already kept under Integrations | done |
| 8.5 | Donations module, off by default: when switched on by a Super Admin after legal approval, a pledge form with bank-transfer instructions per language, an admin list with receipts and totals, and a payment driver interface for later Telebirr or CBE Birr accounts | done |
| 8.6 | Performance and Octane readiness: no request state kept in static properties, a check that the app boots and serves under a long-running worker, slow-query and N+1 review, the decision on Octane for the production host | done |
| 8.7 | Manual chapter, phase report | done |

## Open questions (do not block the build; defaults in brackets)

1. API consumers [the branch office's mobile app and office kiosks, each with its own key; no open access].
2. Fayda/eSignet [registration as a relying party with the National ID Program is needed; the code is ready
   and stays off until the client id and keys are saved].
3. Social networks to post to [the Facebook page and the Telegram channel; X/Twitter needs a paid API and is
   left out].
4. Donations [only if Ethiopian political-finance rules and the party allow it; pledges with bank-transfer
   instructions first, online payment only with a provider account].
5. Octane [not possible on the shared host; a production VPS could run it; the code is kept ready].

## Report (2026-10-21)

Staging: https://hararipp.orcamind.dev. Tag: `v0.8.0`.

### Gate (PLAN.md §13, Phase 8: as prioritised)

Each extension is built, tested end to end with sample data or provider test doubles, deployed, and closed
until its inputs arrive:

| Extension | On staging | Opens when |
|---|---|---|
| Public API (8.1) | `/api/v1/openapi.json` answers; requests without a key are refused in the language asked for | A Super Admin issues a key per app |
| Member API (8.2) | The member endpoints are described and refuse requests without a key | The mobile app is built and given a key |
| Fayda sign-in (8.3) | Off: no button, `/member/fayda` answers 404 | The branch office is registered with eSignet |
| Sharing (8.4) | "Share" on published posts for Publishers and Communications Officers | A page token that may publish; the bot is a channel administrator |
| Donations (8.5) | Off: `/donate` answers 404 | Legal approval, bank accounts and texts; a Super Admin switches it on |
| Performance (8.6) | Slow-query log on; site kept per request | — |

The API was not called with a real key on staging: issuing one from the command line needs server access that
was not available in this session, and a key is issued in a minute under Administration → API keys. The tests
cover every endpoint with keys.

### What was built

| Step | Content | Commit |
|---|---|---|
| 8.1 | Public read-only API with API keys, scopes, rate limits, ETags and the OpenAPI description | b9aadeb |
| 8.2 | Member API: SMS sign-in to a revocable token, card, registrations, members-only documents | 15fafab |
| 8.3 | Sign-in with Fayda through eSignet (PKCE, signed client assertion), off by default | a55cbc5 |
| 8.4 | Sharing published posts to Facebook pages and Telegram channels, with a log per account | a5733dc |
| 8.5 | Donations module, off by default: pledges, recorded payments with receipts, totals | 8ce516f |
| 8.6 | Octane readiness, query-count budgets, slow-query log | c4e004a |
| 8.7 | Manual chapter 7 in English and Amharic with screenshots, this report | this commit |

Decisions are recorded as ADR-0056 to ADR-0061 in `docs/DECISIONS.md`. The open questions kept their defaults,
with one change: a member is matched to Fayda by the identifier eSignet gives the portal, linked once, not by
the Fayda number, which eSignet does not give to relying parties (ADR-0058).

### Quality at the end of the phase

| Measure | Value |
|---|---|
| Tests | 941 Pest tests in the main suites and 6 browser tests, all passing |
| Line coverage | 89.6% overall; `app/Domain` 89.0%, `app/Services` 90.6% (CI minimum 80% each) |
| Static analysis | Larastan level 6, no errors |
| Query budgets | Public and API listings make no more queries for eight records than for two |
| Translations | English, Amharic and Afaan Oromo complete; Harari keys present and empty |

### For administrators on staging

1. **Administration → API keys**: issue one key per app with only the scopes it needs; revoke a key that leaked.
2. **Settings → Integrations → Fayda sign-in**: create the signing key and give the public key and return
   address to the National ID Program (runbook section "Fayda sign-in").
3. The new `social.share` permission went to Publishers and Communications Officers by the deploy; adjust under
   **Administration → Roles** if needed.
4. **Settings → Donations** stays off until the approval below.

### Waiting for the branch office

- Registration with the National ID Program as an eSignet relying party.
- A Facebook page token allowed to publish (`pages_manage_posts`), and the portal's Telegram bot made an
  administrator of the channel.
- The legal advisers' and the party's approval for donations, the bank accounts and the texts per language.
- The mobile app's developer, who receives an API key; publishing the app is for the branch office.
- The production host: Octane only on a VPS (ADR-0061).
