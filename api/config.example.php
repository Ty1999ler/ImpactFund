<?php
/* Copy this file to api/config.php and fill in real values.
   api/config.php is git-ignored — never commit it (the repo is public). */
return [
    /* Where contact-form messages are delivered. */
    'contact_to'   => 'team@example.com',

    /* Envelope/From for all outgoing mail. Use an address on the site's own
       domain so GoDaddy's SPF passes (e.g. admin@alumoimpact.ca). */
    'mail_from'    => 'admin@alumoimpact.ca',
    'mail_from_name' => 'Student Impact Fund',

    /* Blind copies added to every form email (contact + application relay). */
    'mail_bcc'     => [],

    /* ---- Outgoing mail transport ----
       Leave 'host' EMPTY to use PHP mail() (the local server MTA).
       Set it to send through authenticated SMTP instead — strongly preferred:
       alumoimpact.ca publishes DMARC p=quarantine with no SPF record, so mail
       sent from the web host as @alumoimpact.ca fails alignment and lands in
       junk. Sending via the domain's real provider fixes that.

       Microsoft 365:  host smtp.office365.com, port 587, encryption 'tls'
       Google Workspace: host smtp.gmail.com,   port 587, encryption 'tls'

       'username' is the full mailbox address. For M365 the mailbox must have
       SMTP AUTH enabled (Exchange admin -> mailbox -> manage email apps), and
       'mail_from' should be that same address (or one it may Send As), or the
       provider will reject the envelope sender. */
    'smtp' => [
        'host'       => '',
        'port'       => 587,
        'encryption' => 'tls',   // 'tls' = STARTTLS (587) | 'ssl' = implicit (465) | '' = none
        'username'   => '',
        'password'   => '',
        'timeout'    => 20,
    ],

    /* Where application submissions (JSON + uploaded files) are archived.
       On GoDaddy point this ABOVE the webroot, e.g. dirname(__DIR__, 2).'/impactfund-submissions'.
       The directory is created on first use. */
    'submissions_dir' => dirname(__DIR__) . '/_submissions',

    /* Per-file upload cap for the application form, in MB.
       Must match FILE_MAX_BYTES in js/apply-form.js and api/.user.ini. */
    'max_file_mb'  => 10,

    /* Applications are rejected server-side before this moment (ISO date with
       timezone). Empty string = always open. */
    'opens_at' => '2026-09-01T00:00:00-04:00',

    /* Applications are rejected server-side (403 "Submissions are now
       closed.") from closes_at + close_grace_minutes onward. ISO date WITH an
       offset: Eastern is -04:00 until Nov 1, -05:00 after. Only the empty
       string '' = never closes; leave the key OUT (or null) to use
       DEFAULT_CLOSES_AT committed in api/apply.php. Must equal data-closes-at
       in the HTML pages (verify_integrity.py check 6 compares this line too):
       the pages only change what is visible, this is the actual gate. Any
       other value that isn't an ISO date with an offset — false included —
       REFUSES every submission (fail closed, logged), so after any edit run
       the safe probe (stores nothing):
         curl -sS -w "\n%{http_code}\n" -F primary_first_name=probe https://<host>/api/apply.php
       422 = open, 403 = closed.
       close_grace_minutes keeps accepting briefly after closes_at for uploads
       already in flight (15 when the key is absent). */
    'closes_at' => '2026-10-15T23:59:59-04:00',
    'close_grace_minutes' => 15,

    /* Test submissions. Leave the key OUT for automatic: a form sent from a
       test copy of the site — staging., before., open., closed. and
       september.alumoimpact.ca, impactfund.wareham.stream (a leading www.
       ignored) — gets "[TEST] " in front of every email subject (team relay,
       applicant acknowledgement, failure notice, contact form) and of the
       SharePoint list item's Title, and its documents go under a top-level
       "TEST" folder (TEST / Region / Title - School). Recorded as "test" in
       submission.json, so api/redeliver.php keeps the marks on a retry.
       The Winners page (api/winners.php) is marked the same way — see the
       'winners' block at the end of this file.
       Production (alumoimpact.ca, www.) and any other host are never marked.
       true / false forces it either way (booleans only; anything else counts
       as absent) — on every host except alumoimpact.ca / www., which stay
       unmarked even with true (so a staging config copied to production is
       harmless). true on staging and the NAS also marks what reaches them by
       another name (the NAS's LAN address, say). One-command test
       submission: python _tools/submit_test.py */
    // 'test_mode' => true,

    /* Set true ONLY when the origin is reachable exclusively through
       Cloudflare — then rate limiting keys on the CF-Connecting-IP header.
       When false (default) the direct peer IP is used. */
    'trust_cloudflare_header' => false,

    /* How application submissions reach the team. One of:
       'email' — mail the fields + attachments to sharepoint.relay_to
                 (a mailbox; optionally a standard-tier Power Automate flow
                 files attachments into SharePoint from there)
       'graph' — write directly to SharePoint via Microsoft Graph
                 (fill in the app-registration values below)
       'off'   — archive on the server only (submissions_dir)          */
    'delivery_mode' => 'email',

    'relay_to' => 'applications@example.com',

    /* Where delivery-failure notices (and their delivered-after-retry
       confirmations) are sent. Optional — leave '' to use relay_to.
       A failed delivery never bounces the applicant: the submission is
       archived first, ONE notice is emailed here, and a DELIVERY-PENDING
       marker queues it for api/redeliver.php (CLI-only), which retries
       until it goes through. Install the retry cron on the host
       (cPanel -> Cron Jobs); the line, with <cpanel-user> = the account's
       CURRENT username (it changed on 2026-08-28, so check any existing
       entry against the home directory cPanel shows): */
    // */30 * * * * /usr/local/bin/php /home/<cpanel-user>/public_html/api/redeliver.php >/dev/null 2>&1
    'failure_notify_to' => '',

    /* Acknowledgement email sent to the APPLICANT after a submission (added
       Aug 2026). Defaults to true when the key is absent, so existing configs
       keep working. Language follows the form's hidden 'locale' input, so the
       French form gets the French wording. Sending failures are swallowed on
       purpose — the submission is already archived and must never be reported
       as failed because an acknowledgement bounced. */
    'applicant_ack' => true,

    /* Only needed for delivery_mode = 'graph' (Entra app registration with
       Sites.Selected on the target site, client-credentials flow). */
    'graph' => [
        'tenant_id'     => '',
        'client_id'     => '',
        'client_secret' => '',
        'site_id'       => '',   // Graph site id of the SharePoint site
        'list_id'       => '',   // list that receives one item per submission
        'drive_id'      => '',   // document library drive for the files

        /* Creating lists/columns needs more than Sites.Selected, so the target
           list schema often can't be changed. Map our canonical field names
           onto the columns that actually exist; map to '' to fold a field into
           overflow_field instead. Leave field_map empty to send names as-is. */
        'field_map' => [],
        'overflow_field' => '',

        /* Hyperlink ("Link") columns, filled after the uploads finish. Files
           land in  Region / Project title - School /  inside drive_id;
           link_field points at that folder, file_links at one document each.
           Leave a column name out and that link is simply not written. */
        'link_field' => '',      // e.g. 'Documents'
        'file_links' => [
            // 'file_project_overview' => 'ProjectOverview',
            // 'file_budget'           => 'Budget',
            // 'file_team_members'     => 'TeamMembers',
            // 'file_action_plan'      => 'ActionPlan',
            // 'file_support_letter'   => 'SupportLetter',  // "Partner Sign-off Form" on the site
        ],
    ],

    /* ---- Winners page (api/winners.php; /winners-fall-2026/ and
       /fr/gagnants-automne-2026/) ----
       Winners upload a signed funding agreement, a finance form and a void
       cheque. They are filed in SharePoint ONLY — never emailed, never kept on
       the server once SharePoint has them. The page stays CLOSED ("not ready",
       503) unless all of this holds:
         - delivery_mode above is 'graph', with graph tenant_id, client_id,
           client_secret, and a drive id (drive_id here, else graph.drive_id);
         - round and root_folder are set;
         - closes_at is set: a real ISO date WITH an explicit offset, or
           'none' for no deadline at all. Left empty or out, the page stays
           closed — a page that takes banking documents must never stay open
           for good because a key was forgotten. (It stays '' in THIS file on
           purpose, so a config copied from here keeps the page closed. The
           integrity check reads only the top-level closes_at above.);
         - opens_at is '' or such a date. -04:00 is EDT, -05:00 is EST: a
           deadline after Nov 1, 2026 needs -05:00. A value without an
           offset, or one that isn't a real date (2026-11-31), closes the page
           (logged) rather than guessing.
       Documents land in  <root_folder>/<round>/<Full name> - <Project title>/
       as "Funding agreement - <Name>.pdf", "Finance form - …", "Void cheque - …".
       A second submission for the same name never goes into the existing
       folder: it gets a sibling "… (2 - <id>)" folder, and the team notice
       starts "SECOND SUBMISSION — confirm banking details with the winner
       before paying". No SharePoint list item is created.
       Each submission emails notify_to (else failure_notify_to, else
       relay_to) a notice WITHOUT attachments — and the winner a confirmation
       whose replies go to notify_to, but only with send_confirmation true
       (below; off by default).
       Sent from a test copy of the site (test_mode above; automatic on
       staging and its preview copies and the NAS): "[TEST] " in front of
       every winners email subject (team notice, confirmation, failure and
       filed-after-retry notices), documents under
       TEST / <root_folder> / <round> / … — so with create_root false,
       TEST / <root_folder> has to exist in that drive too — and "test": true
       in submission.json, which the retry reads back. A test submission
       never counts as an earlier submission of a real winner, nor the
       reverse. */
    'winners' => [
        'round'       => 'Fall 2026',   // SharePoint level 2; next round: 'Winter 2027'
        'root_folder' => 'Winners',     // SharePoint level 1 (test copies of the site: under TEST /)
        'opens_at'    => '',            // '' = open as soon as configured
        'closes_at'   => '',            // REQUIRED, e.g. '2026-10-30T23:59:59-04:00'; 'none' = no deadline; '' = page stays closed
        /* Submissions only: an upload already on its way at closes_at is
           still accepted for this many minutes. The page shows "closed" from
           closes_at exactly. */
        'close_grace_minutes' => 15,
        'notify_to'   => '',            // "documents received" notices; '' = failure_notify_to, then relay_to
        /* false (default; anything but true counts as false): NO email at all
           to the winner's address, and the page doesn't mention one. true:
           the winner gets a confirmation (EN/FR, at most 30 a day) and the
           page says one is on its way. The team notice goes either way.
           Fall 2026: false (Alumo asked for a simple page, 2026-10-08). */
        'send_confirmation' => false,
        'drive_id'    => '',            // '' = the applications library (graph.drive_id)
        /* false (default): root_folder must ALREADY exist in the drive —
           Alumo creates it with restricted permissions before the link goes
           out, and until it exists deliveries queue for retry (one notice to
           the team). A folder the site created would inherit the library's
           permissions, i.e. everyone who reviews applications. Set true only
           where that doesn't matter (e.g. a separate staging library) —
           never while the drive is Alumo's applications library, as it is
           on staging when drive_id is ''. */
        'create_root' => false,
        /* NEVER set on a server. config.ci.php only: lets CI exercise the
           endpoint without SharePoint (archive-only, still never emailed). */
        // 'allow_without_graph' => true,
    ],
];
