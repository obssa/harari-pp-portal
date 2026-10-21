# Phase 6 plan — Membership and volunteers

Written 2026-10-21 from `docs/PLAN.md` §4 (Get Involved), §5 (people modules), §7 (people dashboard and
analytics), §8 (Membership Officer, Member roles), §10 (`membership_applications`, `members`) and §11, after
Phase 5 (`docs/phases/05-hardening-launch.md`, tag `v0.5.0`). The launch inputs from the branch office
(mail, Cloudflare, legal approval…) are gathered at the end, as the owner decided on 2026-10-21. Each step
ends with tests green, CI green, a staging deploy and a hand-off, as before.

**Gate (PLAN.md §13, Phase 6):** first real applications processed. On staging this is shown with sample
applications (clearly labelled); real ones need the branch office and an SMS gateway.

## Steps

| Step | Content | Status |
|---|---|---|
| 6.1 | Membership application: a multi-step public form (personal details, ID, woreda/kebele and basic organisation, contact, education and occupation, consent), the phone verified by a one-time code by SMS, a reference number and a status page; personal data encrypted, ID and phone also as keyed hashes | done |
| 6.2 | Review queue for Membership Officers: filters, masked applicant profile, duplicate detection (ID, phone, name and birth date), approve (creates the member with a member number) or reject with a reason, assign to a woreda office, notes and history, SMS/email to the applicant, notices to officers, export | done |
| 6.3 | Members directory: member number, office, basic organisation, status, joined and renewed dates, renewal due and reminders, notes and timeline, masked lists, export with `members.export`; privacy search, export and erasure (ADR-0041) extended to applications, members and volunteers | done |
| 6.4 | Import of existing membership rolls from Excel or CSV: column mapping, dry run with errors and duplicates, apply, roll back | done |
| 6.5 | Volunteers: public sign-up with skills, availability and languages; admin list, assignments to events, hours log | done |
| 6.6 | Member portal: sign-in with phone and a one-time code (no passwords), own profile with change requests, membership card (printable, with a QR code that verifies the member number), own event registrations, members-only documents | done |
| 6.7 | People dashboard and reports: applications funnel, member growth by office, sex and age band (aggregated, no personal data), volunteers and hours; membership report in the report builder | |
| 6.8 | Manual chapter, sample applications on staging, phase report | |

## Open questions (do not block the build; defaults in brackets)

1. ID documents accepted [Fayda national ID, kebele ID, passport, none yet; the number is optional].
2. Who may apply [Ethiopian residents of the Harari Region aged 18 or over].
3. Member number format [`HPP-` + office code + sequence, e.g. `HPP-W01-000123`; editable later].
   Decided in step 6.2 (ADR-0045): `HPP-` + six digits in order, without the office code, so the number
   stays the same when a member moves to another woreda; the office is shown next to it.
4. Renewal period and fees [renewal every 12 months, no fee in the portal; fees only if legally permitted,
   Phase 8].
5. SMS gateway for codes and messages [the log driver on staging; Ethio Telecom or Africa's Talking once
   the branch office has an account].
6. Retention of rejected and withdrawn applications [deleted 180 days after the decision].
7. Membership card design [a simple card with the party name, member name, number, office and QR code].
8. List of basic organisations per kebele [entered as offices of type "basic organisation"].
