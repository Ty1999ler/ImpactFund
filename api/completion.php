<?php
/* Project completion page endpoint — /project-completion-fall-2026/ and
   /fr/fin-de-projet-automne-2026/. The winners page's sibling (api/winners.php)
   for the other end of a project: same request shapes, same rails, shared
   code in the winners block of _lib.php.

   GET  ?state=1  -> {ok, state, closes_at_ts, confirmation:false}. state is
                     'open' | 'closed' | 'not-open', or 'unconfigured' with a
                     503. closes_at_ts is null when the page has no deadline
                     (completion.closes_at 'none').
   POST multipart -> school (+ school_other), full_name, project_title, email,
                     photo_consent ("yes" / "no"; required only when photos
                     are sent), locale, and the documents: file_report (one),
                     file_receipts[] (1 to 10), file_photos[] (0 to 8).
   1. checks the schedule, the fields and the files' real content, then
      archives under submissions_dir/completion/<id>/ with the documents
      renamed "Completion report - <Name>.<ext>", "Receipt 2 - …",
      "Photo 1 (OK to share) - …" / "Photo 1 (do not share) - …", plus a
      "Submission details - <Name>.txt" holding what was typed;
   2. answers the sender, then files the documents in SharePoint (Microsoft
      Graph) under <root_folder>/<round>/<subfolder>/<Full name - Project title>/
      — by default Winners / <round> / Project completion / …;
   3. on success deletes the server copies; on failure leaves a
      DELIVERY-PENDING marker for api/redeliver.php and emails the team once;
   4. emails the team a notice, never with an attachment. Nothing is emailed
      to the sender: the page's success panel is the confirmation.
   Sent from a test copy of the site (site_is_test in _lib.php), every email
   subject says "[TEST]" and the documents are filed under
   TEST / <root_folder> / ….
   Errors answer {ok:false, error:<key>, message:<English>, fields:{name:<key>}};
   js/winners-form.js maps the keys onto its EN/FR copy.

   PHP 7.4-compatible, like winners.php. Configured by the 'completion' block
   in api/config.php (documented in api/config.example.php), which inherits
   the winners block's folder settings; without a valid block the endpoint
   stays closed. */

require __DIR__ . '/_lib.php';

$isStateCheck = ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && (string)($_GET['state'] ?? '') === '1';
if (!$isStateCheck) require_post();

$cfg = completion_cfg(load_config());
$w = completion_config($cfg);

/* The single gate every request passes, as in winners.php. */
if ($w === null) {
    respond(503, ['ok' => false, 'error' => 'unconfigured', 'state' => 'unconfigured',
                  'closes_at_ts' => null, 'message' => 'This page is not set up yet.']);
}

if ($isStateCheck) {
    respond(200, ['ok' => true, 'state' => winners_window_state($w, time(), false),
                  'closes_at_ts' => $w['closes_at_ts'], 'confirmation' => false]);
}

/* Up to 19 files: together they may be 50 MB, which the page checks before
   sending (TOTAL_MAX_BYTES in js/winners-form.js) and which sits inside
   api/.user.ini's post_max_size (60M). */
$maxTotalBytes = 50 * 1024 * 1024;
$tooLarge = ['ok' => false, 'error' => 'too_large',
             'message' => 'The submission is too large. Each file must be 10 MB or less, and all files together 50 MB or less.'];

/* When the request body exceeds post_max_size PHP silently delivers EMPTY
   $_POST and $_FILES — catch that before it reads as "all fields missing". */
if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    respond(413, $tooLarge);
}

honeypot_check();

/* Schedule gate BEFORE the rate limit, so refusals never eat a sender's
   budget. Submissions get close_grace_minutes past closes_at. */
$state = winners_window_state($w, time(), true);
if ($state !== 'open') {
    respond(403, ['ok' => false, 'error' => $state === 'closed' ? 'closed' : 'not_open',
                  'state' => $state, 'closes_at_ts' => $w['closes_at_ts'],
                  'message' => $state === 'closed'
                      ? 'The deadline to send your report has passed.'
                      : 'This page is not open yet.']);
}

/* ---------- fields ---------- */

/* Value of the "My school isn't listed" option; it reveals school_other. */
$SCHOOL_OTHER = '__other';
/* The same frozen Fall 2026 list the winners page uses: this round's winners
   are who completes a project. */
$SCHOOL_LIST_FILE = __DIR__ . '/../js/winners-schools-fall-2026.js';

$FIELDS = [
    // name          => [required, max length]
    'school'        => [true, 255],
    'school_other'  => [false, 255],
    'full_name'     => [true, 200],
    'project_title' => [true, 255],
    'email'         => [true, 254],
    /* "Can we share your photos?" — "yes" or "no"; checked below. */
    'photo_consent' => [false, 3],
    /* Hidden input ("en" / "fr"); recorded, nothing is emailed to the sender. */
    'locale'        => [false, 2],
];

$data = [];
$errors = [];
foreach ($FIELDS as $name => [$required, $max]) {
    $v = field($name, $max);
    if ($required && $v === '') $errors[$name] = 'required';
    $data[$name] = $v;
}
if ($data['school'] === $SCHOOL_OTHER && $data['school_other'] === '') {
    $errors['school_other'] = 'required';
}
if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'invalid_email';
}
/* Anything but an explicit yes or no is no answer. */
$consent = strtolower($data['photo_consent']);
if ($consent !== 'yes' && $consent !== 'no') $consent = '';

/* ---------- files ---------- */

/* 10 MB each, fixed: it is printed on the page ("Max. file size: 10 MB") and
   mirrored by FILE_MAX_BYTES in js/winners-form.js. */
