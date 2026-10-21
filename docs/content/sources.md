# Content sources

The site owner asked (2026-10-19) for real content taken from the branch's own public channels first
and from the Harari regional government communication office second. This file records which channels
are official, how they can be read, and the rules for importing from them. The import runs every half
hour since step 2.11 (ADR-0022); admins manage it under Administration → Content sources.

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
2. Imported items arrive as drafts; staff review, correct and publish them. Since ADR-0063 the
   branch's own channel is trusted to publish: its stories go live when imported, dated when posted.
   Stories from the government office stay drafts.
3. Text is taken as written (mostly Amharic) and translated by staff following
   `docs/translations-review.md`; machine translation is only a draft.
4. Photos are downloaded once, cleaned like any upload (ADR-0009) and credited to the source.
   Telegram image links expire, so the post link is the citation.
5. The office republishes branch stories: import the branch version and skip duplicates.
6. Names and titles of officials are copied exactly from the source post, never guessed. Since
   ADR-0063 the leadership pages list the people whose positions are published in public sources,
   each with its source; the branch corrects them in the admin.

## Open questions for the branch (add to PLAN.md §14 inputs)

1. Confirm the branch's official accounts above, and provide a Facebook Page access token (or a page
   admin who can create one) and the TikTok handle.
2. Permission from the Government Communication Affairs Office to republish its text and photos.
3. The branch's official name in Afaan Oromo and Harari; contact address, phone, email and office hours
   (only unverified snippets were found).
4. The current leadership list with titles after the October 2026 government formation.

## Content pack (ADR-0063)

`database/content/pack.json` was compiled on 2026-10-21 from the sources above and from ENA, Fana,
Birr Metrics, the election board (NEBE), UNESCO and the statistics service; every leader and page
carries its links. Points left for the branch to confirm:

1. The deputy head of the branch office: branch posts of March-April 2026 name Abdulhakim Umer; a
   government post of 9 October 2026 names Abdulhakim Abdumalik, who ENA reported on 2 October as the
   president's adviser. Neither is listed.
2. Whether Jafer Sufian (organisation sector), Mustefa Ilyas (youth wing) and Hailegiorgis Megersa
   (strategy and ICT) keep their party posts after their council and government appointments of
   2 October 2026. Not listed; the youth wing shows no head.
3. Photos for the other leaders; portraits of Arif Mohamed and Muhyedin Ahmed could only be inferred
   from group photos and were left out.
4. The office's address, phone, email and hours (none are published) and its official English name.
5. English spellings of names, which are transliterations where no official form was found.

