# OyubiaCYF — Oyubia Christian Youth Forum Attendance Management System

A yearly attendance & registration system for the **Oyubia Christian Youth Forum**
(Church of Christ, Akwa Ibom State). Built as a **PHP + MySQL** app with an
**offline-capable PWA** front end, designed to run on **cPanel shared hosting**.

## What it does

- Registers attendees under three categories:
  - **Came with a congregation** — group from a Church of Christ congregation
  - **Came alone (member)** — a member travelling on their own
  - **Visitor** — not a member; captures church attended, who invited them, how
    they heard, and their expectations
- Groups everyone by congregation (group + solo members appear on the same roster)
- Auto-assigns a **continuous reg number** per event: `OYCF-2026-0014` (the running
  number doubles as the attendance count — the highest number is the total registered)
- Titles members automatically: **Bro.** (male) / **Sis.** (female); visitors get none
- Tracks **multiple yearly editions**; congregations carry over year to year
- **Returning attendees**: a search box on the registration form finds people from
  past years and links them, so a person has one record with full attendance history
  (no duplicates). Linking refreshes their phone/email/etc. with the latest info, and
  blocks re-registering someone already on this year's list. *(This live search needs
  internet; while offline a new record is created and can be linked later.)*
- **Offline-first**: keeps registering when the venue internet drops; queues on the
  device and syncs automatically when back online
- **Prints** per-congregation rosters (with phone numbers) — via the browser's
  Print → Save as PDF — and exports them to CSV
- **Merge duplicates** (admin): combine two records that are the same person —
  moves registrations onto the kept record, drops true same-year duplicates,
  backfills missing details, and deletes the absorbed record. Cleans up any
  duplicates created during offline registration.
- **Admin + desk-staff** logins

## Project layout

```
OyubiaCYF/
├── app/                  ← application code (kept OUT of the web root)
│   ├── config.sample.php ← copy to config.php and fill in DB credentials
│   ├── config.php        ← your real credentials (git-ignored, created by you)
│   ├── db.php  helpers.php  auth.php
│   ├── controllers/  services/  views/
├── database/
│   └── schema.sql        ← MySQL schema (also run automatically by install.php)
├── public/               ← THIS is the web root (document root points here)
│   ├── index.php  .htaccess  install.php  offline.html
│   ├── manifest.webmanifest  service-worker.js
│   └── assets/ (css, js, icons)
└── README.md
```

## Reg number format

`OYCF-{year}-{seq}` — e.g. `OYCF-2026-0014`. The sequence runs continuously across
the whole event (everyone, every congregation, and visitors share one counter), so
the highest number equals the total number of people registered that year.
Reg numbers are assigned **on the server** so two offline desks never collide — a
record registered offline shows "pending" until it syncs (usually seconds).

---

## Deploying to cPanel

1. **Create the database** — cPanel → *MySQL® Databases*:
   - Create a database (e.g. `youracct_oyubiacyf`)
   - Create a user with a strong password
   - Add the user to the database with **All Privileges**

