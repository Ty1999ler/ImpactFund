# Alumo Impact static rebuild — forward plans

## Client edits rounds (2026-08-26) — STATUS
SHIPPED: nav rename, Home tab, scheduled Apply Now tab + orange-card Sept-1 swap,
Step 2 doc list + Step 4 help block, apply-now help section, FAQ doc answer,
category "Other" (+backend), banner quirk removed, empty province cards hidden.

### Still waiting on from Alumo (buttons/blocks staged & hidden until these land)
1. Instructions PDF ("application guide") — EN (+FR?) → /assets/docs/, unhide buttons
2. The 3 templates (project overview, team members, action plan) — EN (+FR?)
3. FINAL/renamed letter of support → also unhide the Partners-page download block
4. ANSWER: should the form's five upload labels match the Step 2 list (Project
   Overview / Team member information / Action plan-timeline / Budget / Letter of
   Support)? Currently: Detailed budget / Team Members / Action plan and schedule /
   Additional information (optional) / Letter of support.
5. ANSWER: "Apply Now" (and "Home") in the footer menu too, or header only (built header-only)?
6. Corrections doc approvals from Sophia (Alumo-Site-Issues) — spelling/content fixes on hold until then.
7. Real privacy + cookies policy copy (EN + FR); consent-link decision (remove or add tool).
8. Contact emails for the 10 "Coming Soon" partners; confirmations for the 4 province moves + shared-email pairs.

## Forms delivery — STATUS 2026-08-26
Credentials RECEIVED by Tyler (held privately — never in this repo/chat): M365 SMTP
values + SharePoint app registration (tenant/client/secret + site URL). Decision:
wire everything ONCE, directly on GoDaddy, when delegate access arrives — staging
keeps the submit stub until then. On wiring day: fill api/config.php on GoDaddy
(delivery_mode 'graph'; resolve site_id/list_id/drive_id from the site URL via
Graph), create the Applications list + document library (rows-not-PDFs design:
fields → list columns, uploads → folder per application), PHPMailer+SMTP for
outgoing mail, end-to-end test before Sept 1. The Cakemail/PDF-by-email idea
(below) was superseded — team doesn't want PDFs; email is notification-only if
used at all.

## (superseded) Forms delivery — FIRST ITERATION per Alumo IT (Roxanne, 2026-08-26)
No SharePoint connection for v1. Instead: on submit, api/apply.php generates a
PDF summary of the application, attaches the uploaded documents, and sends via
the **Cakemail API** (Alumo's existing email vendor — needs their API key +
sender + destination address). Server-side archive in _submissions/ remains the
authoritative copy. Attachment-size guard: always send the PDF summary; attach
files only when under the email limit; originals stay in the archive.
Contact form: same Cakemail path. This supersedes the graph/email delivery_mode
plan below for v1 — SharePoint (Graph) can return as a later iteration.
IT asks reduced to: GoDaddy delegate access + Cakemail API key.
Hosting answer given to IT: staging on wareham.stream (noindexed), production on
Alumo's existing GoDaddy plan (no DNS/domain changes).

## CMS (client asked 2026-08-19 — quote in preparation)
Two-tier plan, both compatible with the existing pipeline (git → Actions → GHCR → Watchtower):
1. **CMS-lite** (~2-3 dev-days): small password-protected admin page on the existing
   PHP container editing a content.json + schools-data.js — covers the things that
   actually change (submission dates/status, schools list, FAQ, winners). Pages read
   those values at load. No new infra.
2. **Full CMS** (~2-3 dev-weeks): extract page copy to markdown/JSON, add a static
   site generator (Eleventy) build step in Actions, put a git-based CMS UI on top
   (Sveltia/Decap at /admin/, GitHub OAuth via a tiny proxy on the server). Client
   edits any text in a browser; every save = commit = auto-deploy. Site stays static
   (fast, no DB, nothing to hack).

## Forms (two) — NO Power Automate (decided 2026-08-18)

Power Automate's HTTP-request trigger is a premium connector (~$15/user/mo) — not
worth it for two forms. Final hosting is GoDaddy shared hosting (decided
2026-08-18), which runs PHP natively — so the forms are **two small PHP handlers
deployed with the static site**. $0, nothing extra to run.

### /api/contact.php — contact form (Name/Email/Message on Home/About/Past Winners)
Validates + sends an email to the team inbox. PHP `mail()` works out of the box on
GoDaddy; switch to PHPMailer + SMTP AUTH (M365 mailbox) if deliverability is poor.

### /api/apply.php — application form (multipart: fields + 5 file uploads)
1. Validates, saves a server-side backup (JSON + files folder outside webroot), then
2. delivers to SharePoint — pick ONE:
   a. **Graph API direct** (preferred): one-time free Entra app registration with
      Sites.Selected on the target site; PHP calls Graph via cURL (~50 lines) to
      create the list item + upload files to a document library. Needs tenant
      admin consent once.
   b. **Email relay**: PHP emails the submission + attachments to a dedicated
      mailbox; an optional STANDARD-tier Power Automate flow ("When a new email
      arrives" → save to SharePoint) files it — standard connectors are included
      in M365, no premium. Caps attachments ~25MB total (email limits); file cap
      per upload ~10MB is sensible regardless (GF's 128MB was absurd).

### Interim hosting on the user's server
Base the Docker image on `php:8-apache` instead of nginx — then the SAME PHP
handlers work identically on the interim server and after the GoDaddy cutover.
No rework at migration time.

- Spam: Cloudflare Turnstile (free) on both forms + honeypot field. (Note: if
  final hosting is GoDaddy without Cloudflare in front, use honeypot + simple
  rate-limit in PHP instead.)
- Front-end pattern stays: `<form data-handler="...">` + `fetch()` in js/main.js,
  inline success/error message.

OPEN DECISIONS: (1) Graph-direct vs email-relay for SharePoint; (2) which mailbox
receives contact messages; (3) sender identity for outgoing mail.

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
NOTE for SharePoint wiring: handled by the self-hosted forms-api (see "Forms" section
above) — multipart POST to /api/apply. Microsoft Forms is NOT viable (file upload
requires tenant sign-in; student applicants are external).

## Partner schools data
Sept 2026 list (155 partners after cleaning, loaded 2026-08-26) lives in js/schools-data.js
(window.ALUMO_SCHOOLS) — single source of truth for the partner-schools list page
and the form's institution dropdown. Raw: _tools/partner-list-sept2026.json (as received) and partner-schools-sept2026-clean.tsv (as loaded); the 2026-08-18 original remains in partner-schools-raw.tsv.
Display columns on the list page: school, association, contact email (no names).
Data flags: 10 "Contact Email Coming Soon" entries; 4 province corrections applied (Burman/King's→AB, Crandall/Maritime Forest Tech→NB) pending Alumo confirmation — see SITE-REVIEW.md update block.

## Hosting / deployment
GitHub: https://github.com/Ty1999ler/ImpactFund (public). Deploy = clone on the
user's server, serve this directory at the server root (port 8777 planned), put
Cloudflare in front (tunnel preferred — avoids opening the port). Site uses
root-relative URLs: must be served at a domain root, not a subpath.
`_source/` and `_tools/` are reference/build-time only — never deployed
(_source/ is git-ignored).
