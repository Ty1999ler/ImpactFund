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
       (cPanel -> Cron Jobs); the exact line: */
    // */30 * * * * /usr/local/bin/php /home/aseqhosting/public_html/api/redeliver.php >/dev/null 2>&1
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
];
