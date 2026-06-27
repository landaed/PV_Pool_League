# PV Pool League — Site

The overhauled site now lives at the **web root** (it used to be under
`/new_site/`). Signups use the shared `SportsTeam` / `Player` tables; the
site's own configuration uses `ns_*` tables in the same database.

## Pages (public)

| URL | What it does |
|-----|--------------|
| `/index.html` (i.e. `pvpoolleagues.com`) | Home page with the 3D canvas intro and the admin-controlled poster modal. |
| `/locations.html` | Lists the registerable locations, grouped by region (driven by the dashboard). |
| `/register.html` | Pick a **region** first, then see only that region's sessions / divisions / bars. |
| `/dashboard.html` | Admin dashboard (login required). |

## One-time setup

1. Deploy the repo (plain HTML + PHP, same stack as before).
2. In a browser, open:

   ```
   https://pvpoolleagues.com/api/setup.php?run=1
   ```

   This creates the `ns_*` tables, adds the `Region` column to `SportsTeam`,
   normalizes the stored poster path, and seeds (only if empty):
   - the default admin — **username: `eli`**, **password: `1!Cheddar`**
   - four regions: **Calgary, Cochrane, Vancouver, Vancouver Island**
     (Calgary & Cochrane pre-filled with the existing bars/divisions).
3. Delete `api/setup.php` afterwards (optional but recommended).

The installer is idempotent — re-running it never overwrites existing data.

## What admins can do (dashboard)

- **Login** — required for everything below. Add more admins under the **Admins** tab.
- **Home Poster** — upload an image (or point to a URL) and toggle whether it pops up on the home page.
- **Schedules** — upload / replace / delete schedule files (PDF or image), each with a title; configure the landing-page button's label, which schedule it opens, and whether it shows.
- **Signups** — view every team with filtering by region / session / division / search; edit team & player details inline; add/remove players; delete teams; select teams and **email** all their players.
- **Registration Setup** — per region, manage **sessions** (open/close registration), **day/division** options, and **locations** (including a bulk paste box). Active locations also appear on the public Locations page.

## How it's wired

- `api/db.php` — shared DB connection (reuses `/php/db_connect.php` credentials) + helpers + admin session. Works with or without the mysqlnd driver.
- `api/auth.php` — login / logout / session check (passwords hashed with `password_hash`).
- `api/public.php` — unauthenticated reads used by the public pages (regions, region config, all locations, poster).
- `api/submit_registration.php` — public registration → writes the shared `SportsTeam` / `Player` tables (with `Region`), emails players (PHPMailer, same SMTP as before).
- `api/admin.php` — all authenticated dashboard actions (config CRUD, bulk locations, signups CRUD, poster, email).
- `uploads/` — uploaded poster images (script execution blocked via `.htaccess`).

## Signups share the existing data

The dashboard and the registration form read/write the **same** `SportsTeam` /
`Player` tables, so every existing signup shows up and edits/new registrations
are reflected everywhere. `Region` tags new signups; older rows without it are
shown under Calgary/Cochrane via the same name/bar heuristic the old site used.

> Only the *configuration* (regions, sessions, divisions, locations, admins,
> poster) lives in separate `ns_*` tables.

## Notes / TODO

- The old auxiliary pages (`about.html`, `schedule.html`, `divisions.html`, `stats.html`, etc.) are unchanged and still linked from the new nav.
- DB and SMTP credentials are inherited from `/php/db_connect.php` and the PHPMailer config. Moving these to environment variables is still recommended.
