<?php
/* Winners page endpoint — /winners-fall-2026/ and /fr/gagnants-automne-2026/.

   GET  ?state=1  -> {ok, state, closes_at_ts}. state is 'open' | 'closed' |
                     'not-open', or 'unconfigured' with a 503. The page asks on
                     load which card to show; closes_at_ts (epoch seconds or
                     null) is the deadline it prints.
   POST multipart -> school (+ school_other), full_name, project_title, email,
                     confirm, locale, and three documents: file_agreement,
                     file_finance_form, file_void_cheque.
   1. checks the schedule, the fields and the files' real content, then
      archives under submissions_dir/winners/<id>/ with the documents renamed
      "Funding agreement - <Name>.<ext>", "Finance form - …", "Void cheque - …";
   2. answers the winner, then files the documents in SharePoint (Microsoft
      Graph) under <root_folder>/<round>/<Full name - Project title>/;
   3. on success deletes the server copies; on failure leaves a
      DELIVERY-PENDING marker for api/redeliver.php and emails the team once;
   4. emails the team a notice and the winner a confirmation. Neither ever
      carries an attachment: documents never travel by email (see the winners
      block at the end of _lib.php for how that is enforced).
   Errors answer {ok:false, error:<key>, message:<English>, fields:{name:<key>}};
   js/winners-form.js maps the keys onto its EN/FR copy.

   Written PHP 7.4-compatible on purpose: the host's PHP version is not
   confirmed and CI lints with 8.3 only. Configured by the 'winners' block in
   api/config.php (documented in api/config.example.php); without a valid
   block the endpoint stays closed. */

require __DIR__ . '/_lib.php';

$isStateCheck = ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && (string)($_GET['state'] ?? '') === '1';
if (!$isStateCheck) require_post();

$cfg = load_config();
$w = winners_config($cfg);

/* The single gate every request passes — the state check and a submission
   alike — before anything about the page is revealed or accepted. Any future
   access rule for this page belongs here, in this one place. */
if ($w === null) {
    respond(503, ['ok' => false, 'error' => 'unconfigured', 'state' => 'unconfigured',
                  'closes_at_ts' => null, 'message' => 'This page is not set up yet.']);
}

if ($isStateCheck) {
    respond(200, ['ok' => true, 'state' => winners_window_state($w, time(), false),
                  'closes_at_ts' => $w['closes_at_ts']]);
}

/* When the request body exceeds post_max_size PHP silently delivers EMPTY
   $_POST and $_FILES — catch that before it reads as "all fields missing". */
if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    respond(413, ['ok' => false, 'error' => 'too_large',
                  'message' => 'The submission is too large. Each file must be 10 MB or less.']);
}

honeypot_check();

/* Schedule gate BEFORE the rate limit, so refusals never eat a winner's
   budget. Submissions get close_grace_minutes past closes_at. */
$state = winners_window_state($w, time(), true);
if ($state !== 'open') {
    respond(403, ['ok' => false, 'error' => $state === 'closed' ? 'closed' : 'not_open',
                  'state' => $state, 'closes_at_ts' => $w['closes_at_ts'],
                  'message' => $state === 'closed'
                      ? 'The deadline to send your documents has passed.'
                      : 'This page is not open yet.']);
}

/* ---------- fields ---------- */

/* Value of the "My school isn't listed" option; it reveals school_other. */
$SCHOOL_OTHER = '__other';
/* The frozen list the page's <select> is built from. school_listed is
   checked against it here — the browser's choice is never taken on trust. */
$SCHOOL_LIST_FILE = __DIR__ . '/../js/winners-schools-fall-2026.js';

/* Lengths respect SharePoint: the folder name is built from full_name and
   project_title (sp_safe_name caps it at 100), and nothing here goes in a list
   column. Error values are keys, not prose — the page localizes them. */