2. **Upload the files** — using cPanel *File Manager* or FTP. Recommended layout:
   - Put the contents of **`public/`** into your domain's document root
     (usually `public_html/`).
   - Put the **`app/`** and **`database/`** folders **one level above**
     `public_html/` (so they're not web-accessible). The paths in `public/index.php`
     (`__DIR__ . '/../app'`) already expect this.
   - *Alternative:* upload the whole project and point the domain/subdomain
     document root at the `public/` folder (cPanel → *Domains* → document root).

3. **Configure** — copy `app/config.sample.php` to `app/config.php` and fill in:
   - `db.host` (usually `localhost`), `db.name`, `db.user`, `db.pass`
   - `app_key` → a long random string
   - `base_url` → leave `''` if at the domain root; set to `/subfolder` if installed
     in one.

4. **Run the installer** — visit `https://yourdomain/install.php`:
   - It creates all tables, your first **admin** account, and this year's
     **edition** (activated).
   - **Delete `public/install.php` afterwards.**

5. **Sign in** at `https://yourdomain/login` and start registering.

### HTTPS note
PWA/offline features and the camera require **HTTPS**. cPanel provides free
*AutoSSL* (Let's Encrypt) — enable it for the domain.

---

## Day-to-day use

- **Admin** sets up the year under *Editions*, adds desk-staff under *Users*,
  manages congregations, and cleans up duplicate people under *Merge*.
- **Desk staff** use *Register*: pick the category, fill the form, and a reg number
  is assigned.
- Add a congregation once (with minister name/phone/address); afterwards everyone
  from that congregation is attached to it and appears on its roster.
- For someone who attended a previous year, use the **"Returning attendee?"** search
  at the top of the registration form to link their existing record before saving.
  Their full attendance history appears on their profile under *Attendees*.
- Export a congregation's roster from *Congregations → Roster* (Print → Save as PDF)
  or **CSV** (opens in Excel/Sheets). The Attendees page also exports the whole
  edition to CSV, honouring any active search filter.

### Offline behaviour
When a desk loses internet, registrations are saved on that device and the sync dot
in the top bar turns amber. They upload automatically when the connection returns;
the reg number becomes available after syncing.

---

## Version control & deploying from GitHub (cPanel Git)

This repo includes a `.cpanel.yml` so cPanel's **Git™ Version Control** can deploy
it automatically. `app/config.php` is git-ignored, so your DB credentials never go
to GitHub and are never overwritten by a deploy.

**One-time setup**

1. **Push to GitHub** from your computer:
   ```bash
   git remote add origin https://github.com/<you>/oyubiacyf.git
   git push -u origin main
   ```
2. In **cPanel → Git™ Version Control → Create**, set the *Clone URL* to your repo
   (for a private repo, add cPanel's SSH key as a GitHub *Deploy key* first) and a
   repository path like `/home/<you>/repositories/oyubiacyf`.
3. Create `app/config.php` **on the server** once (copy from `config.sample.php` via
   File Manager) with your real DB credentials. Deploys won't touch it.
4. Before deploying, update the production and demo folder names in `.cpanel.yml`
   if your actual domains differ from `oyubiacyf.com` and `demo.oyubiacyf.com`.
   Point each domain document root at its own `public/` directory. This isolated
   layout prevents OyubiaCYF from overwriting any other application on the account.
5. In cPanel, click **Manage → Pull or Deploy → Update from Remote**, then **Deploy
   HEAD Commit**. Production and demo each receive separate `public/`, `app/`, and
   `database/` directories and use separate `app/config.php` database settings.
6. Visit `/install.php` once on each environment, then delete its deployed
   `install.php` file after setup.

**Everyday workflow**

```bash
# edit locally, then:
git add -A
git commit -m "describe the change"
git push
```
Then in cPanel: **Update from Remote → Deploy HEAD Commit**. (Some hosts can
auto-deploy on push via a webhook — optional.)

> If your `.cpanel.yml` tasks fail because `$HOME` isn't expanded, replace it with
> your absolute home path, e.g. `export DEPLOYPATH=/home/youruser/public_html`.

## Local development

Requires PHP 8+ and MySQL.

```bash
# 1. Create a local DB + user, then copy config and set credentials:
cp app/config.sample.php app/config.php   # edit db.* values

# 2. Serve the public folder:
php -S 127.0.0.1:8099 -t public

# 3. Visit http://127.0.0.1:8099/install.php to set up, then /login
```

## Tech notes
- No Composer/Node build step — deploys by copying files.
- Roster PDFs are produced via the browser's print dialog (Save as PDF), which is
  reliable on shared hosting; rosters also export to CSV.
- All inputs are escaped on output; forms are CSRF-protected; passwords are hashed
  with `password_hash()`; queries use prepared statements.
