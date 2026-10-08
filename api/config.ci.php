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

    /* Winners page (api/winners.php). CI has no SharePoint, so
       allow_without_graph lets the endpoint open anyway: submissions are
       archived and queued (DELIVERY-PENDING), and still never emailed. That
       key must exist in THIS file only; it is also what lets this block go
       without a deadline. The forms-smoke workflow seds copies of this file
       for its "after the deadline" variant (it inserts a deadline after the
       marker line below — keep that marker the only one in the file) and its
       "email mode" variant (the delivery_mode line above); it fails loudly if
       either pattern stops matching.
       Deliberately NO closes_at key in this block: the "after the deadline"
       variant inserts the only one, and the application smoke test's
       closes_at seds match the top-level line by its text, so an empty
       winners closes_at would be rewritten with it (its "no closes_at key"
       step deletes and checks only the top-level line, by its 4-space
       indent). */
    'winners' => [
        'round'       => 'CI Round',
        'root_folder' => 'Winners (CI)',
        'opens_at'    => '',
        // winners-ci-closes-at (the "after the deadline" variant inserts its deadline after this line)
        'close_grace_minutes' => 15,
        'notify_to'   => 'winners-ci@example.invalid',
        'drive_id'    => '',
        'allow_without_graph' => true,
    ],
];
