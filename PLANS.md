# Alumo Impact static rebuild — forward plans

## Fall 2026 close (Thu Oct 15, 11:59 pm ET) — BUILT 2026-10-06 (uncommitted), not deployed
Decided with Alumo (Hafsa, Oct 2026): automatic close at 11:59 pm Eastern Oct 15;
ONE note on Partners; the "closed for now" note on Submit your Idea; next round is
Winter 2027 (Jan 4 – Mar 1, 2027); we write the French, Hafsa approves it later
(drafts in FR_DRAFTS.md). Everything goes to staging first.

Before this build the site could only OPEN (main.js knew data-opens-at only; api/apply.php
checked only opens_at): on Oct 16 the home/About cards would still have said "now open",
the form would have stayed live and the server would have kept accepting applications.

Close moment: `2026-10-15T23:59:59-04:00` everywhere (still EDT; DST ends Nov 1). Pages
are open at 23:59:58 and closed from 23:59:59. Server keeps accepting for 15 more
minutes (close_grace_minutes) so an upload that was already on its way at 11:59 isn't
refused. Tell Alumo those count as on time.

Built (one branch → main → staging → release):
1. js/main.js — optional `data-closes-at`; window = [opens-at, closes-at). hasAttribute +
   isNaN guards (never new Date(null)); dates must carry an offset or the element stays
   as authored. `data-show-when="closed"` = shown only OUTSIDE the window (the notes).
   Evaluated once per load, no timer (the server is the gate). `?preview-form=1` still
   forces open. `?preview-at=<ISO with offset>` renders the site as of that moment and
   STICKS for the tab (sessionStorage key sifPreviewAt); `?preview-at=off` ends it;
   garbage is ignored. While a preview is on, a fixed bottom-left banner says "Preview:
   the site as it will look on October 16, 2026, 9:00 a.m. ET. Exit preview" (FR
   "Aperçu : le site tel qu'il apparaîtra le 16 octobre 2026 à 9 h 00 (heure de l'Est).
   Quitter l'aperçu"; in FR_DRAFTS.md section A). Visual only — the server never reads
   it. preview-at works ONLY on PREVIEW_HOSTS in main.js (staging, the before./open./closed.
   copies of item 12, the NAS, localhost): production ignores it and clears any stored value, so a shared link can
   never show real visitors a closed site. Given twice, the LAST preview-at wins and the
   exit link drops every copy. Banner z-index 98, under the header's 99, so the open
   mobile menu (language switcher) covers it; the body gets bottom padding so the end
   of the footer scrolls clear of it. On localhost the consent banner sits on top.
2. HTML — data-closes-at on the 8 scheduled elements (home pill + period card, About
   card, Submit form section; same four in FR) + the 4 notes = 12 per date.
