# Translations awaiting native-speaker review

Strings the agent translated into Amharic (am) or Afaan Oromo (om) that a native speaker must confirm
before launch. Remove a row once it is reviewed. Harari (har) values are always left empty for the
Harari translators.

| Key | Locale | Shipped value | Doubt |
|---|---|---|---|
| `site.name` | am | የሐረሪ ብልጽግና ፓርቲ | Official branch name must come from the party (PLAN.md §14) |
| `site.office` | am | የሐረሪ ክልል ቅርንጫፍ ጽሕፈት ቤት | Official office name; "ጽሕፈት ቤት" vs "ጽ/ቤት" |
| `site.name` | om | Paartii Badhaadhinaa Hararii | Official branch name in Afaan Oromo |
| `site.office` | om | Waajjira Damee Naannoo Hararii | Official office name |
| `holding.*` | am, om | see `lang/am.json`, `lang/om.json` | Temporary holding page; register and wording |
| `access.role.*` | am, om | role names | Official titles used inside the party (e.g. ዋና አስተዳዳሪ, Bulchaa Olaanaa) |
| `access.module.*`, `access.action.*` | am, om | permission matrix labels | Technical terms (ፈቃድ, ሚና, ኦዲት, 2FA) |
| `admin.*` | am, om | admin screens and the sign-in email | Register of the sign-in email; staff-facing wording |
| `nav.language` | am, om | ቋንቋ / Afaan | Label of the language switcher |
| `lang/am/*.php`, `lang/om/*.php` | am, om | validation, sign-in, password and pagination messages | Taken from Laravel-Lang (MIT); some read as machine translation (e.g. Oromo password reset text mixes in English). Review all. |
| Harari name in the switcher | har | ሐረሪ (`config/portal.php`) | The brief suggested "መሐዲያ"; confirm the name Harari speakers use for their language (Gey Sinan, ጌይ ሲናን?) |
| `calendar.ethiopian.date` | am | `:month :day ቀን :year ዓ.ም.` | Confirm the usual written order and whether "ቀን" belongs in short dates |
| `calendar.gregorian.month.*` | am | ጃንዩወሪ, ፌብሩወሪ … | Several spellings are in use (ጃንዋሪ, ጃኑዋሪ); pick the party's house style |
| `calendar.ethiopian.month.*`, `calendar.gregorian.month.*` | om | Fulbaana … Qaammee; Amajjii … Muddee | Afaan Oromo uses the same month names for both calendars; only the era mark tells them apart |
| `calendar.ethiopian.date` | om | `:month :day, :year A.L.I.` | Confirm the era abbreviation for the Ethiopian calendar (A.L.I., "Akka Lakkoobsa Itoophiyaa") |
| `admin.settings.*` | am, om | settings screens | Staff-facing wording of the settings and integrations screens |
| `admin.media.*`, `admin.media_folders.*` | am, om | media library screens | Terms: alt text (አማራጭ ጽሑፍ / Barreeffama bakka bu'aa), folder (ማህደር / Fooldara), media library (የሚዲያ ማከማቻ / Kuusaa miidiyaa), focal point (የትኩረት ነጥብ / Qabxii xiyyeeffannaa), upload (ጫን / Fe'i) |
| `media.rejected.*` | am, om | upload refusal messages | Shown to staff when a file is refused; check they read as plain instructions |
| `nav.*`, `footer.*` | am, om | site navigation and footer | Short UI labels; "hotline" (ቀጥታ መስመር / Sarara kallattii) and the copyright line |
| `errors.*` | am, om | error and maintenance pages | Tone of the apology on 500/503; "session expired" wording on 419 |
| `site.description` | am, om | page description for search engines | Must match the branch's official self-description once supplied |
| `docs/manual/01-getting-started.am.md` | am | staff manual, chapter 1 | Whole chapter; UI labels quoted must match the Amharic admin |
| `admin.categories.*`, `admin.tags.*`, `admin.translations.*` | am, om | category/tag screens and language tabs | "slug" kept as a loanword (ስሉግ); category (ምድብ / Ramaddii), tag (መለያ / Mallattoo) |
| `content.post_type.*`, `content.status.*` | am, om | post types and workflow statuses | Party usage for press release (ጋዜጣዊ መግለጫ / Ibsa miidiyaa) and statement (መግለጫ / Ibsa); status words |
| `admin.posts.*`, `content.block.*`, `admin.revisions.*` | am, om | post editor, blocks, versions | Editor-facing wording; "kicker" (ንዑስ ርዕስ / Mata-duree xiqqaa); Oromo uses Finfinnee for Addis Ababa |
| `content.transition.*`, `content.notification.*`, `admin.comments.*`, `admin.webhooks.*` | am, om | workflow buttons, staff notifications and emails, webhooks | Button wording (ለግምገማ አቅርብ, ለማስተካከያ መልስ); "webhook" kept as a loanword |
| `news.*`, `nav.news`, `pagination.*` | am, om | public news pages and feeds | Reading-time plural forms; "also available in" phrasing; share/related labels |
