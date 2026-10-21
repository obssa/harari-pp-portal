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
| 8.7 | Manual chapter, phase report | |

## Open questions (do not block the build; defaults in brackets)

1. API consumers [the branch office's mobile app and office kiosks, each with its own key; no open access].
2. Fayda/eSignet [registration as a relying party with the National ID Program is needed; the code is ready
   and stays off until the client id and keys are saved].
3. Social networks to post to [the Facebook page and the Telegram channel; X/Twitter needs a paid API and is
   left out].
4. Donations [only if Ethiopian political-finance rules and the party allow it; pledges with bank-transfer
   instructions first, online payment only with a provider account].
5. Octane [not possible on the shared host; a production VPS could run it; the code is kept ready].
