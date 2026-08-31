<?php
/* Application form endpoint — multipart POST (fields + 5 documents).
   1. archives the submission under submissions_dir (JSON + files)
   2. answers the applicant, then delivers per delivery_mode: 'email' | 'graph' | 'off'
   3. a failed delivery leaves a DELIVERY-PENDING marker, emails the team once,
      and is retried by api/redeliver.php (cron, every 30 minutes)
   See api/config.example.php. */

require __DIR__ . '/_lib.php';

require_post();
$cfg = load_config();

/* When the request body exceeds post_max_size PHP silently delivers EMPTY
   $_POST and $_FILES — catch that before it reads as "all fields missing". */
if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    respond(413, ['ok' => false, 'error' => 'The submission is too large. Each file must be 10 MB or less.']);
}

honeypot_check();
rate_limit('apply', 5, 3600);

/* Server-side open date — the client-side reveal (?preview-form=1) only
   changes what is VISIBLE; actual submissions are gated here. */
$opensAt = strtotime((string)($cfg['opens_at'] ?? ''));
if ($opensAt && time() < $opensAt) {
    respond(403, ['ok' => false, 'error' => 'Submissions are not open yet.']);
}

/* ---------- fields ---------- */

/* Max lengths are the SharePoint ceiling, not an arbitrary choice: a single
   line of text column holds 255 characters and rejects more, and field()
   truncates rather than erroring — so a longer cap here would send a value
   Graph refuses, failing the whole submission silently. */
$FIELDS = [
    // name              => [required, max length, multiline]
    'primary_first_name'  => [true, 100, false],
    'primary_last_name'   => [true, 100, false],
    'primary_email'       => [true, 254, false],
    'primary_role'        => [true, 200, false],
    'secondary_first_name'=> [false, 100, false],
    'secondary_last_name' => [false, 100, false],
    'secondary_email'     => [false, 254, false],
    'secondary_role'      => [false, 200, false],
    'organization_name'   => [true, 255, false],
    'province'            => [true, 2, false],
    'institution'         => [true, 255, false],
    'campus_recognised'   => [false, 10, false],
    'off_campus_org'      => [false, 10, false],
    'project_title'       => [true, 255, false],
    'category'            => [true, 100, false],
    'category_other'      => [false, 200, false],
    'funding_requested'   => [true, 100, false],
    'total_cost'          => [true, 100, false],
    'fund_acknowledgement'=> [true, 50, false],
    'project_summary'     => [true, 4000, true],
    'students_in_org'     => [true, 20, false],
    'students_reached'    => [true, 20, false],
    'consent'             => [true, 50, false],
    /* Hidden input on each form ("en" on /apply-now/, "fr" on /fr/soumettre/).
       Optional on purpose: it only picks the language of the acknowledgement
       email, so an older cached page that omits it must not fail validation. */
    'locale'              => [false, 2, false],
];

$data = [];
$errors = [];
foreach ($FIELDS as $name => [$required, $max, $multiline]) {
    $v = field($name, $max, $multiline);
    if ($required && $v === '') $errors[$name] = 'Required';
    $data[$name] = $v;
}
if (!filter_var($data['primary_email'], FILTER_VALIDATE_EMAIL)) {
    $errors['primary_email'] = 'Invalid email';
}
if ($data['secondary_email'] !== '' && !filter_var($data['secondary_email'], FILTER_VALIDATE_EMAIL)) {
    $errors['secondary_email'] = 'Invalid email';
}
/* Province is submitted as a two-letter code and drives the institution list
   the applicant was shown, so it must be one we actually serve. */
$PROVINCES = ['AB', 'BC', 'MB', 'NB', 'NS', 'ON', 'QC', 'SK'];
if ($data['province'] !== '' && !in_array($data['province'], $PROVINCES, true)) {
    $errors['province'] = 'Invalid province';
}

