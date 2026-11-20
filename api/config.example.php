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
    'closes_at' => '2026-10-22T23:59:59-04:00',
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

        /* Round-first folder layout (Nov 2026). With round set, applications
           file under  <round> / Projects / Region / Project title - School /
           — the round folder that also holds that round's Payments and
           Completion report folders (the winners and completion blocks
           below). Left '' they file under  Region / Project title - School /
           at the top of the library, where Fall 2026's applications are.
           SET IT WHEN A NEW ROUND OPENS (e.g. 'Spring 2027'), and change it
           at each round after. projects_folder renames the "Projects" level.
           With round set, each application's ROW also gets a "Round" value —
           so the list (list_id above) must have a single-line-of-text column
           named Round BEFORE this is set: SharePoint refuses a row naming a
           column it does not have, and applications would queue for retry. */
        'round'           => '',
        'projects_folder' => 'Projects',

        /* Creating lists/columns needs more than Sites.Selected, so the target
           list schema often can't be changed. Map our canonical field names
           onto the columns that actually exist; map to '' to fold a field into
           overflow_field instead. Leave field_map empty to send names as-is. */
        'field_map' => [],
        'overflow_field' => '',

        /* Hyperlink ("Link") columns, filled after the uploads finish. Files
           land in  [<round> / Projects /] Region / Project title - School /
           inside drive_id;
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
       Winners upload a signed funding agreement and a void cheque, and say
       who the funds are sent to (the finance form was dropped in Nov 2026).
       They are filed in SharePoint ONLY — never emailed, never kept on
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
       Documents land in
         <round> / <root_folder> / <Region> / <Project title> - <School> /
       e.g.  Fall 2026 / Payments / Ontario / Campus Pantry - Algonquin College /
       (round-first layout, Nov 2026) as "Funding agreement - <Name>.pdf" and
       "Void cheque - …", with a "Submission details - <Name>.txt" holding
       what was typed (name, email, school, project title, who the funds are
       sent to). A second copy of the void cheque goes in
         <round> / <root_folder> / All Void /
       named "Void cheque - <Project title> - <School>.<ext>".
       <Region> is one of the applications' four — Ontario, West,
       Quebec (FR), Quebec (EN) & East — worked out from the school the
       winner picks; a school that is not on the list files under "Other".
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
       TEST / <round> / <root_folder> / … — so with create_root false,
       TEST / <round> / <root_folder> has to exist in that drive too — and "test": true
       in submission.json, which the retry reads back. A test submission
       never counts as an earlier submission of a real winner, nor the
       reverse. */
    'winners' => [
        'round'       => 'Fall 2026',   // SharePoint level 1: the round's folder; next round: 'Spring 2027'
        'root_folder' => 'Payments',    // SharePoint level 2, inside the round (was 'Winners' at level 1 until Nov 2026)
        'opens_at'    => '',            // '' = open as soon as configured
        'closes_at'   => '',            // REQUIRED, e.g. '2026-10-30T23:59:59-04:00'; 'none' = no deadline; '' = page stays closed
        /* Submissions only: an upload already on its way at closes_at is
           still accepted for this many minutes. The page shows "closed" from
           closes_at exactly. */
        'close_grace_minutes' => 15,
        'notify_to'   => '',            // "documents received" notices; '' = failure_notify_to, then relay_to
        /* The date in the page's orange note ("…our team will start
           processing your payment the week of January 11, 2027"): the Monday
           of that week, as 'YYYY-MM-DD'. The page writes it out in English
           and French. '' (or not a real date) = the page keeps the date
           typed into it. */
        'payment_week' => '',           // e.g. '2027-01-11'
        /* A row per submission in a SharePoint list (same site as the
           applications' list: graph.site_id): the list's NAME as Alumo
           created it, or its id. '' = no row. One permanent list, every round
           in it — the row carries Round. Alumo creates the list and these
           columns, named exactly so when created (no spaces; the display
           name can be changed afterwards):
             Title (there already) = project title    Round, Region, School,
             FullName, Email, FundsSentTo, SubmissionId: single line of text
             Received: date and time                  Folder: hyperlink
           and any of their own (Status, Paid on, Notes…), which the site
           never touches. The row is added after the documents are filed and
           never blocks them: if the list or a column is missing, the
           documents are filed, a column the list lacks is left out of the
           row, and the team notice says what to add by hand. No banking
           detail goes in a row. list_field_map renames a column
           ('FundsSentTo' => 'Payee') or drops it ('Email' => ''). */
        'list'           => '',         // e.g. 'ImpactFundPayments'
        'list_field_map' => [],
        /* false (default; anything but true counts as false): NO email at all
           to the winner's address, and the page doesn't mention one. true:
           the winner gets a confirmation (EN/FR, at most 30 a day) and the
           page says one is on its way. The team notice goes either way.
           Fall 2026: false (Alumo asked for a simple page, 2026-10-08). */
        'send_confirmation' => false,
        'drive_id'    => '',            // '' = the applications library (graph.drive_id)
        /* false (default): <round> / <root_folder> must ALREADY exist in the
           drive (the round folder itself is created when missing) —
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

    /* ---- Project completion page (api/completion.php;
       /project-completion-fall-2026/ and /fr/fin-de-projet-automne-2026/) ----
       A winner whose project is done uploads a completion report (one file),
       receipts (1 to 20 files) and photos (0 to 10 files), and says whether
       the photos may be shared. Same rails as the winners page: SharePoint
       ONLY, never emailed, server copies deleted once filed, "[TEST] "
       marking from a test copy of the site, the same retry cron. Nothing is
       emailed to the sender; the team gets a notice without attachments.
       The page stays CLOSED ("not ready", 503) without this block, and under
       the winners block's rules: delivery_mode 'graph' with credentials and a
       drive id, a round and a root_folder, and a deadline — a real ISO date
       WITH an explicit offset, or 'none'. (Left '' or out, the page stays
       closed; it stays '' in THIS file on purpose.)
       round, notify_to, drive_id and create_root DEFAULT TO THE WINNERS
       BLOCK'S VALUES when a key is left OUT of this block (a key that is
       present, even empty, is this block's own). root_folder is NOT
       inherited: it defaults to 'Completion report'. So the smallest
       working block is the deadline line alone, set to 'none' or a date.
       Documents land in
         <round> / <root_folder> / <Region> / <Project title> - <School> /
       e.g.  Fall 2026 / Completion report / West / Campus Pantry - Douglas College /
       (round-first layout, Nov 2026; regions as for the winners block) as
       "Completion report - <Name>.pdf", "Receipt 1 - …", "Receipt 2 - …",
       "Photo 1 - …" and a "Submission details - <Name>.txt". A sender who
       answers "no" to "Can we share your photos?" gets " - DO NOT SHARE" on
       the end of that folder's name. With create_root false,
       <round> / <root_folder> must already exist (and TEST / <round> /
       <root_folder> for test copies of the site). A second submission for
       the same name and project gets a sibling "… (2 - <id>)" folder and a
       "SECOND SUBMISSION" notice. */
    'completion' => [
        // 'round'       => 'Fall 2026',  // left out = the winners block's round
        'root_folder' => 'Completion report',   // SharePoint level 2, inside the round; left out = 'Completion report'
        'opens_at'    => '',            // '' = open as soon as configured
        'closes_at'   => '',            // REQUIRED: a date like the winners block's, or 'none' = no deadline; '' = page stays closed
        'close_grace_minutes' => 15,
        /* As winners.list, for reports (NOT inherited from the winners
           block). Columns: Title = project title; Round, Region, School,
           FullName, Email, SubmissionId: single line of text; Photos:
           number; OkToShare: single line of text ("Yes", "No", "No
           photos"); Attested: single line of text ("Jane Doe on December 1,
           2026"); Received: date and time; Folder: hyperlink. */
        'list'           => '',         // e.g. 'ImpactFundCompletionReports'
        'list_field_map' => [],
        // 'notify_to'   => '',         // left out = the winners block's notify_to
        // 'drive_id'    => '',         // left out = the winners block's drive
        // 'create_root' => false,      // left out = the winners block's setting
    ],
];
