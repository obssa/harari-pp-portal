# Translations and the social hub

For translators, communications staff and media managers of the Harari Regional Branch Office. Amharic
version: [04-translations-social.am.md](04-translations-social.am.md).

## 1. The words of the site

Every button, heading and message of the site exists in English, Amharic, Afaan Oromo and Harari.
Translators correct them in **Administration → Languages → Interface translations**.

Who: everyone with translation rights sees the list; a translator changes only the languages given to
them (for example Harari only).

1. The page opens on your language. **Change language** switches it.
2. Use **Status** to find what needs work:
   - **Missing**: not translated yet (the site shows Amharic or English instead).
   - **English changed**: the English text changed after this translation was made.
   - **Machine translated**: written by a machine and not yet checked.
   - **Glossary term not used**: an approved term (see 3) is missing.
3. Choose **Translate** on a line. You see the English text, where it appears, the placeholders and the
   recent changes. Write the text and save.
4. Placeholders such as `:date` or `:name` must stay exactly as in English: the site puts the real date or
   name there. A text without them is refused.
5. Leave the box empty to remove your version: the shipped text, or the fallback language, shows again.

Every change is kept with who made it and the English it was made from.

## 2. Working in Excel

1. On the translations page choose **Export**, then **Excel**. The file has the columns `key`, `en`, your
   language and `context`.
2. Fill in or correct the column of your language. Do not change the `key` column or the placeholders.
3. Choose **Import** and pick the file. Nothing is saved yet: you see what is new, what changed, and what
   is refused (unknown keys, changed placeholders, a file not saved as UTF-8).
4. Choose **Save these changes**.

Empty cells leave a text as it is, unless you tick **Empty cells remove the translation**.
**Administration → Languages → Translation imports** lists every import; **Roll back** undoes one
completely. CSV files must be saved as "CSV UTF-8" so Ethiopic letters survive; Excel files always work.

## 3. The glossary

**Administration → Languages → Glossary** holds the approved words for the party's names, bodies and ideas
in every language, with notes. The translation editor shows the terms in each English text and whether
your translation uses them. The glossary warns; it never stops you saving.

## 4. Machine translation

Machine translation is off unless a Super Admin switched it on under **Administration → Integrations**
(Google or an AI model). It is only offered for Amharic and Afaan Oromo, never for Harari.

- In the translations grid, **Machine translate** fills one line; **Translate missing strings by machine**
  fills every missing line of the language in the background.
- In a post, the Amharic and Afaan Oromo tabs offer **Translate from another language by machine**.
- Everything a machine wrote is marked until a person checks it: in the grid choose **Mark as checked**
  (or correct the text); in a post tick **Checked by a person** and save.

Read every machine translation before it goes public: names, numbers and the party's terms need a
person's eye.

## 5. The social media hub

Who: media managers (**Social media** in the admin).

- **Accounts**: the Telegram channels, YouTube channels and Facebook pages of the branch, its offices and
  leagues. Telegram and YouTube are read on their own; a Facebook page needs its page access token
  (**Page access token** on the account). TikTok, Instagram and X posts are added with **Add a post by
  its address**.
- **Read now** reads an account at once; otherwise they are read every hour. Errors show next to the
  account.
- **Social posts**: every post read, newest first. **Hide** keeps one off the site; **Pin to the top**
  keeps one first.

The public hub is at `/{language}/social`; the homepage section "On social media" and the office and
league pages show the latest posts. Pictures are copies kept on the portal, and every post links to the
original.