3. Stale closed-state text → Winter 2027 (EN + FR, FR_DRAFTS.md section A): hero pill
   "Submissions open in January"; home/About closed cards "January 4 to March 1, 2027" +
   "Submissions are currently closed."; FR kicker now "Prochaine période de soumission"
   (was "date limite": a range isn't a deadline). Submit page's bottom card unchanged.
4. Partners note — span at the end of the orange intro card's <p>, <br><br> inside the
   span (hidden with it). List stays visible.
5. Submit note — orange `.apply-closed-notice` under the H1 (rule in apply-now.css, no
   display set, so [hidden] works). The bottom closed card also stays — tell Hafsa.
6. api/apply.php — closes_at + close_grace_minutes gate; BOTH schedule gates sit above
   rate_limit() (refusals don't eat the 5/hour budget). `DEFAULT_CLOSES_AT` /
   `DEFAULT_CLOSE_GRACE_MINUTES` constants at the top of apply.php apply when config.php
   has no closes_at key or it is null (only an explicit '' = never closes, as in
   config.ci.php), so the release itself carries the gate. schedule_time() in _lib.php
   accepts only an ISO date WITH offset; anything else — false and other non-strings
   included — fails CLOSED (error_log). 403 "Submissions are now closed."
   → T.errClosed EN/FR in js/apply-form.js. Keys documented in config.example.php; its
   and redeliver.php's cron line now say /home/<cpanel-user>/…
7. CI — forms-smoke: 403 after close+grace for a COMPLETE submission posted with
   ?preview-form=1&preview-at=… (nothing stored); 422 inside the grace window; 403 +
   log line when closes_at is 'not-a-date'; no closes_at key → the committed default
   (expected 422/403 worked out from the clock). Each sed is grep-checked. Not run yet
   (no Docker here) — first run is on push.
8. verify_integrity.py — check 6, per TAG: both dates, ISO with offset, opens before
   closes, one value of each across LIVE_PAGES (a future winners page lives outside
   LIVE_PAGES, so its own deadline can't trip it); DEFAULT_CLOSES_AT in apply.php and
   config.example.php's closes_at must equal that data-closes-at (forms-smoke only reads
   the constant back, so this is what keeps server and pages together).
9. Cache-busting: main.js v8 (v6, then v7 for item 12, v8 for its before. copy) on all 12 live pages, apply-form.js v11 + apply-now.css v9
   on apply-now + fr/soumettre. Stale "Revealed automatically on Sept 1" footer comment
   deleted on all 12. Partners meta/og descriptions lost the stale September sentence.
10. Open cards now say "From: September 1st to October 15th, 11:59 pm ET" / "Du 1er
   septembre au 15 octobre à 23 h 59 (heure de l'Est)" (checked at 375px). NOTE: this
   is the only new French that is visible BEFORE the close — Hafsa should see it first.
11. Tests: `python _tools/verify_integrity.py` (passes) and
   `python _tools/test_schedule_preview.py` — headless Edge, EN+FR home/About/Submit/
   Partners at every boundary, stickiness walk, exit link, preview-at given twice,
   375px layout, open burger menu over the banner (375/768/1024), footer clear of the
   banner at the page end, and a second run as http://alumoimpact.ca:<port>/ where
   preview-at must do nothing; since item 12 also runs as before./open./closed./www.closed./
   www./staging./september.alumoimpact.ca, with the clock faked around the window, and
   a test-only probe page that tells the before copy's real clock from its pinned one
   (passes, 285 checks; `--screenshots` saves PNGs to %TEMP%). README has the one-paragraph summary.
12. Pinned preview copies (2026-10-07; before. added 2026-10-08): before.alumoimpact.ca,
   open.alumoimpact.ca and closed.alumoimpact.ca are cPanel subdomains on staging's
   document root (same files, nothing deployed separately); main.js picks the view from
   the host name. before. = the site just before the window opens (real clock until the
   opening, its last second before it after that — so the closed cards and both notes);
   open. = the window open (real clock inside it, its first moment before it, its last
   second after it); closed. = the site as from the close moment (real clock once past
   it). All read from the page's own data-opens-at/data-closes-at, so nothing changes
   per round. Banner: "Preview copy: the site as it looks before submissions open on
   August 31, 2026, 6:30 p.m. ET. Go to staging" / "...as it looks while submissions are
   open. Go to staging" / "...as it will look after submissions close on October 15,
   2026, 11:59 p.m. ET. Go to staging" (the closed one lost its parentheses — the French
   date already ends in "(heure de l'Est)"; FR in FR_DRAFTS.md section A); on pages with
   nothing scheduled the "on {date}" is left out; the link goes to the same page on
   staging. ?preview-at= still wins there (its exit link returns to the pinned view);
   ?preview-form=1 still forces open (and shows the open-copy banner, even on before. and
   closed.). A leading www. (cPanel's alias) is ignored, so www.closed. pins like closed.
   and www.alumoimpact.ca stays production. september.alumoimpact.ca is no longer a
   preview host. Analytics stays off on all of them (not in ENABLED_HOSTS). Visual only:
   the copies' forms post to staging's api/apply.php (staging's config) — and are marked
   [TEST] there (item 13). NOTE: before. matches the real pre-opening site only once the
   pages carry the NEXT window's dates (the December job). Until then (opening Aug 31,
   2026 is past) it shows exactly what closed. shows — Winter 2027 text ("Submissions
   open in January", "January 4 to March 1, 2027", both Jan 4 notes) — under a banner
   saying "before submissions open on August 31, 2026". Don't send it to Hafsa as a
   review link; she approves the before-banner French from FR_DRAFTS.md section A.
13. [TEST] marking (2026-10-08, server side only — the live site's behaviour is unchanged):
   site_is_test($cfg) in api/_lib.php — alumoimpact.ca and www. (SITE_LIVE_HOSTS) are
   never test, checked FIRST, whatever config says (a staging config copied to production
   must not mark real applications); otherwise config 'test_mode' true/false wins;
   otherwise by HTTP_HOST (":port" and a leading "www." ignored) in SITE_TEST_HOSTS:
   staging., before., open., closed., september.alumoimpact.ca and
   impactfund.wareham.stream. localhost (CI) and the CLI are not test unless test_mode
   says so. apply.php works it out once, stores "test": true/false in
   submission.json, and then: "[TEST] " on the team relay email, the
   acknowledgement (EN + FR) and the failure notice; Graph: list item Title "[TEST] …"
   (set before graph_map_fields, the title trimmed — never the prefix — to stay within
   255) and the documents under a top-level TEST folder (TEST / Region / Title - School).
   redeliver.php has no host, so it reads the archived flag (absent = not test) and keeps
   all of it on a retry, its "delivered after retry" email included. contact.php: subject
   prefix. config.example.php documents 'test_mode'; config.ci.php leaves it out.
   forms-smoke: contact as staging/www.before./NAS → "[TEST] …", as alumoimpact.ca/www. →
   plain, and the same again in a container with test_mode true (site-forced: localhost
   and an IP → "[TEST] …", alumoimpact.ca/www. still plain); application as staging →
   "test": true + "[TEST]" acknowledgement; the existing
   localhost cases now also assert "test": false and plain subjects; an email-mode
   container (site-email) checks relay + acknowledgement for a test host and production
   (ci/fake-sendmail.sh now also appends to /tmp/mail.all; ci/mail-subjects.sh decodes
   subjects). Checked under php-wasm 7.4 + 8.3 (outside the repo): live-host output
   (emails, Graph requests, retries) identical to before apart from "test": false.
   NOTE for Alumo: test runs now create a "TEST" folder at the top of the document
   library and "[TEST] …" list items — theirs to ignore or delete, AFTER Tyler has
   checked them (server checklist, Oct 16): open. shows a working form until the close
   and posts to staging, so a real applicant who got that link would be marked TEST too.
14. _tools/submit_test.py (2026-10-08): one-command test application — see the server
   checklist below. Stdlib only; builds every required field with test values, an
   institution from js/schools-data.js, five tiny PDFs; checks the payload against
   api/apply.php's own rules (parsed from the file) before sending; refuses alumoimpact.ca
   and www., and september. (retired: it still runs the Aug 31 apply.php, which marks
   nothing); --dry-run, --contact, --locale fr. A refusal is explained from the JSON
   "error" (schedule gate / no config.php / check --email / fields), anything without one
   as "something in front of PHP (Cloudflare/WAF?)". Checked by feeding its exact bytes to
   apply.php / contact.php under php-wasm (200, every field stored as sent, TEST folder).

Server checklist (Tyler):
- staging + production api/config.php: nothing is REQUIRED now (no closes_at key → the
  committed default). Setting closes_at '2026-10-15T23:59:59-04:00' + close_grace_minutes
  15 explicitly is fine (backups OUTSIDE public folders); an explicit key wins over the
  default, so it must then be kept right. Safe probe after any edit:
  `curl.exe -sS -w "\n%{http_code}\n" -F primary_first_name=probe https://<host>/api/apply.php`
  → 422 while open, 403 after (refusals no longer count toward the 5/hour limit).
- Pinned preview copies (item 12), before Hafsa's review: cPanel → Domains → Create a
  New Domain, three times — before.alumoimpact.ca, open.alumoimpact.ca and
  closed.alumoimpact.ca — with the Document Root set to staging's folder, `staging`
  (= ~/staging, the one staging.alumoimpact.ca shows in cPanel → Domains; untick "Share
  document root" first if that box is shown, so it doesn't default to public_html). Then
  SSL/TLS Status → Run AutoSSL so all three get a certificate (the banner links to
  https://staging…, and Hafsa's links are https). If the domain's DNS is not on this
  cPanel (e.g. Cloudflare), add the three records there the way staging's is set up.
  Nothing to deploy: they serve whatever main put in ~/staging (robots.txt there is
  staging's no-index one). Check all three, EN + FR.
- BEFORE main goes to staging (item 13): look for DELIVERY-PENDING markers in staging's
  submissions_dir (`ls <submissions_dir>/*/DELIVERY-PENDING`). Those archives predate the
  "test" flag, so the retry cron would deliver them UNMARKED into the real library or
  relay — delete the marker of any that is only a test. Then, optional hardening: add
  `'test_mode' => true,` to staging's and the NAS's api/config.php, so whatever reaches
  them by another name (the NAS's LAN address :8777, an unlisted alias of ~/staging) is
  marked too. Never on production — and harmless if copied there by mistake:
  alumoimpact.ca / www. are never marked, whatever test_mode says.
- Test submission (items 13–14), once main is on staging: from a checkout,
  `python _tools/submit_test.py --email <your address>` (add `--dry-run` first to see
  every field; `--locale fr`; `--site before|open|closed` to send through a copy;
  `--contact` for the contact form). Expect HTTP 200 + an id, then: the "[TEST] We
  received your application…" email at your address; with staging's delivery_mode
  'graph' a "[TEST] TEST submission <time>" list item and its five PDFs under
  TEST / Ontario / … in the library (with 'email', a "[TEST] Application — …" relay
  email); "test": true in staging's submissions_dir/<id>/submission.json. Counts toward
  5 applications per hour per IP. After Oct 15 + 15 min staging answers 403 (its gate),
  unless its config.php sets closes_at ''. Also check the contact form once
  (`--contact`, "[TEST] Contact form — TEST Contact" in contact_to's inbox). The script
  refuses alumoimpact.ca (production gets no test submissions) and september. (old code,
  nothing marked); the NAS marks only once it is redeployed from main.
- Other copies that would still accept applications after the close: the NAS preview
  (impactfund.wareham.stream, redeployed from main) picks up the committed default once
  this is on main — unless its own config.php sets closes_at. The old ~/september docroot
  still runs the Aug 31 september-preview apply.php (no close gate) with its own
  config.php, and it is retired now (the closed. copy replaces it). FIRST: open
  ~/september/api/config.php and find submissions_dir — the default is
  ~/september/_submissions, INSIDE the folder about to be deleted; if anything there is
  real or not yet delivered, download it first. Remove any cPanel → Cron Jobs line that
  contains /september/, the september-preview repository in cPanel → Git Version
  Control (then delete its clone folder, or a deploy from it would recreate
  ~/september), and the september DNS record if DNS is elsewhere (Cloudflare). THEN
  delete the september.alumoimpact.ca subdomain AND the ~/september docroot (that removes
  the old ungated apply.php and its config.php) — with the subdomain setup above, and no
  later than Tue Oct 13, BEFORE the close — then probe
  september.alumoimpact.ca/api/apply.php the same day: expect 404 or no answer, never
  422. The september-preview branch and its .cpanel.yml lines (IS_SEPT, the branch
  check, the sed at line 73) become unused; .cpanel.yml is deliberately NOT edited for
  this release — remove them in the January cleanup below.
  Oct 16: probe every backend (production, staging, NAS) — 403 or 503 — and
  september — 404 / gone. before./open./closed. share staging's api/, so staging's probe
  covers them. Then, before anyone deletes TEST items in the library: list staging's
  submissions_dir archives with "test": true and check each primary_email is a known
  tester — open. showed a working form until the close, so a real applicant who got
  that link was marked TEST (their archive is still there); pass any such one to Alumo.
- Retry cron: the only recorded line points at /home/aseqhosting/…, but the account got a
  new username on 2026-08-28. Check cPanel → Cron Jobs and fix the path (affects
  application retries NOW).
- Release day: check Cache-Control / cf-cache-status on /apply-now/ and main.js?v=8;
  purge Cloudflare if HTML is cached. Repeat just after midnight Oct 16.

Timeline: build Oct 6–7 → staging + 403 test + the before./open./closed. subdomains Oct 7–8 →
Hafsa reviews by Fri Oct 9 (Mon Oct 12 is Thanksgiving) with ONE link per language that
she can click around in: https://closed.alumoimpact.ca/ and
https://closed.alumoimpact.ca/fr/ (plain links, nothing to expire; the banner says
it's a preview copy; on a phone the banner covers the FR hero pill until she
scrolls), plus https://open.alumoimpact.ca/fr/ for the open-card French of item 10,
which the closed copy never shows → release Tue Oct 13 + delete the ~/september docroot
and subdomain by the same day (server checklist) → check production after 12:15 am
Oct 16 and probe every backend.
RELEASE RULE (2026-10-08): the winners page is ON main since 82d2ebb (so staging shows it), but
Tuesday's release is the CLOSING WORK ONLY: release = 138e259 (close + UMSU hotfix, tag
close-release-base) plus cherry-picks of any later close-only fixes (Hafsa's) — NOT main's tip,
and never `git merge main` into release before the winners go-live. Build it on a branch from
138e259, check it (verify_integrity, test_schedule_preview, CI), then fast-forward release to it.
The winners code reaches release later, with its own go-live (Alumo's PDFs, deadline, folder).

Rollback: wrong text → revert on main, staging, release. Deadline extended / closed too
early → set closes_at in production config.php (overrides the committed default;
live within about a minute — host OPcache — so confirm with the probe), give applicants
/apply-now/?preview-form=1, then fix the HTML dates and DEFAULT_CLOSES_AT and release.

January 4, 2027 reopen (December job, do not build now): new dates on all 12 scheduled
elements (-05:00) + DEFAULT_CLOSES_AT in api/apply.php and closes_at in
config.example.php (check 6 fails until all three agree), Winter open-card copy (incl. the
11:59 pm line), after-Mar-1 closed copy (pill, closed cards, both notes, errClosed),
server opens_at / closes_at, Winter partner list in schools-data.js, Fall 2027 dates
from Alumo, decide whether Winter applications get their own SharePoint level, drop
the september-preview lines from .cpanel.yml (IS_SEPT, the branch check, the sed at
:73) and delete the september-preview branch. The before./open./closed. copies need
nothing: they read the new dates from the pages. _tools/test_schedule_preview.py DOES: it
hardcodes the Fall 2026 window (CLOCK_*, PROBE_OPENS — between CLOCK_BEFORE and the
opening — AFTER/AFTER_TEXT, LAST_SECOND_TEXT and OPENS_TEXT — the closed-/before-copy
banner checks use them — and the boundary URLs in build_plan), so move those to the
Winter window with the new dates or many checks fail.

## Winners page (Fall 2026) — PLAN 2026-10-06 (superseded: built — see "Winners page — BUILT" below)
From Hafsa's mock (OneDrive: Documents/Alumo/Winners page.html) — used for content and
order only; built in the site's own style. Decided: payee is the INDIVIDUAL winner;
the finance form downloads at the top; Alumo owns the tax wording (used verbatim,
their finance team reviews it); SharePoint permissions are Alumo's; Hafsa approves the
French; access is a plain unlisted link Alumo emails (secret-key layer PARKED — see below).

- URLs: /winners-fall-2026/ and /fr/gagnants-automne-2026/. noindex,nofollow, not in
  nav/sitemap; language switcher between the two only. verify_integrity.py gets a
  PRIVATE_PAGES list (asset versions + forbidden words still checked).
- Page, top to bottom: H1 + intro + deadline line → finance form download card →
  tax information block → form: school (frozen Fall 2026 list, see below, + "not
  listed" text box), full name ("as it appears on your void cheque"), project title,
  email (for the confirmation) → uploads: signed funding agreement, completed
  finance form, void cheque → confirm checkbox (accurate + account in my name) →
  privacy line linking /privacy-policy/ → honeypot → submit → success panel.
- Native file inputs styled like the application form (accessible, reuses
  apply-form patterns), not the mock's drag-and-drop zones.
- School list: js/winners-schools-fall-2026.js, built once on Oct 16 from every version
  of js/schools-data.js live Aug 31–Oct 15 (git log --since=2026-08-31
  --until=2026-10-15T23:59:59-04:00), so Regina etc. are still there and the January
  Winter list can't change it. The page never loads schools-data.js.
- Files: pdf, docx, jpg, png (drop .doc unless the finance form is .doc), 10 MB each;
  HEIC gets a friendly "save as JPG" error. Server checks real content (image →
  getimagesize, PDF → %PDF- header, docx → zip).
- api/winners.php (new), reusing _lib.php helpers; archives under
  submissions_dir/winners/<id>/ (the old redeliver glob can't see it); deadline via its
  own closes_at (offset required, fails closed); rate-limit counts only real attempts.
- SharePoint: Winners / Fall 2026 / "<Full name> - <Project title>", files named
  "Funding agreement - <Name>.pdf", "Finance form - …", "Void cheque - …". A second
  submission NEVER goes into the existing folder: it gets a suffixed sibling folder and
  the team email says "SECOND SUBMISSION — confirm banking details with the winner before
  paying". Staging uses the same root_folder; test copies file under TEST / <root_folder>
  automatically.
- Never by email: delivery is Graph only. If delivery_mode isn't 'graph' the endpoint
  answers "not ready" rather than storing banking/SIN files on the server indefinitely.
  Winner gets a confirmation email and the team a notice — both without attachments.
  The server copy is deleted once SharePoint confirms; redeliver.php gets a 'winners' branch.
- CI: forms-smoke cases (happy path, missing file, wrong type, HEIC, too big, after
  deadline, and an email-mode container asserting no attachment is ever sent).
- Staging test: dummy files only ("VOID SAMPLE"), iPhone + Android photos, PDF, too big,
  wrong type, after deadline.
- Still need from Alumo: the finance form file (EN, and FR if it exists); is the funding
  agreement also downloadable on the page or already in the winner email; the winners'
  deadline; which inbox gets "documents received" notices.
- Effort: about 3–4 dev-days (page EN+FR, JS, backend, CI, staging tests).

PARKED (user, 2026-10-06 — "later"): secret key in the winners link and making the
GitHub repo private. Findings to pick up then: the key must live only in server
config.php; the FR/EN switcher must carry it; going private needs a read-only SSH deploy
key on BOTH cPanel clones first; the GHCR image ghcr.io/ty1999ler/impactfund is PUBLIC and
can't be made private again (stop docker-publish or delete the package); the NAS preview
is a public copy of the site; partner contact names sit in the public repo history.

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

## Winners page — BUILT 2026-10-06, rebased onto the close build 2026-10-08 (not deployed)
Built on its own worktree branch per the "Winners page (Fall 2026)" plan (secret-key
layer still PARKED: the page is a plain unlisted URL). Rebased onto the Fall 2026 close
build (5e7b66e); ships after it. Asset ?v= synced with the live pages (main.js v8,
apply-now.css v9); verify_integrity.py check P fails if they drift apart again.
- Pages: /winners-fall-2026/ + /fr/gagnants-automne-2026/ (noindex, not in nav or
  sitemap, switcher links the pair), css/pages/winners.css, js/winners-form.js.
- School list: js/winners-schools-fall-2026.js, generated by
  _tools/freeze_winner_schools.py (126 schools today) — RERUN ONCE ON OCT 16.
- Backend: api/winners.php + an append-only block at the end of api/_lib.php;
  api/redeliver.php retries submissions_dir/winners/*/ through Graph only; config
  'winners' block documented in api/config.example.php (CI: config.ci.php).
- Safety rails: closed ("not ready") unless delivery_mode is 'graph' with
  credentials; closes_at REQUIRED (a real ISO date with offset, or 'none' — empty or
  missing keeps the page closed); no email ever carries a document; uploads go by the
  folder's item id, so a retry never re-creates a moved/deleted Winners folder;
  create_root false → Alumo must create and restrict root_folder first.
- SECOND SUBMISSION: flagged when an earlier record this round has the same name
  (case/accents/spacing/punctuation/word order and Cyrillic/Greek look-alikes ignored)
  — whatever the title — and when the "<name> - <title>" folder already exists (that
  one also gets a sibling "(2 - id)" folder). The team notice lists the earlier ids.
- school_listed is checked on the server against js/winners-schools-fall-2026.js.
- Winner confirmation: name/title dropped if they look like a link, address or domain
  or run long (falls back to "Hi," / "for your project" — FR "Bonjour," / "pour votre
  projet", NEW French for Hafsa); at most 30 confirmations a day site-wide.
- No js/analytics.js on either winners page (verify_integrity.py fails if it is added).
- [TEST] marking (2026-10-08, the close build's site_is_test / test_prefix): from a test
  host every winners subject gets "[TEST] " (team notice, EN/FR confirmation, failure
  notice, filed-after-retry), documents go under TEST / <root_folder> / <round> / … (with
  create_root false, TEST / <root_folder> must exist — create it by hand in the same
  drive, restricted like <root_folder>. Keep create_root false while the winners drive is
  Alumo's library, as it is on staging (winners.drive_id empty = graph.drive_id): a
  folder the site creates inherits the library's open permissions, and an unmarked
  staging submission would create the real <root_folder> that way. Set it true only with
  a separate staging winners.drive_id, plus 'test_mode' => true), and submission.json
  stores "test" (the retry reads it back). Test and real archives are never each other's
  earlier submission. The 30-a-day confirmation cap counts test confirmations
  separately. Live hosts unchanged apart from "test": false.
- BEFORE this reaches staging: `ls <submissions_dir>/winners/*/DELIVERY-PENDING` there
  should list nothing (the pre-[TEST] build 0017fbc was never deployed). An archive
  without "test" retries as REAL, into <root_folder> / … — delete the marker of any that
  is only a test.
- Preview copies: the pinned before./open./closed. copies and ?preview-at= do NOT apply
  to the winners page. Its open/closed card follows the server (api/winners.php?state=1,
  i.e. staging's own winners config), but main.js still shows the copy's site-wide
  banner over it (e.g. "after submissions close" on closed.). Test it on
  staging.alumoimpact.ca. (Optional, at the next main.js bump: skip the banner when the
  page has [data-winners-open].)
- One-command test: `python _tools/submit_test.py --winners --email you@example.com`
  (staging by default; refuses production and september.; --dry-run).
- CI: forms-smoke.yml job "winners" (3 containers on 8791-8793, clear of the smoke
  job's 8777-8783; ci/fake-sendmail.sh's /tmp/mail.all holds every message), including
  a Host: staging.alumoimpact.ca case for the [TEST] subjects, "test": true and the
  test/real second-submission separation. verify_integrity.py: PRIVATE_PAGES +
  AWAITING_CLIENT (finance form PDFs) + shared-asset ?v= check against the live pages,
  alongside the close build's check 6.
- Integration notes (rebase onto the close build, 2026-10-08):
  - api/config.ci.php: the winners block deliberately has NO closes_at key; CI inserts
    its deadline after the winners-ci-closes-at marker. The close build's grace step
    and "no closes_at key" step now match only the TOP-LEVEL key (4-space indent:
    ^    'close_grace_minutes', ^    'closes_at'), since the winners block has its own.
  - api/config.example.php: keep the winners closes_at '' (a config copied from it
    keeps the page closed). Check 6 reads only the top-level closes_at there (4-space
    indent), so a winners example date can't be taken for the application deadline.
  - ci/fake-sendmail-all.sh is gone: the winners job uses the close build's
    ci/fake-sendmail.sh (+ /tmp/mail.all) and ci/mail-subjects.sh.
  - Release gate: `python _tools/verify_integrity.py --release` must pass before main
    → release — it fails while the finance form PDFs are AWAITING_CLIENT. If Alumo has
    no French form, point the FR button at the EN PDF and add the "le formulaire est en
    anglais" line (FR_DRAFTS.md point 4) instead of shipping a dead -fr.pdf link.
- Still needed: Alumo's finance form files (assets/docs/winners-finance-form-en.pdf /
  -fr.pdf), the deadline, notify_to inbox, Hafsa's French sign-off, finance review of
  the tax block, staging tests with real phones and SharePoint (incl. TEST / <root_folder>
  on staging's drive).
