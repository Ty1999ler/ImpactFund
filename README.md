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

## Deploy

Any static file server works (nginx, Caddy, Apache, or `python -m http.server` behind
Cloudflare for low-traffic use). Serve this directory as the document root and make
sure `/` maps to `index.html` in every folder (all listed servers do this by default).
With Cloudflare in front, point the origin at the server's port and let Cloudflare
terminate TLS; enable "Always Use HTTPS".

## Pending wiring (see PLANS.md)

- Contact form → email (Power Automate HTTP trigger planned); form stub is
  `data-handler="email"` in the markup, submit handler in `js/main.js`.
- Application form → SharePoint list (Power Automate); shows on Apply Now when
  submissions open.
- Partner-schools list: placeholder rows live in ONE array at the top of
  `js/partner-schools.js` — swap in the real list there.
