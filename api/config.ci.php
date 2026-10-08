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

    /* Empty = never closes (an explicit '' also overrides the default close
       date committed in api/apply.php). forms-smoke.yml derives closed copies
       of this file with sed, so keep this line exactly as written. */
    'closes_at' => '',
    'close_grace_minutes' => 15,

    'trust_cloudflare_header' => false,

    /* No 'test_mode' key on purpose: automatic, and localhost is not a test
       host, so CI submissions stay unmarked unless the request names one
       (forms-smoke.yml sends some with curl -H 'Host: staging.alumoimpact.ca').
       For one container forms-smoke.yml inserts test_mode true after the
       delivery_mode line below with sed, so keep that line exactly as
       written. */

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
