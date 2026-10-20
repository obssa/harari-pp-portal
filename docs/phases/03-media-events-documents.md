# Phase 3 plan — Media, events, documents, projects

Written 2026-10-20 from `docs/PLAN.md` §4, §7, §10, §11 and §13, after the Phase 2 gate
(`docs/phases/02-content-core.md`). Each step ends with tests green, CI green, a staging deploy and a
hand-off, as before.

**Gate (PLAN.md §13):** all public pages from section 4 exist with real seed content.

## Steps

| Step | Content | Status |
|---|---|---|
| 3.1 | Photo galleries: albums of library photos with captions per language, cover, office/league/event links; public album list and album page with a lightweight lightbox (works without JavaScript), responsive WebP/AVIF, optional download of the original | done 2026-10-20 (with a homepage photo strip) |
| 3.2 | Videos and live: YouTube (privacy-enhanced), Facebook and Telegram videos with title and description per language, categories and playlists; video list and page; Live page showing the current or next live stream; homepage "video of the week" section | done 2026-10-20 |
| 3.3 | Events: events per language with start/end (both calendars), venue with map pin or online link, agenda, speakers (leaders), offices and leagues; public calendar (month and list), event page, iCal per event and a calendar feed; homepage "upcoming events" section; events in search and sitemaps | done 2026-10-20 (registration columns ready for 3.4) |
| 3.4 | Event registration: form with capacity and waitlist (automatic promotion), confirmation by email and SMS through an `SmsGateway` interface (log driver until a provider is chosen), QR code and check-in screen for staff, reminders the day before, honeypot and Turnstile, CSV export; contact details encrypted | done 2026-10-20 (custom questions with the forms builder, 3.7) |
| 3.5 | Documents library: programmes, bylaws, reports, policies, speeches, forms, vacancies and tenders (with deadlines); a file per language, year, office/league, versions with history, download counter, in-browser PDF viewer, related documents; public library with filters and a notices page for vacancies and tenders; documents in search | done 2026-10-20 (members-only access waits for Phase 6) |
| 3.6 | Projects and statistics: projects per language with sector, woreda/office, status, budget (if public), dates, progress, map pin and photos; statistics blocks (value, unit, label, source, as-of date) on projects and pages; projects list with filters and an achievements page by sector with simple charts | done 2026-10-20 (all-projects map left for later) |
| 3.7 | Forms builder and inbox: forms (contact, feedback and petition, press enquiries, vacancy applications) with fields and labels per language, routing to an office or address, honeypot and Turnstile, auto-reply per language; contact page with office cards; ticket numbers with a public status lookup; Filament inbox with status, assignee, internal notes and a response-time clock | done 2026-10-20 (canned replies later) |
| 3.8 | Press kit and "Get involved": press kit page (logos, colours, boilerplate, leadership portraits, media enquiry form); Get involved page linking events, feedback and volunteering, with membership, volunteering and newsletter sign-up marked as coming in Phases 6–7 | done 2026-10-20 |
| 3.9 | Phase 3 gate: sample content for every new page (clearly labelled) alongside the imported real stories, a check that every section-4 page renders in the four languages, Lighthouse on an event and a document page, manual chapter "Events, documents and forms", report, tag `v0.3.0` | done 2026-10-20 |

## Main decisions to record

- ADR-0023 photo galleries (lightbox approach).
- ADR-0024 videos and live (no third-party scripts; privacy-enhanced YouTube; thumbnails copied).
- ADR-0025 events and registrations (capacity and waitlist rules, QR check-in, reminders, SMS gateway
  interface, PII encryption and retention).
- ADR-0026 documents library (files per language, versions, viewer, counters, notices).
- ADR-0027 projects and statistics.
- ADR-0028 forms builder, tickets and Turnstile.

## New tables (draft)

`galleries`, `gallery_translations`, `gallery_items`; `videos`, `video_translations`; `events`,
`event_translations`, `event_speakers`, `event_registrations`; `documents`, `document_translations`,
`document_versions`; `projects`, `project_translations`, `statistics`, `statistic_translations`;
`forms`, `form_translations`, `form_submissions`, `submission_notes`. All with `site_id`, activity log
and policies; personal data in registrations and submissions encrypted at rest.

## Public routes (draft)

`/{lang}/gallery`, `/{lang}/gallery/{slug}`, `/{lang}/videos`, `/{lang}/videos/{slug}`, `/{lang}/live`,
`/{lang}/events`, `/{lang}/events/{slug}` (+ `.ics`), `/{lang}/events/feed.ics`, `/{lang}/documents`,
`/{lang}/documents/{slug}`, `/{lang}/notices`, `/{lang}/projects`, `/{lang}/projects/{slug}`,
`/{lang}/achievements`, `/{lang}/contact`, `/{lang}/forms/{slug}`, `/{lang}/tickets/{number}`,
`/{lang}/press`, `/{lang}/get-involved`.