$CATEGORIES = ['Mental Health & Wellbeing', 'Sustainability', 'Arts & Culture', 'Academics', 'Other'];
if ($data['category'] !== '' && !in_array($data['category'], $CATEGORIES, true)) {
    $errors['category'] = 'Invalid category';
}
if ($data['category'] === 'Other' && trim($data['category_other']) === '') {
    $errors['category_other'] = 'Required';
}

/* Numeric sanity — the UI enforces this client-side (type="number" /
   inputmode="decimal"), but a direct POST could otherwise plant text like
   "abc" into fields SharePoint/email treat as amounts. Counts must be whole
   numbers; the two dollar amounts may carry currency punctuation as typed
   (e.g. "$1,500.00"). */
foreach (['students_in_org', 'students_reached'] as $name) {
    if ($data[$name] !== '' && !ctype_digit($data[$name])) {
        $errors[$name] = 'Invalid number';
    }
}
foreach (['funding_requested', 'total_cost'] as $name) {
    if ($data[$name] === '') continue;
    $amount = str_replace(['$', ',', ' ', "\u{00A0}"], '', $data[$name]);
    if (filter_var($amount, FILTER_VALIDATE_FLOAT) === false || (float)$amount < 0) {
        $errors[$name] = 'Invalid number';
    }
}

/* ---------- files ---------- */

/* input name => [required, label] — defined in _lib.php because redeliver.php
   needs the same slot => label mapping to rebuild an archived delivery. */
$UPLOADS = apply_upload_slots();
$ALLOWED_EXT = ['doc', 'docx', 'xls', 'xlsx', 'csv', 'pdf'];
$maxBytes = (int)($cfg['max_file_mb'] ?? 10) * 1024 * 1024;

$files = [];
$finfo = new finfo(FILEINFO_MIME_TYPE);
foreach ($UPLOADS as $name => [$required, $label]) {
    $f = $_FILES[$name] ?? null;
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        if ($required) $errors[$name] = 'Required';
        continue;
    }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        $errors[$name] = 'Upload failed — please retry';
        continue;
    }
    if ($f['size'] > $maxBytes) {
        $errors[$name] = 'File is larger than ' . ($cfg['max_file_mb'] ?? 10) . ' MB';
        continue;
    }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $ALLOWED_EXT, true)) {
        $errors[$name] = 'File type not accepted';
        continue;
    }
    /* Light content sniff: block anything that looks executable/HTML. */
    $mime = (string)$finfo->file($f['tmp_name']);
    if (preg_match('#^(text/html|application/x-(php|httpd-php|sh)|application/javascript)#i', $mime)) {
        $errors[$name] = 'File type not accepted';
        continue;
    }
    /* Safe display name: slot label + sanitized original basename. */
    $orig = preg_replace('/[^A-Za-z0-9._ -]/', '_', basename($f['name']));
    $files[] = [
        'slot'  => $name,
        'label' => $label,
        'tmp'   => $f['tmp_name'],
        'name'  => $name . '--' . $orig,
    ];
}

if ($errors) {
    respond(422, ['ok' => false, 'error' => 'Please check the highlighted fields.', 'fields' => $errors]);
}

/* ---------- archive on the server ---------- */

$submissionId = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
$dir = rtrim($cfg['submissions_dir'], '/\\') . '/' . $submissionId;
if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
    respond(500, ['ok' => false, 'error' => 'Could not store the submission. Please try again later.']);
}
/* If submissions_dir ends up inside a webroot, refuse to serve its contents. */
$deny = rtrim($cfg['submissions_dir'], '/\\') . '/.htaccess';
if (!is_file($deny)) @file_put_contents($deny, "Require all denied\n");

