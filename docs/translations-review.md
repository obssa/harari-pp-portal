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
| `admin.redirects.*`, `access.module.redirects` | am, om | redirects manager | "redirect" (አቅጣጫ ማዞሪያ / Qajeelcha); old/new address wording |
| `admin.pages.*`, `admin.menus.*`, `pages.*`, `footer.links` | am, om | page and menu screens, footer quick links | Template names (የጽሑፍ ገጽ, መግቢያ ገጽ / Fuula seensaa); menu (ማውጫ / Baafata) |
| `admin.settings.security.*` | am, om | security settings page | "two-step sign-in" (ባለሁለት ደረጃ መግቢያ / Seensa sadarkaa lamaa); "authenticator app" (የማረጋገጫ መተግበሪያ / appii mirkaneessaa) |
| `organisation.*`, `admin.offices.*`, `admin.leaders.*`, `admin.leagues.*` | am, om | office kinds, leadership bodies, directory screens | Official names of bodies (ሥራ አስፈጻሚ ኮሚቴ / Koree Hojii Raawwachiiftuu; ሴክሬታሪያት / Sekreteriyeetii); "sector office" (የዘርፍ ጽሕፈት ቤት / Waajjira seektaraa); Oromo keeps "Latitude"/"Longitude"; the hours example uses local (Ethiopian) time in am and om |
| `directory.*`, `leaders.*`, `offices.*`, `leagues.*`, `structure.*` | am, om | public directory pages | Page titles and the short labels on profiles |
| `homepage.*` | am, om | homepage sections and builder | Core values: መደመር / Ida'amuu, ኅብረ ብሔራዊ አንድነት / Tokkummaa sab-daneessummaa, ብሔራዊ ሉዓላዊነት / Olaantummaa biyyaa, አካታችነት / Hammattummaa; confirm against party documents. "Breaking news" (ሰበር ዜና / Oduu ariifachiisaa) |
| `search.*`, `admin.search_terms.*` | am, om | search page, header search, search insights | "search" (ፍለጋ / Barbaacha); quotation marks around the query in am/om |
| `dashboard.*` | am, om | admin dashboard, widgets and announcements | "widget" kept as a loanword in om (Widget) and as መግብር in am; fiscal-year wording (በጀት ዓመት / Bara baajataa) |
| `import.*`, `news.source` | am, om | content sources screen, imported-post notes | "draft" (ረቂቅ / wixinee), "source" (ምንጭ / Madda); "Ethiopic script" (ግዕዝ ፊደል / qubee Saabaa) |
| `gallery.*`, `homepage.section.gallery` | am, om | photo albums | "photo album" (የፎቶ ስብስብ / kuusaa suuraa); count forms for photos |
| `video.*`, `homepage.section.video`, `admin.categories.type.*` | am, om | videos, live page | "live broadcast" (ቀጥታ ስርጭት / Tamsaasa kallattii); "playlist" (የቪዲዮ ዝርዝር / Tarree viidiyoo) |
| `events.*`, `calendar.weekday.short.*`, `homepage.section.events` | am, om | events list, month view, event page, admin | "event" (ሁነት / Sagantaa; om could also use Taatee); short weekday names in the month grid (ማክሰ for Tuesday is shortened); "agenda" (መርሐ ግብር / Sagantaa hojii) |
| `events.registration.*`, `events.ticket.*`, `events.check_in.*`, `forms.*` | am, om | registration form, ticket, messages, check-in | "register" (ይመዝገቡ / Galmaa'aa); "waiting list" (መጠባበቂያ ዝርዝር / Tarree eegaa); "check in" (አስገባ / Galchi, :name seeneera); SMS texts are kept short |
| `documents.*`, `admin.menus.type.documents`, `admin.menus.type.notices` | am, om | documents library, notices, admin | Document types (መተዳደሪያ ደንብ / Dambii; ጨረታ / Caalbaasii; ክፍት የሥራ ቦታ / Bakka hojii banaa); "version" (ስሪት / Versiyoona, a loanword in om) |
| `projects.*`, `content.block.statistics`, `homepage.section.projects`, `admin.categories.type.sector` | am, om | projects, statistics, achievements | "project" (ፕሮጀክት / Pirojektii, loanwords); "achievements" (ስኬቶች / Milkaa'ina); status names (በሂደት ላይ / Hojii irra jira; የተቋረጠ / Dhaabbate); "birr" order in om (Qarshii :amount) |
| `forms.*`, `admin.menus.type.contact` | am, om | forms, contact page, tickets, inbox | "reference" (የመከታተያ ቁጥር / Lakkoofsa hordoffii); "petition" (አቤቱታ / Iyyannoo) and "job application" (የሥራ ማመልከቻ / Iyyannoo hojii) share a word in om; "hotline" uses the footer's wording |
| `press.*`, `involved.*`, `admin.menus.type.press`, `admin.menus.type.get_involved` | am, om | press kit, Get involved | "press kit" (የሚዲያ መረጃ ጥቅል / Kuusaa miidiyaa); colour names ("Harar ochre" የሐረር ቡናማ / Daalacha Harar); "volunteer" (በጎ ፈቃደኛ / Tola ooltuu) |
| `social.*` | am, om | social accounts and posts in the admin | "post" (ልጥፍ / Maxxansa); "account" (መለያ / Akkaawuntii, loanword); network names kept in Latin in om |
| `social.hub.*`, `homepage.section.social`, `admin.menus.type.social` | am, om | social media hub | "social media" (ማኅበራዊ ሚዲያ / Miidiyaa hawaasaa); "follow" (ይከተሉ / Hordofaa) |
| `translations.*` | am, om | translations grid | "interface translations" (የገጽ ጽሑፎች ትርጉም / Hiikkaa barreeffamoota fuulaa); "placeholder" (ምልክት / Mallattoo) |
| `translations.export.*`, `translations.import.*` | am, om | export, import and rollback | "import" (ከፋይል አስገባ / Faayilii irraa galchi); "roll back" (መልስ / Deebisi) |
| `translations.glossary.*`, `translations.status.glossary` | am, om | glossary | "glossary" (የቃላት መፍቻ / Galmee jechootaa) |
| `translations.machine.*`, `dashboard.widget.translation_queue` | am, om | machine translation | "machine translation" (የማሽን ትርጉም / Hiikkaa maashinii); "AI model" (የሰው ሰራሽ አስተውሎት ሞዴል / Moodeela AI) |

## Legal pages (step 5.9, `app/Domain/Content/Support/LegalPagesAm.php`, `LegalPagesOm.php`)

Drafts for the branch office's legal reviewers; the English text is the reference.

| Term | Amharic | Afaan Oromo | Doubt |
|---|---|---|---|
| Personal Data Protection Proclamation No. 1321/2024 | የግል መረጃ ጥበቃ አዋጅ ቁጥር 1321/2024 | Labsii Eegumsa Odeeffannoo Dhuunfaa Lakk. 1321/2024 | use the official title as published in the Negarit Gazeta |
| privacy-enhanced mode (YouTube) | በግላዊነት በተጠናከረ ሁነታ | haala dhuunfaa cimsateen | technical; may stay in English in brackets |
| two-step sign-in | ባለሁለት ደረጃ መግቢያ | seensa sadarkaa lamaa | same wording as the admin |
| draft for legal review | ለሕግ ግምገማ የቀረበ ረቂቅ | wixinee gamaaggama seeraaf dhiyaate | |
| accessibility statement | የተደራሽነት መግለጫ | ibsa dhaqqabummaa | |

## Menu and content pack (redesign, ADR-0062 and ADR-0063)

| Keys or text | Languages | Context | Doubts |
|---|---|---|---|
| `nav.*` | am, om | main menu sections and hints | "Resources" (የመረጃ ማዕከል / Kuusaa odeeffannoo); "Programmes" (ፕሮግራሞች / Pirogiraamota, since Sagantaalee already means events); "sector offices" (Waajjiraalee kutaa hojii) |
| `organisation.leader_body.government`, `leaders.source` | am, om | leadership page | "councils" (ምክር ቤቶች / mana mareewwan) |
| `import.source.*.auto_publish` | am, om | content sources admin | "publish at once" (ወዲያውኑ አትም / battaluma maxxansi) |
| Pack pages About, History, Harar (`database/content/pack.json`) | om | written for the pack, not translated by a native speaker | "wing" of the party (Kutaa Dubartootaa / Kutaa Dargaggootaa); "speaker" (afyaa'ii); woreda names other than Soofii and Dirree Xiyyaaraa kept in their English form; EPRDF as ADWUI |
| Pack leader names | en | transliterations where no official English spelling exists | Getu Weyesa, Dinir Yusuf, Seifu Getachew (also written ሴይፉ), Ayele Tesfaye, Hassen Abdi, Zinet Yusuf, Tuna Ibrahim, Muhyedin Ahmed, Amina Abdukerim |

## Harar photos and wing profiles (ADR-0065)

| Keys or text | Languages | Context | Doubts |
|---|---|---|---|
| `harar.photo.*`, `harar.caption.*`, `homepage.heritage.fact_*` | am, om | photo descriptions and Harar facts | "basket plates" (የሰፌድ ሥራዎች / gabatee hodhaa) |
| `leagues.about`, `leagues.take_part_*`, `admin.leagues.*accent*` | am, om | league page and admin colour | colour names (የብልጽግና ሰማያዊ / Cuquliisa Badhaadhinaa; Maajantaa hodhaa) |
| Wing profiles in `database/content/pack.json` (updates) | om | written for the pack | "Resurgence Day" (የማንሰራራት ቀን) left out of the Afaan Oromo text; "corridor development" (misooma koriidaraa); "Green Legacy" (Ashaaraa Magariisaa) |


## The party's name and the head's name (ADR-0069)

| Keys or text | Languages | Context | Doubts |
|---|---|---|---|
| `site.name` and every string with the party's name | am, om | "Harari Region Prosperity Party" as the branch office asked | የሐረሪ ክልል ብልጽግና ፓርቲ (the office's own posters write የሀረሪ ክልል ብልፅግና ፓርቲ); Paartii Badhaadhinaa Naannoo Hararii |
| Head of the branch, `database/content/pack.json` | am, om | "H.E Getu Wayyesa Tola, Head of Harari Region Prosperity Party" | ክቡር አቶ ጌቱ ወዬሳ ቶላ, የሐረሪ ክልል ብልጽግና ፓርቲ ኃላፊ; Kabajamoo Obbo Geetuu Wayyeessaa Tolaa, Itti Gaafatamaa Paartii Badhaadhinaa Naannoo Hararii |

## Homepage carousel (ADR-0070)

| Keys or text | Languages | Context | Doubts |
|---|---|---|---|
| `homepage.carousel.*`, `admin.carousel.*` | am, om | the carousel and its admin | "carousel" (ተንሸራታች ማሳያ / agarsiisa fakkiiwwanii); "slide" (ስላይድ / fakkii); "come into focus" |
| Slides in `database/content/pack.json` | om | the banner's tagline and the head's message | "Medemer" as Ida'amuu; "fraternity" (እህትማማችነት) as obbolummaa; "all-round prosperity" as badhaadhina hundagaleessa |

