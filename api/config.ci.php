<?php
/* CI-ONLY config — committable (no secrets, every address is a dummy).
   The forms-smoke workflow mounts this file over api/config.php inside the
   test container. Never use it in a real deployment. */
return [
    'contact_to'   => 'contact-ci@example.invalid',

    'mail_from'    => 'ci@example.invalid',
    'mail_from_name' => 'CI Smoke Test',

    'mail_bcc'     => [],

    /* Matches the directory Dockerfile.php creates (owned www-data). */
    'submissions_dir' => '/var/www/_submissions',

    'max_file_mb'  => 10,

    /* Empty = submissions always open, so CI can exercise apply.php. */
    'opens_at' => '',

    'trust_cloudflare_header' => false,

    /* Archive on disk only — no email relay, no Graph calls from CI.
       (contact.php still uses PHP mail(); the workflow fakes sendmail.) */
    'delivery_mode' => 'off',

    'relay_to' => 'applications-ci@example.invalid',

    'graph' => [
        'tenant_id'     => '',
        'client_id'     => '',
        'client_secret' => '',
        'site_id'       => '',
        'list_id'       => '',
        'drive_id'      => '',
    ],
];
