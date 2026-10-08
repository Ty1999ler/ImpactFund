# Student Impact Fund by Alumo — static site

Clean static HTML/CSS rebuild of [alumoimpact.ca](https://alumoimpact.ca) (previously
WordPress + Elementor). No PHP, no build step, no frameworks — plain HTML, CSS, and a
little vanilla JS.

## Structure

```
index.html                 EN home            fr/index.html            FR home
about-the-fund/            + 7 more EN pages  fr/about-the-fund/       + 7 more FR pages
past-winners/2/            winners page 2     fr/past-winners/2/
css/style.css              shared design system (tokens, header/footer, forms)
css/pages/<slug>.css       per-page styles (fr-home.css = FR-only overrides)
js/main.js                 burger menu, language dropdown, sticky header, form stub
assets/img|fonts|docs      all images, self-hosted Inter + Source Serif Pro, PDFs
BUILD_NOTES.md             EN build conventions   BUILD_NOTES_FR.md  FR conventions
PLANS.md                   forms + i18n forward plans
_tools/mirror.py           re-downloads the live-site reference mirror into _source/
_tools/submit_test.py      one-command [TEST] application to staging (never production)
```

`_source/` (git-ignored) is a mirror of the live WordPress site used as the
build reference. Regenerate it with `python _tools/mirror.py`.

## Run locally

```bash
python -m http.server 8777
```

then open http://localhost:8777/. Root-relative URLs mean the site must be served
from the domain/server root (not a subfolder).

## Deploy (Docker, recommended)

Every push to `main` triggers GitHub Actions to build and push
`ghcr.io/ty1999ler/impactfund:latest` (nginx-alpine serving the site on **port 8777**).
On the server:

```bash
git clone https://github.com/Ty1999ler/ImpactFund.git && cd ImpactFund
docker compose up -d          # pulls the image, serves on :8777
```

Point the Cloudflare tunnel at `http://localhost:8777`. Watchtower label is already
set, so if Watchtower runs on the server, new pushes deploy automatically.
If the GHCR package is private (default on first push), either make it public in
GitHub → Packages → impactfund → settings, or `docker login ghcr.io` with a
`read:packages` token first.

To build locally on the server instead of pulling: `docker build -t impactfund . && docker run -d -p 8777:8777 --restart unless-stopped impactfund`

## Deploy (no Docker)

Any static file server works — serve this directory as the document root at the
domain root (the site uses root-relative URLs): e.g. `python -m http.server 8777`
or `caddy file-server --listen :8777`.

## Forms backend (PHP — works on GoDaddy and in the php:apache image)

- `api/contact.php` — contact form → email to the team inbox.
- `api/apply.php` — application form (fields + 5 uploads) → archived on the
  server under `_submissions/`, then delivered per `delivery_mode` in the
  config: `email` relay / `graph` (Microsoft Graph → SharePoint) / `off`.
- `api/winners.php` — the unlisted Winners page (`/winners-fall-2026/`,
  `/fr/gagnants-automne-2026/`; front end `js/winners-form.js`): three documents
  → archived under `_submissions/winners/<id>/`, then filed in SharePoint ONLY
  (`<root_folder>/<round>/<Full name> - <Project title>/`), never emailed, server
  copy deleted once filed. Configured by the `winners` block in the config —
  `round`, `root_folder`, `opens_at` (ISO with offset, or ''), `closes_at`
  (REQUIRED: ISO with offset, or 'none'), `close_grace_minutes`, `notify_to`,
  `drive_id`, `create_root` — and stays closed ("not ready", 503) unless
  `delivery_mode` is `graph` with credentials.
- Setup: copy `api/config.example.php` to `api/config.php` and fill it in
  (config.php is git-ignored — this repo is public; never commit it).
- The nginx image does NOT run PHP: once the forms go live, build with
  `Dockerfile.php` instead — or serve from GoDaddy, where PHP just works.
- Front-end wiring: `js/main.js` (contact) and `js/apply-form.js` (application;
  3-step).
- Submission window: `data-opens-at` / `data-closes-at` (ISO dates WITH an
  offset) on the scheduled elements swap the open/closed cards and notes
  (`js/main.js`, once per page load); the server enforces the same window
  with `opens_at` / `closes_at` (+ `close_grace_minutes`) in api/config.php,
  and the default close date is committed in `api/apply.php`. Preview with
  `?preview-form=1` (forces the open state) or
  `?preview-at=<ISO date WITH offset, e.g. 2026-10-16T09:00:00-04:00>` (the
  whole site as of that moment; sticks for the tab, `?preview-at=off` ends
  it; a date without an offset is ignored, and it works only on the
  `PREVIEW_HOSTS` in `js/main.js` — staging and its before./open./closed.
  copies, the NAS, localhost — never on production) — both are visual only.
  Checks: `python _tools/verify_integrity.py` (check 6, which also compares
  `DEFAULT_CLOSES_AT` with the pages) and
  `python _tools/test_schedule_preview.py` (headless Edge).
- Preview hosts: `staging.alumoimpact.ca` plus three pinned copies,
  `before.alumoimpact.ca`, `open.alumoimpact.ca` and
  `closed.alumoimpact.ca`, all serving the same files — the three are cPanel
  subdomains whose document root is staging's folder (`~/staging`), each
  with its own AutoSSL certificate, so every deploy to staging updates all
  four. Staging follows the real clock; `before.` shows the site as it
  looks just before the submission window opens, `open.` always shows the
  window open and `closed.` shows the site as from the close moment (all
  worked out by `js/main.js` from the pages' own dates, with a banner
  linking to the same page on staging). Once the opening is past, and
  until the pages carry the next window's dates, `before.` shows the same
  closed site as `closed.` (next round's text included) under its own
  banner, so it is not a review link then. `?preview-at=` still wins on all
  of them. A leading `www.` (cPanel's alias) is ignored, so `www.closed.`
  behaves like `closed.`. Visual only: their forms post to staging's
  `api/`.
- Test submissions: anything sent from staging, its three copies or the NAS
  is marked automatically by the server (`site_is_test()` in
  `api/_lib.php`, by host name; `'test_mode'` in `api/config.php` forces it
  on or off, except on production): `[TEST] ` in front of every email
  subject and of the SharePoint list item's Title, the documents under a
  top-level `TEST` folder, `"test": true` in `submission.json`. Production
  (alumoimpact.ca, www.) is never marked, whatever `test_mode` says.
  To send one: `python _tools/submit_test.py --email you@example.com`
  (`--site staging|before|open|closed|https://<test host>`, `--locale fr`,
  `--contact`, `--dry-run`) — a complete valid application with five tiny
  PDFs, checked against `api/apply.php`'s own rules first; it refuses
  alumoimpact.ca and the retired september. copy. Counts toward the
  5-applications-per-hour limit.

## Data

- Partner-schools list: `js/schools-data.js` (window.ALUMO_SCHOOLS) —
  single source of truth for the list page and the application form's
  institution dropdown. Edit only there.