$stored = [];
foreach ($files as $f) {
    $dest = $dir . '/' . $f['name'];
    if (!move_uploaded_file($f['tmp'], $dest)) {
        respond(500, ['ok' => false, 'error' => 'Could not store the submission. Please try again later.']);
    }
    $stored[] = ['slot' => $f['slot'], 'label' => $f['label'], 'name' => $f['name'], 'path' => $dest];
}
$record = [
    'id' => $submissionId,
    'received_utc' => gmdate('c'),
    'fields' => $data,
    'files' => array_map(fn($s) => $s['name'], $stored),
];
file_put_contents($dir . '/submission.json', json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

/* ---------- answer, then deliver ----------
   Everything above this line is the part that must not be lost, and it is on
   disk. Delivery is ~20 round trips to Microsoft; making the applicant watch a
   spinner through them buys nothing, so the confirmation goes out now and the
   delivery runs after the response is closed. */
respond_and_continue(200, ['ok' => true, 'id' => $submissionId]);
@set_time_limit(180);

/* Payload builders live in _lib.php, shared with redeliver.php, so a retried
   delivery is byte-for-byte the one that would have gone out first time. */
$mode = $cfg['delivery_mode'] ?? 'off';
$deliveryError = '';

if ($mode === 'email') {
    $atts = array_map(fn($s) => ['path' => $s['path'], 'name' => $s['name']], $stored);
    $sent = send_mail(
        $cfg, $cfg['relay_to'],
        "Application — {$data['project_title']} ($submissionId)",
        apply_summary_text($data, array_map(fn($s) => $s['name'], $stored), $submissionId),
        $data['primary_email'], $atts
    );
    if (!$sent) $deliveryError = 'send_mail returned false (transport details are in the server error log)';
} elseif ($mode === 'graph') {
    try {
        graph_deliver(apply_graph_config($cfg), $submissionId, apply_graph_fields($data, $submissionId), $stored);
    } catch (Throwable $e) {
        $deliveryError = $e->getMessage();
    }
}

/* The archive on disk succeeded either way — never lose a submission. But the
   applicant has already been told it worked, so a failure must reach a human
   AND fix itself: delivery_record_failure marks the archive DELIVERY-PENDING
   for the retry cron (api/redeliver.php) and emails the team once. */
if ($mode === 'email' || $mode === 'graph') {
    if ($deliveryError === '') {
        delivery_update_record($dir, ['status' => 'delivered', 'at' => gmdate('c')]);
    } else {
        error_log("apply.php: delivery ($mode) failed for $submissionId — archived, queued for retry: $deliveryError");
        delivery_record_failure($cfg, $dir, $submissionId, $mode, $deliveryError, $data);
    }
}

/* ---------- acknowledge the applicant ----------
   DELIBERATELY LAST, and inside a catch-all. Delivery and the
   DELIVERY-PENDING bookkeeping above are the parts that must never be
   skipped: if this courtesy email threw on its way out and it ran any
   earlier, the submission would be archived but never delivered AND never
   marked for retry — silently lost, after the applicant had been told it
   worked. Nothing below this line may affect the submission.
   Set 'applicant_ack' => false in config.php to switch it off. */
try {
    if (($cfg['applicant_ack'] ?? true) && $data['primary_email'] !== '') {
        $isFr = strtolower($data['locale'] ?? '') === 'fr';
        $ackSubject = $isFr
            ? 'Nous avons bien reçu votre candidature — Fonds d\'impact étudiant'
            : 'We received your application — Student Impact Fund';
        $ackBody = $isFr
            ? "Merci d'avoir soumis votre candidature au Fonds d'impact étudiant, par Alumo. "
              . "Les candidatures seront examinées après la fermeture de la période de soumission, "
              . "et vous pouvez vous attendre à recevoir une mise à jour concernant votre candidature "
              . "dans les deux mois suivant la date de clôture.\n"
            : "Thank you for your application to the Student Impact Fund, by Alumo. "
              . "Applications will be reviewed after the submission window closes and you can "
              . "expect to receive an update on your application within two months of the "
              . "closing date.\n";
        @send_mail($cfg, $data['primary_email'], $ackSubject, $ackBody);
    }
} catch (Throwable $e) {
    error_log("apply.php: applicant acknowledgement failed for $submissionId (submission is delivered and safe): " . $e->getMessage());
}
