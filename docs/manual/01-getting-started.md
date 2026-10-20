# Getting started with the portal admin

For staff of the Harari Regional Branch Office who manage the portal. The admin works the same on a
computer and a phone. Amharic version: [01-getting-started.am.md](01-getting-started.am.md).

Admin address (test site): **https://hararipp.orcamind.dev/admin**

## 1. Signing in for the first time

1. Your administrator creates your account and gives you your email address and a first password, or
   sends you a "sign-in link" by email.
2. Open the admin address, enter your email and password, and choose **Sign in**.
3. If your role is Super Admin, Admin or Publisher and a Super Admin has made two-factor login
   compulsory (**Administration → Security**), the admin asks you to set up **two-factor login**:
   - Install an authenticator app on your phone (Google Authenticator, Microsoft Authenticator or a
     similar app).
   - Scan the QR code shown on screen with the app, then type the 6-digit code the app shows.
   - **Write down the recovery codes** and keep them somewhere safe and private. Each code works once
     if you lose your phone.
4. Once it is set up, every later sign-in asks for your password and the current 6-digit code from
   the app.

Anyone may turn two-factor login on (or off, while it is not compulsory) from their profile; it is
strongly recommended for everyone.

## 2. Your profile

Click your name or picture at the top right, then **Profile**.

- Change your **name**, **email address** and **password** (at least 12 characters with upper and
  lower case letters and a number).
- The **language of the admin** (English, Amharic or Afaan Oromo) is set on your account by an Admin
  (**Users → your account → Preferred language**).
- Turn two-factor login on or off, or create new recovery codes.

If you lose your phone, ask an Admin to **Reset two-factor login** on your account; you then set it up
again at your next sign-in.

When your account is used from a network and a browser it has not seen for 90 days, you get **New
sign-in to your account** in the portal and by email. If it was you, nothing needs doing; if not, change
your password at once and tell an Admin.

### Notifications

The bell at the top right and **Notifications** in the menu show what needs you: a post to review or
publish, a message for your office or a ticket given to you, event registrations, new drafts from the
public channels, a finished machine translation, an account or channel that can no longer be read. Use
**Unread** to see what is new, **Open** to go to the item, and **Mark all as read** when you are done.

**How I am notified** chooses, for each kind of notice, whether it reaches you in the portal, by email,
both or neither. Registrations and imports come in the portal only unless you add email; unread notices
about one event are combined into one with the latest numbers.

## 3. The four languages of the site

The public site is published in English (`/en`), Amharic (`/am`), Afaan Oromo (`/om`) and Harari
(`/har`). Visitors switch language at the top of every page and the site remembers their choice.
Until a text is translated into Harari, the Amharic text is shown in its place; Afaan Oromo and
Amharic texts fall back to English. Every date is shown in the Ethiopian calendar with the Gregorian
date next to it.

## 4. Users and roles (Super Admin and Admin)

**Administration → Users**

- **New user**: enter the name and email, choose the roles, and either set a temporary password or
  let the portal email a sign-in link.
- **Roles** decide what a person may do (Editor, Author, Translator, Media Manager, Publisher and
  others). Some roles can be limited to one office, league or language with **scopes**.
- **Suspend** signs the person out everywhere and blocks sign-in until **Reactivate**.
- **Send sign-in link** lets someone choose a new password.
- Changing someone's roles signs them out, so the new rights apply at once.
- Super Admin accounts can only be changed by another Super Admin; nobody can delete their own account.

**Administration → Roles** shows what each role may do. Only a Super Admin changes it.

## 5. Media library (photos, documents, video)

**Content → Media library**

1. Choose **Upload**, drag up to 20 files into the box (or tap to choose them), pick a folder if you
   like, and choose **Upload** again.
   - Photos (JPEG, PNG, WebP, GIF) up to 20 MB, SVG graphics, documents (PDF, Word, Excel,
     PowerPoint, OpenDocument, text, CSV) up to 25 MB, video and audio up to 50 MB.
   - Files are checked when they arrive. A file that is not what its name says, contains code or
     macros, or is too large is refused, and the message tells you why.
   - Location and camera details are removed from photos automatically.
2. Open a file (**Edit**) to add:
   - **Alt text** in each language: a short description of what the photo shows, for people who
     cannot see it. English, Amharic and Afaan Oromo are needed before a photo can be a featured image.
   - **Credit** (photographer or source) and **tags**.
   - **Focal point**: click the part of the photo that must always stay visible. The portal crops the
     photo for cards, banners and portraits around this point.
3. Smaller copies of each photo are made automatically within about a minute of uploading.
4. A file that is used somewhere cannot be deleted; the **Used in** list shows where.

Filters on the list help with the work left: **Missing alt text**, **Not used anywhere** and
**Uploaded by me**. Folders are managed under **Content → Media folders**.

## 6. Site settings

**Administration → Site settings** (Admins can read; only a Super Admin changes them): default
language, which calendar comes first, contact email, phone and hotline, address and office hours in
each language, and the links to the branch's social media pages. The public footer shows these.

**Administration → Integrations and API keys** (Super Admin only): keys for SMS, translation and
social media services. A saved key is never shown again; leave the field empty to keep it.

## 7. Audit log

**Administration → Audit log** lists who changed what and when, with the before and after values,
including sign-ins, failed sign-ins and refused requests. Entries are kept for two years and cannot be
edited.

### System health and backups (Admins and Super Admins)

**Administration → System health** shows whether the portal is working: the database, background jobs,
the scheduler, outgoing mail, storage and backups, checked every five minutes. **Run the checks now**
checks again; **Send a test email** sends you a short message to prove mail leaves the portal. If a check
starts failing you get a notice.

The page also lists the backups (made every night at 02:00 and kept for 30 days) and the last restore
drill. A Super Admin can choose **Back up now** or **Download** a backup. Backups contain personal data:
download one only when it is needed and delete the copy afterwards.

## 8. Good practice

- Never share your password, your 6-digit codes or your recovery codes, and never sign in on a shared
  computer without signing out afterwards.
- Use alt text that describes the photo, not the file name.
- Only publish photos and texts the branch has the right to use; credit the source.
- Report anything unexpected in the audit log to the Super Admin.

Next chapter: [Publishing a post](02-publishing-a-post.md).
