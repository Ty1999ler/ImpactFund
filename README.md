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

## Pending wiring (see PLANS.md)

- Contact form → email (Power Automate HTTP trigger planned); form stub is
  `data-handler="email"` in the markup, submit handler in `js/main.js`.
- Application form → SharePoint list (Power Automate); shows on Apply Now when
  submissions open.
- Partner-schools list: placeholder rows live in ONE array at the top of
  `js/partner-schools.js` — swap in the real list there.