$FIELDS = [
    // name          => [required, max length]
    'school'        => [true, 255],
    'school_other'  => [false, 255],
    'full_name'     => [true, 200],
    'project_title' => [true, 255],
    'email'         => [true, 254],
    'confirm'       => [true, 50],
    /* Hidden input ("en" / "fr"); only picks the confirmation's language. */
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

/* ---------- files ---------- */

/* 10 MB each, fixed: it is printed on the page ("Max. file size: 10 MB") and
   mirrored by FILE_MAX_BYTES in js/winners-form.js. Three files fit well
   inside api/.user.ini's post_max_size (60M). */
$maxBytes = 10 * 1024 * 1024;

$docs = [];
foreach (winners_upload_slots() as $slot => $spec) {
    $check = winners_check_upload($_FILES[$slot] ?? null, $spec['exts'], $maxBytes);
    if (isset($check['error'])) {
        $errors[$slot] = $check['error'];
        continue;
    }
    $docs[] = ['slot' => $slot, 'label' => $spec['label'], 'tmp' => (string)$_FILES[$slot]['tmp_name'], 'ext' => $check['ext']];
}

if ($errors) {
    respond(422, ['ok' => false, 'error' => 'fields', 'message' => 'Please check the highlighted fields.', 'fields' => $errors]);
}

/* Counted only now, so a winner fixing a wrong file type is never locked out:
   the limit caps what can actually be written to the server and SharePoint. */
rate_limit('winners', 10, 3600);

/* ---------- archive on the server ---------- */

/* Whatever was picked or typed (or posted directly), school_listed says
   whether the school is really on the Fall 2026 list: 'yes', 'no' — flagged
   on the team notice — or 'unchecked' if the list can't be read. */
$school = $data['school'] === $SCHOOL_OTHER ? $data['school_other'] : $data['school'];
$listed = winners_school_listed($SCHOOL_LIST_FILE, $school);
$fields = [
    'school'        => $school,
    'school_listed' => $listed === null ? 'unchecked' : ($listed ? 'yes' : 'no'),
    'full_name'     => $data['full_name'],
    'project_title' => $data['project_title'],
    'email'         => $data['email'],
    'confirm'       => $data['confirm'],
    'locale'        => $data['locale'],
];

$id   = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
$base = rtrim((string)$cfg['submissions_dir'], '/\\');
$dir  = $base . '/winners/' . $id;

$stored = [];
/* A half-written archive would leave banking documents on the server with no
   marker to ever deliver or delete them — so a storage failure takes back
   whatever it already wrote. */
$abandon = function () use ($dir, &$stored) {
    foreach ($stored as $s) @unlink($s['path']);
    @unlink($dir . '/submission.json');
    @rmdir($dir);
    respond(500, ['ok' => false, 'error' => 'store_failed', 'message' => 'Could not store the documents. Please try again later.']);
};

if (!is_dir($dir) && !@mkdir($dir, 0750, true)) $abandon();
/* If submissions_dir ends up inside a webroot, refuse to serve its contents. */
foreach ([$base, $base . '/winners'] as $denyDir) {
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
    'kind'         => 'winners',
    'round'        => $w['round'],
    'root_folder'  => $w['root_folder'],
    'received_utc' => gmdate('c'),
    'fields'       => $fields,
    /* Someone sending a different void cheque for a real winner need only
       type the project title differently to get a fresh folder, so repeats
       are found by NAME across the round's records, not by folder. Non-empty
       = SECOND SUBMISSION on every team notice; stored, so a retry says the
       same thing. */
    'earlier_submissions' => winners_earlier_submissions($base . '/winners', $w['round'], $fields['full_name'], $id),
    /* 'documents', NOT 'files': the application retry path reads 'files', so
       a winner archive that somehow reached it would find nothing to send. */
    'documents'    => array_map(fn($s) => ['slot' => $s['slot'], 'label' => $s['label'], 'name' => $s['name']], $stored),
];
if (@file_put_contents($dir . '/submission.json', json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
    $abandon();
}

/* ---------- answer, then deliver ----------
   Everything the winner must not lose is on disk; SharePoint is ~10 round
   trips, so the confirmation goes out first (same as apply.php). The id is
   returned for CI and logs, not shown on the page. */
respond_and_continue(200, ['ok' => true, 'id' => $id]);
@set_time_limit(180);

$folder = [];
$deliveryError = '';
if ($w['graph_ready']) {
    try {
        winners_graph_deliver($cfg, $record, $stored, $folder);
    } catch (Throwable $e) {
        $deliveryError = $e->getMessage();
    }
} else {
    /* Only reachable with allow_without_graph (CI): the page otherwise never
       opens without Graph. Archive-only — and still never emailed. */
    $deliveryError = "delivery_mode is '" . ($cfg['delivery_mode'] ?? 'off')
        . "', not 'graph' — winner documents are filed in SharePoint only";
}

if ($deliveryError === '') {
    $deleted = winners_finish_delivery($dir, $stored, $folder, 'delivered', 1);
    winners_notify_delivered($cfg, $record, $folder, false, $deleted);
} else {
    error_log("winners.php: delivery failed for $id — archived, queued for retry: $deliveryError");
    winners_record_failure($cfg, $dir, $record, $w['graph_ready'] ? 'graph' : 'awaiting-graph', $deliveryError, $folder);
}

/* ---------- confirm to the winner ----------
   LAST and inside a catch-all, as in apply.php: delivery and its bookkeeping
   above must never be skipped because a courtesy email threw. */
try {
    winners_ack($cfg, $record);
} catch (Throwable $e) {
    error_log("winners.php: confirmation email failed for $id (documents are safe): " . $e->getMessage());
}
