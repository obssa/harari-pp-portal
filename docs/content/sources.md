# Content sources

The site owner asked (2026-10-19) for real content taken from the branch's own public channels first
and from the Harari regional government communication office second. This file records which channels
are official, how they can be read, and the rules for importing from them. The import itself arrives
with the content module (Phase 2).

## Official channels

**Prosperity Party, Harari Region Branch (primary source)**

| Platform | Address | Status |
|---|---|---|
| Telegram | https://t.me/hrprosperityparty | Probably official; readable without login |
| Facebook | page id 100064354104698 ("Harari Region Prosperity Party - ብልፅግና") | Probably official; login wall |
| X | https://x.com/HarariRegPP | Likely official; not readable without login |
| Website, YouTube, TikTok | none confirmed | — |

None of these is linked from a party or government page, so the branch must confirm they are its own.

**Harari Government Communication Affairs Office (secondary source)**

Listed in the federal Government Communication Service directory
(https://www.gcs.gov.et/en/2024/04/12/harari-government-communication-affairs-office/). Official
sources call it an Office (ጽ/ቤት), not a Bureau.

| Platform | Address | Status |
|---|---|---|
| Telegram | https://t.me/HarariGovernmentCommunication | Official; readable without login |
| Facebook | profile id 100064845514191 | Official; login wall |
| YouTube | https://www.youtube.com/@hararicommunication82 | Official; readable; no uploads since April 2026 |
| X | https://x.com/HarariGovComm | Official; not readable without login |
| Website | hararicommunication.gov.et | Official but offline on 2026-10-19 |

Not the Harari branch, never import from: the national party channel (t.me/Prosperity2022), other
regional branches (Addis Ababa, Central Ethiopia, Amhara, Oromia, Dire Dawa), and the former Harari
National League / Harari People's Democratic Party.

## How content is read

- **Telegram** is the only live source readable without an account (the public preview at
  `t.me/s/<channel>`). Posts usually come as a text message followed by a photo album.
- **Facebook** shows nothing without logging in. The portal never scrapes behind a login. The Social
  Media Hub (Phase 5) reads Facebook through the Meta Graph API with a Page access token, which a page
  admin of each page must provide (Admin → Settings → Integrations).
- **YouTube** is read through the YouTube Data API (key in Integrations) from Phase 5.

## Import rules (Phase 2)

1. Every imported item keeps its source: channel, post link and original date. Public pages show
   "Source: …" with the link.
2. Imported items arrive as drafts; staff review, correct and publish them. Nothing is published
   automatically.
3. Text is taken as written (mostly Amharic) and translated by staff following
   `docs/translations-review.md`; machine translation is only a draft.
4. Photos are downloaded once, cleaned like any upload (ADR-0009) and credited to the source.
   Telegram image links expire, so the post link is the citation.
5. The office republishes branch stories: import the branch version and skip duplicates.
6. Names and titles of officials are copied exactly from the source post, never guessed. Leadership
   pages use only names confirmed by the branch.

## Open questions for the branch (add to PLAN.md §14 inputs)

1. Confirm the branch's official accounts above, and provide a Facebook Page access token (or a page
   admin who can create one) and the TikTok handle.
2. Permission from the Government Communication Affairs Office to republish its text and photos.
3. The branch's official name in Afaan Oromo and Harari; contact address, phone, email and office hours
   (only unverified snippets were found).
4. The current leadership list with titles after the October 2026 government formation.