$maxBytes = 10 * 1024 * 1024;

$docs = [];
$totalBytes = 0;
$photoCount = 0;
foreach (completion_upload_slots() as $slot => $spec) {
    $check = completion_check_slot($_FILES[$slot] ?? null, $spec, $maxBytes);
    if (isset($check['error'])) {
        $errors[$slot] = $check['error'];
        continue;
    }
    $count = count($check['files']);
    if ($slot === 'file_photos') $photoCount = $count;
    foreach ($check['files'] as $i => $file) {
        $totalBytes += $file['size'];
        $docs[] = ['slot' => $slot, 'tmp' => $file['tmp'], 'ext' => $file['ext'],
                   'label' => completion_doc_label($slot, $spec['label'], $i + 1, $count, $consent)];
    }
}
/* The question is asked about the photos, so it only needs an answer when
   there are some. */
if ($photoCount > 0 && $consent === '') $errors['photo_consent'] = 'required';

if ($errors) {
    respond(422, ['ok' => false, 'error' => 'fields', 'message' => 'Please check the highlighted fields.', 'fields' => $errors]);
}
if ($totalBytes > $maxTotalBytes) {
    respond(413, $tooLarge);
}

/* Counted only now, so a sender fixing a wrong file type is never locked out:
   the limit caps what can actually be written to the server and SharePoint. */
rate_limit('completion', 10, 3600);

/* ---------- archive on the server ---------- */

$school = $data['school'] === $SCHOOL_OTHER ? $data['school_other'] : $data['school'];
$listed = winners_school_listed($SCHOOL_LIST_FILE, $school);
$fields = [
    'school'        => $school,
    'school_listed' => $listed === null ? 'unchecked' : ($listed ? 'yes' : 'no'),
    'full_name'     => $data['full_name'],
    'project_title' => $data['project_title'],
    'email'         => $data['email'],
    'photo_consent' => $consent,
    'locale'        => $data['locale'],
];

/* Sent from a test copy of the site? Archived as 'test', as in winners.php:
   api/redeliver.php has no host and reads it back. */
$isTest = site_is_test($cfg);

$id   = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
$base = rtrim((string)$cfg['submissions_dir'], '/\\');
$dir  = $base . '/completion/' . $id;

$stored = [];
/* A half-written archive would never be delivered or deleted — so a storage
   failure takes back whatever it already wrote. */
$abandon = function () use ($dir, &$stored) {
    foreach ($stored as $s) @unlink($s['path']);
    @unlink($dir . '/submission.json');
    @rmdir($dir);
    respond(500, ['ok' => false, 'error' => 'store_failed', 'message' => 'Could not store the documents. Please try again later.']);
};

if (!is_dir($dir) && !@mkdir($dir, 0750, true)) $abandon();
/* If submissions_dir ends up inside a webroot, refuse to serve its contents. */
foreach ([$base, $base . '/completion'] as $denyDir) {
    if (!is_file($denyDir . '/.htaccess')) @file_put_contents($denyDir . '/.htaccess', "Require all denied\n");
}

foreach ($docs as $doc) {
    $name = winners_doc_name($doc['label'], $fields['full_name'], $doc['ext']);
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($doc['tmp'], $dest)) $abandon();
    $stored[] = ['slot' => $doc['slot'], 'label' => $doc['label'], 'name' => $name, 'path' => $dest];
}
$record = [
    'id'           => $id,
    'kind'         => 'completion',
    'round'        => $w['round'],
    'root_folder'  => $w['root_folder'],
    'subfolder'    => $w['subfolder'],
    'received_utc' => gmdate('c'),
    'test'         => $isTest,
    'fields'       => $fields,
    /* 'documents', NOT 'files' — see winners.php. */
    'documents'    => array_map(fn($s) => ['slot' => $s['slot'], 'label' => $s['label'], 'name' => $s['name']], $stored),
];
/* What was typed — the photo-sharing answer above all — as a text file filed
   with the uploads (winners_details_file). */
$details = winners_details_file($dir, $record);
if ($details !== null) {
    $stored[] = $details;
    $record['documents'][] = ['slot' => $details['slot'], 'label' => $details['label'], 'name' => $details['name']];
}
if (@file_put_contents($dir . '/submission.json', json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
    $abandon();
}

/* ---------- answer, then deliver ----------
   Everything the sender must not lose is on disk; filing up to 20 files in
   SharePoint takes a while, so the answer goes out first. The id is returned
   for CI and logs, not shown on the page. */
respond_and_continue(200, ['ok' => true, 'id' => $id]);
@set_time_limit(600);

$folder = [];
$deliveryError = '';
if ($w['graph_ready']) {
    try {
        winners_graph_deliver($cfg, $record, $stored, $folder);
    } catch (Throwable $e) {
        $deliveryError = $e->getMessage();
    }
} else {
    /* Only reachable with allow_without_graph (CI). Archive-only — and still
       never emailed. */
    $deliveryError = "delivery_mode is '" . ($cfg['delivery_mode'] ?? 'off')
        . "', not 'graph' — completion reports are filed in SharePoint only";
}

if ($deliveryError === '') {
    $deleted = winners_finish_delivery($dir, $stored, $folder, 'delivered', 1);
    winners_notify_delivered($cfg, $record, $folder, false, $deleted);
} else {
    error_log("completion.php: delivery failed for $id — archived, queued for retry: $deliveryError");
    winners_record_failure($cfg, $dir, $record, $w['graph_ready'] ? 'graph' : 'awaiting-graph', $deliveryError, $folder);
}
