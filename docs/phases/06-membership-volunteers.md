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
| 6.7 | People dashboard and reports: applications funnel, member growth by office, sex and age band (aggregated, no personal data), volunteers and hours; membership report in the report builder | done |
| 6.8 | Manual chapter, sample applications on staging, phase report | done |

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

## Report (2026-10-21)

Staging: https://hararipp.orcamind.dev. Tag: `v0.6.0`.

### Gate (PLAN.md §13, Phase 6)

"First real applications processed" is shown on staging with sample applications, clearly labelled: one
went through the public form with its SMS code and was approved as member `HPP-000001` (who signed in to the
member portal and opened the card), one was rejected with a reason, two wait in the review queue. A sample roll
was imported through the queue and rolled back, and a sample volunteer signed up. Real applications need
two inputs from the branch office: an SMS gateway account (codes and messages go to the SMS log until then)
and the woreda, kebele and basic organisation offices entered in the admin.

### What was built

| Step | Content | Commit |
|---|---|---|
| 6.1 | Membership application in three steps with a code by SMS, reference and status page | 4f3f78a |
| — | Fix: the log SMS driver writes its own file at any log level | fb1a6f6 |
| 6.2 | Review queue: masked list, duplicates, approve with member number, reject, hand over, notes, CSV, notices | d608841 |
| 6.3 | Members directory, manual members, renewals and reminders, privacy requests for applications and members | 8707f7b |
| 6.4 | Import of existing rolls from Excel or CSV with column matching, check, queued apply and roll back | e6ef3cf |
| 6.5 | Volunteer sign-up, coordinators' list, tasks at events and hours | 689fc36 |
| 6.6 | Member portal: sign-in by SMS code, card with a QR check, correction requests, events, members-only documents | f50c2fd |
| 6.7 | People dashboard widgets and the membership report | efef361 |
| 6.8 | Manual chapter 5 in English and Amharic with screenshots, staging samples, this report | this commit |

Decisions are recorded as ADR-0044 to ADR-0050 in `docs/DECISIONS.md`. Open question 3 was decided
differently from its default: member numbers are `HPP-` and six digits without the office code, so a member
keeps the number when moving to another woreda (ADR-0045).

### Quality at the end of the phase

| Measure | Value |
|---|---|
| Tests | 845 Pest tests in the main suites and 6 browser tests, all passing |
| Line coverage | 89.7% overall; `app/Domain` 88.9%, `app/Services` 91.9% (CI minimum 80% each) |
| Static analysis | Larastan level 6, no errors |
| Accessibility | axe on the public pages including the application, status, volunteer and portal sign-in pages: no serious or critical issues |
| Translations | English, Amharic and Afaan Oromo complete; Harari keys present and empty |

### For administrators on staging

1. **Administration → Roles**: Membership Officers hold the application, member and volunteer permissions by
   default. Limit an officer to woredas with a role scope on the user (Office), as for the inbox.
2. Enter the woredas, kebeles and basic organisations in **Organisation → Offices** before announcing the
   application form; until then applicants can only write their address.
3. Members-only documents: set **Who may see it** on a document.
4. Staging sample data is labelled "(sample data)" or "(staging test)". Remove it before launch with a privacy
   request on each sample phone number (0900000101, 0900000303 and 0900000402 to 0900000404).

### Waiting for the branch office

- An SMS gateway account (Ethio Telecom or Africa's Talking) for codes, decisions, reminders and portal sign-in.
- Legal approval of keeping membership and volunteer data, and of the consent texts on the forms.
- The membership card design, if it should differ from the simple card (open question 7).
- The offices of every woreda, kebele and basic organisation.
