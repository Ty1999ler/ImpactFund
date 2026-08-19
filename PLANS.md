# Alumo Impact static rebuild — forward plans

## Forms (two, to wire after the static build)

The static site can't process forms by itself; each form posts to a small backend.

### Form 1 — Contact form → sends an email
Appears on Home / About the Fund / Past Winners (Name, Email, Message).
Options, in order of preference:
1. **Power Automate flow** — "When an HTTP request is received" trigger → "Send an email (V2)"
   via the org's M365. Form JS POSTs JSON to the flow URL. No hosting dependency,
   stays in Microsoft stack, same pattern as the SharePoint form below.
2. **Tiny PHP handler on GoDaddy** (`/contact.php` + `mail()`) — works on the existing
   shared hosting even with a static site; no third party.
3. **Formspree/Basin** — zero code, external service, free tier limits.

### Form 2 — Application form → writes to SharePoint
New form (live site currently has no application form — submissions closed).
Recommended: **Power Automate** — "When an HTTP request is received" → "Create item"
in a SharePoint list. Static form JS POSTs JSON; flow validates + creates the list item
and can also send a confirmation email. Notes:
- The trigger URL embeds a SAS token — fine for this risk level; can front with an
  Azure Function later if abuse becomes a concern.
- Alternative if speed matters over look: embed a Microsoft Form styled around the page.

Both forms share the same front-end pattern: `<form data-handler="...">` + a small
`fetch()` submit in `/js/main.js`, success/error message inline, honeypot field for spam.

## FR/EN (long run)
- French source content is already mirrored: `_source/pages-fr/*.html` (TranslatePress output).
- Plan: build `/fr/<slug>/index.html` mirroring the EN structure — same CSS/JS, translated
  text. The header language switcher already links EN pages ↔ `/fr/...` equivalents.
- `<html lang="en">` / `lang="fr"` + `hreflang` link tags when FR ships.

## Application form (structure captured 2026-08-18 from Gravity Forms id=2 "Multi Step")
3 steps: 1. Contact information (primary + secondary contact, organization info,
institution select fed from js/schools-data.js) · 2. Project Information (title,
category, funding amounts, acknowledgement, summary w/ 1000-char cap, student counts)
· 3. Required Documents (5 file uploads: budget, team, action plan, additional,
letter of support + consent). Buttons: Move forward / Previous / Done.
Scheduled reveal on /apply-now/: data-opens-at="2026-09-01T00:00:00-04:00";
preview early with ?preview-form=1. FR version pending FR copy.
NOTE for SharePoint wiring: the file uploads mean the Power Automate flow must be
an HTTP trigger accepting multipart (files → SharePoint document library, fields →
list item). Microsoft Forms is NOT viable (file upload requires tenant sign-in;
student applicants are external).

## Partner schools data
Real list (128 rows, provided 2026-08-18) lives in js/schools-data.js
(window.ALUMO_SCHOOLS) — single source of truth for the partner-schools list page
and the form's institution dropdown. Raw table: _tools/partner-schools-raw.tsv.
Display columns on the list page: school, association, contact email (no names).
Data flags: one email missing its @ ("j_riddell2fanshawec.ca" — Fanshawe Students'
Union), several TBD contacts.

## Hosting / deployment
GitHub: https://github.com/Ty1999ler/ImpactFund (public). Deploy = clone on the
user's server, serve this directory at the server root (port 8777 planned), put
Cloudflare in front (tunnel preferred — avoids opening the port). Site uses
root-relative URLs: must be served at a domain root, not a subpath.
`_source/` and `_tools/` are reference/build-time only — never deployed
(_source/ is git-ignored).
