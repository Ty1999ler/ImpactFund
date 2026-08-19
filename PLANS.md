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

## Deployment note
`_source/` and `_tools/` are reference/build-time only — exclude from any upload to hosting.