## Open questions (do not block the build; defaults in brackets)

1. Cloudflare Turnstile keys for the forms (free; Integrations screen) [honeypot and rate limits until
   the keys are entered].
2. SMS provider for event confirmations and reminders (Ethio Telecom, Africa's Talking, Twilio) [log
   driver: messages written to the log, email still sent].
3. Who answers each form and in what time (routing and the response-time target) [branch office inbox,
   3 working days].
4. Which documents may be public (programme, bylaws, reports) and which are members-only (Phase 6)
   [public only].
5. Real events, documents and projects for the seed content [clearly labelled samples until staff add
   real ones].

## Report (2026-10-20)

Staging: https://hararipp.orcamind.dev. Tag: `v0.3.0`.

### Gate (PLAN.md §13, Phase 3)

| Criterion | Status | Evidence |
|---|---|---|
| All public pages of section 4 that Phase 3 adds exist with seed content | met | Labelled sample events, documents, projects, statistics and a feedback form from `php artisan portal:sample-content` on staging, next to the stories imported from the party's channels; `Phase3GateTest` loads every Phase 3 page (events list, month and event page, registration, documents, notices, document page, projects, project page, achievements, gallery, videos, live, contact, form page, tickets, press, get involved) in en, am, om and har |
| Lighthouse on an event and a document page | met | CI Lighthouse on live staging (median of 3 mobile runs, all assertions ≥ 0.9): the sample event `/en/events/sample-community-consultation-meeting` and the sample document `/en/documents/sample-party-programme` (both lists were audited in the run before) |
| Manual chapter "Events, documents and forms" | met | `docs/manual/03-events-documents-forms.md` and `.am.md` |

### What was built

| Step | Content | Commit |
|---|---|---|
| 3.1 | Photo albums with captions per language, a lightbox without JavaScript, downloads when allowed | b190394 |
| 3.2 | Videos (privacy-enhanced YouTube, Facebook, Telegram), playlists, live page, video of the week | fd4b05f |
| 3.3 | Events with agenda, speakers and map; list, past and month views; .ics and a calendar feed | ee0c2ab |
| 3.4 | Registration with places and a waiting list that moves up, QR tickets, check-in, reminders, SMS gateway (log driver) | 92b01ee |
| 3.5 | Documents library with a file per language, versions, PDF viewer, counted downloads; vacancies and tenders | 0298baa |
| 3.6 | Projects with sector, progress and budget when public; statistics with sources; achievements by sector | 6e304e7 |
| 3.7 | Forms builder, contact page, random ticket references and status page, inbox per office | 55b1583 |
| 3.8 | Press kit and "Get involved" | 2813563 |
| 3.9 | Sample content, four-language page check, manual chapter, this report | 3e001ad and this commit, tagged `v0.3.0` |

Decisions are recorded as ADR-0023 to ADR-0029 in `docs/DECISIONS.md`. Left for later on purpose: a map
of all projects together (needs a map script), PDF.js for phones that do not show PDFs in a page,
canned replies and satisfaction ratings in the inbox, public holidays in answer-by dates, retention
periods for tickets (PLAN.md §14), members-only documents (Phase 6), and a real SMS provider.

### Quality at the end of the phase

| Measure | Value |
|---|---|
| Tests | 695 Pest tests in CI, all passing |
| Line coverage, whole app | 89.3% (CI minimum 80%) |
| Static analysis | Larastan level 6, no errors |
| Translations | English, Amharic and Afaan Oromo complete (1429 keys); Harari keys present and empty |
| Lighthouse | Every audited page meets performance, accessibility and best practices ≥ 0.9 on mobile |

### Demo script (staging)

1. `/en/events`: the sample consultation meeting; open it, **Add to calendar**, then **Register** with a
   phone number; the ticket page shows the QR code. In the admin, **Content → Event check-in** with the
   ticket's code.
2. `/en/documents`: filter by type; open the sample programme and read the PDF in the page; `/en/notices`
   shows the sample vacancy with its deadline.
3. `/en/projects` and `/en/achievements`: the sample water project, its progress and numbers.
4. `/en/contact` and the sample feedback form: send a message, keep the reference, look it up at
   `/en/tickets`. In the admin, **Inbox and forms → Inbox**: assign, add a note, reply.
5. `/en/press` after filling **Content → Press kit**; `/en/get-involved`.
6. Switch to አማርኛ, Afaan Oromoo and ሐረሪ on each page.
7. Remove the samples before launch with `php artisan portal:sample-content --remove`.
