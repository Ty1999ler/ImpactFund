# Alumo Impact static rebuild — forward plans

## Forms (two) — NO Power Automate (decided 2026-08-18)

Power Automate's HTTP-request trigger is a premium connector (~$15/user/mo) — not
worth it for two forms. Instead: one tiny self-hosted **forms-api** container on the
same server as the site (behind the same Cloudflare hostname under `/api/`).

### forms-api container (Node or Python, ~100 lines)
- `POST /api/contact` — contact form (Name/Email/Message from Home/About/Past
  Winners): sends an email to the team inbox. Email via free transactional SMTP
  (Resend 3k/mo free, or Brevo 300/day free, or M365 SMTP AUTH from a mailbox).
- `POST /api/apply` — application form (multipart: fields + 5 file uploads):
  1. always saves a local backup on the server (JSON + files folder), then
  2. delivers to SharePoint — pick ONE:
     a. **Graph API direct** (preferred): one-time Entra app registration with
        Sites.Selected on the target site; backend creates the list item and
        uploads files to a document library. $0 forever, needs tenant admin
        consent once.
     b. **Email relay**: backend emails the submission + attachments to a
        dedicated mailbox; an optional STANDARD-tier Power Automate flow
        ("When a new email arrives" → save to SharePoint) files it — standard
        connectors are included in existing M365 licenses, no premium needed.
        Caps attachments ~25MB total (email limits).
- Spam: Cloudflare Turnstile (free) on both forms + honeypot field.
- Front-end pattern stays: `<form data-handler="...">` + `fetch()` in js/main.js,
  inline success/error message.

OPEN DECISIONS: (1) Graph-direct vs email-relay for SharePoint; (2) which mailbox
receives contact messages; (3) which SMTP/sender (Resend vs M365 SMTP).

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
