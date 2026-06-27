# PV Pool League — New Site (`/new_site`)

A self-contained overhaul of the site that lives entirely under `/new_site/`
so it does **not** touch the existing/old pages or data. The old site keeps
using the `SportsTeam` / `Player` tables; the new site uses its own `ns_*`
tables in the same database.

## Pages (public)

| URL | What it does |
|-----|--------------|
| `/new_site/index.html` | Home page. Shows the admin-controlled poster modal on load. |
| `/new_site/locations.html` | Lists the registerable locations, grouped by region (driven by the dashboard). |
| `/new_site/register.html` | Pick a **region** first, then see only that region's sessions / divisions / bars. |
| `/new_site/dashboard.html` | Admin dashboard (login required). |

## One-time setup

1. Deploy the repo as usual (the new site is plain HTML + PHP, same stack as the old site).
2. In a browser, open:

   ```
   https://YOUR-DOMAIN/new_site/api/setup.php?run=1
   ```

   This creates all `ns_*` tables and seeds:
   - the default admin — **username: `eli`**, **password: `1!Cheddar`**
   - four regions: **Calgary, Cochrane, Vancouver, Vancouver Island**
     (Calgary & Cochrane are pre-filled with the existing bars/divisions).
3. Delete `new_site/api/setup.php` afterwards (optional but recommended).

The installer is idempotent — re-running it never overwrites existing data.

## What admins can do (dashboard)

- **Login** — required for everything below. Add more admins (username + password) under the **Admins** tab.
- **Home Poster** — upload an image (or point to an existing URL) and toggle whether it pops up on the home page.
- **Signups** — view every team with filtering by region / session / division / search; edit team & player details inline; add/remove players; delete teams; select teams and **email** all their players.
- **Registration Setup** — per region, manage the **sessions** (and open/close registration), **day/division** options, and **locations**. Locations marked active also appear on the public Locations page.

## How it's wired

- `api/db.php` — shared DB connection (reuses the existing `/php/db_connect.php` credentials) + helpers + admin session.
- `api/auth.php` — login / logout / session check (passwords hashed with `password_hash`).
- `api/public.php` — unauthenticated reads used by the public pages (regions, region config, all locations, poster).
- `api/submit_registration.php` — public registration handler → writes `ns_teams` / `ns_players`, emails players (PHPMailer, same SMTP as the old site).
- `api/admin.php` — all authenticated dashboard actions (config CRUD, signups CRUD, poster, email).
- `uploads/` — uploaded poster images (script execution blocked via `.htaccess`).

## Signups share the existing data

The new dashboard and the new registration form read/write the **same**
`SportsTeam` / `Player` tables the old site uses — so the dashboard shows every
existing signup, and edits/deletes/new registrations are reflected everywhere.
Setup adds one nullable `Region` column to `SportsTeam` to tag which region a
team belongs to; older rows without it are shown under Calgary/Cochrane using
the same name/bar heuristic the old site used.

> Only the new-site *configuration* (regions, sessions, divisions, locations,
> admins, poster) lives in separate `ns_*` tables. The signups themselves are
> shared.

If you set things up before this change, just re-run
`/new_site/api/setup.php?run=1` once to add the `Region` column (idempotent).

## Notes / TODO

- DB and SMTP credentials are inherited from the existing `/php/db_connect.php` and PHPMailer config. Moving these to environment variables is still recommended (carried over from the old site).
