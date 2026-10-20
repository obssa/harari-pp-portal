# Accessibility audit (Phase 5, step 5.7)

Target: WCAG 2.2 level AA (PLAN.md §11). Checked 2026-10-20 on the code at step 5.7.

## Automated checks in CI

| Check | Where | Gate |
|---|---|---|
| axe-core in a real browser (Chromium through Playwright), with the built styles so colour contrast is measured | `tests/Browser/AccessibilityTest.php`, CI job `browser` | any serious or critical violation fails the build |
| Lighthouse accessibility score on mobile | `lighthouserc.json`, after every deploy, against staging | score below 0.9 fails |
| Keyboard: the first Tab reaches "Skip to content", Enter moves to the content | `tests/Browser/AccessibilityTest.php` | fails the build |
| Visible text goes through translations (no hard-coded English) | `scripts/check-hardcoded-strings.py` | fails the build |

Pages covered by axe: homepage (English and Amharic), news list, a story (English and Amharic), events,
an event, event registration, offices, an office, documents, search results, contact, social media hub,
press, structure. Result on 2026-10-20: **no serious or critical violations** on any of them.

## Built-in measures (from Phases 1–4)

- Landmarks (`header`, `nav`, `main`, `footer`), one `h1` per page, headings in order; skip link first in
  the tab order; visible focus outline on every control.
- `lang` on the page and on every element in another language (language switcher, Ethiopic names),
  `hreflang` alternates; Noto Sans Ethiopic with a taller line height for Amharic and Harari.
- Images: alt text required per language for featured images (admin validation); decorative images empty.
- Forms: every field labelled, errors next to the field and announced (`aria-describedby`,
  `aria-invalid`), in the visitor's language; no CAPTCHA puzzle (Turnstile is invisible).
- Maps and videos load only on request, behind a labelled button, so keyboard users are not trapped in
  frames.
- Reduced motion respected; text resizable to 200 % without loss; no information by colour alone (status
  badges have text).

## Manual checks still to do (people with the tools and languages)

| Check | Who | Status |
|---|---|---|
| NVDA (Windows) and TalkBack (Android) reading Amharic, Afaan Oromo and Harari pages | a tester who reads the language | open |
| Keyboard-only walk through: publish a post, answer a ticket, check in an event guest (admin) | editors during training (step 5.10) | open |
| Older PDF documents (scans) — tagged copies or text versions | communications staff | open, listed in the accessibility statement |
| Captions for videos | media managers | open, listed in the accessibility statement |

The public accessibility statement (`/{language}/accessibility`, step 5.9) lists these limitations and how
to report a problem.
