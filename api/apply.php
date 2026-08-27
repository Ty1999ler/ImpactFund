<?php
/* Application form endpoint — multipart POST (fields + up to 5 documents).
   1. archives the submission under submissions_dir (JSON + files)
   2. delivers it per config delivery_mode: 'email' | 'graph' | 'off'
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

$UPLOADS = [
    // input name         => [required, label]
    'file_budget'          => [true,  'Detailed budget'],
    'file_team_members'    => [true,  'Team members'],
    'file_action_plan'     => [true,  'Action plan and schedule'],
    'file_additional'      => [false, 'Additional information'],
    'file_support_letter'  => [true,  'Letter of support'],
];
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

/* ---------- deliver ---------- */

$summaryLines = ["New Student Impact Fund application — $submissionId", ''];
foreach ($data as $k => $v) {
    if ($v !== '') $summaryLines[] = str_pad($k, 22) . ': ' . str_replace(["\r", "\n"], [' ', ' '], $v);
}
$summaryLines[] = '';
$summaryLines[] = 'Files: ' . implode(', ', array_map(fn($s) => $s['name'], $stored));
$summary = implode("\n", $summaryLines);

$mode = $cfg['delivery_mode'] ?? 'off';
$deliveryOk = true;

if ($mode === 'email') {
    $atts = array_map(fn($s) => ['path' => $s['path'], 'name' => $s['name']], $stored);
    $deliveryOk = send_mail(
        $cfg, $cfg['relay_to'],
        "Application — {$data['project_title']} ($submissionId)",
        $summary, $data['primary_email'], $atts
    );
} elseif ($mode === 'graph') {
    try {
        graph_deliver($cfg['graph'], $submissionId, [
            'Title'            => $data['project_title'],
            'SubmissionId'     => $submissionId,
            'Organization'     => $data['organization_name'],
            'Institution'      => $data['institution'],
            'Province'         => $data['province'],
            'Category'         => ($data['category'] === 'Other' && trim($data['category_other']) !== '') ? ('Other — ' . trim($data['category_other'])) : $data['category'],
            'PrimaryContact'   => $data['primary_first_name'] . ' ' . $data['primary_last_name'],
            'PrimaryEmail'     => $data['primary_email'],
            'PrimaryRole'      => $data['primary_role'],
            'SecondaryContact' => trim($data['secondary_first_name'] . ' ' . $data['secondary_last_name']),
            'SecondaryEmail'   => $data['secondary_email'],
            /* Numbers, not strings: these are the fields anything downstream
               will want to add up or filter on. */
            'FundingRequested' => parse_amount($data['funding_requested']),
            'TotalCost'        => parse_amount($data['total_cost']),
            'StudentsInOrg'    => $data['students_in_org'] === '' ? null : (int)$data['students_in_org'],
            'StudentsReached'  => $data['students_reached'] === '' ? null : (int)$data['students_reached'],
            'Summary'          => $data['project_summary'],
        ], $stored);
    } catch (Throwable $e) {
        error_log('graph delivery failed for ' . $submissionId . ': ' . $e->getMessage());
        $deliveryOk = false;
    }
}

/* The archive on disk succeeded either way — never lose a submission. */
if (!$deliveryOk) {
    error_log("apply.php: delivery ($mode) failed for $submissionId — archived on server only");
}
respond(200, ['ok' => true, 'id' => $submissionId]);
