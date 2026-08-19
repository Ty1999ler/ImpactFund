<?php
/* Copy this file to api/config.php and fill in real values.
   api/config.php is git-ignored — never commit it (the repo is public). */
return [
    /* Where contact-form messages are delivered. */
    'contact_to'   => 'team@example.com',

    /* Envelope/From for all outgoing mail. Use an address on the site's own
       domain so GoDaddy's SPF passes (e.g. noreply@alumoimpact.ca). */
    'mail_from'    => 'noreply@alumoimpact.ca',
    'mail_from_name' => 'Student Impact Fund',

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

    /* Only needed for delivery_mode = 'graph' (Entra app registration with
       Sites.Selected on the target site, client-credentials flow). */
    'graph' => [
        'tenant_id'     => '',
        'client_id'     => '',
        'client_secret' => '',
        'site_id'       => '',   // Graph site id of the SharePoint site
        'list_id'       => '',   // list that receives one item per submission
        'drive_id'      => '',   // document library drive for the files
    ],
];
