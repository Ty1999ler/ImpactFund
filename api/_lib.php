<?php
/* Shared helpers for the two form endpoints. PHP 7.4+ / 8.x, no dependencies. */

function respond(int $status, array $payload): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload);
    exit;
}

/* Answer the browser and keep running. Delivering a submission to SharePoint
   is ~20 round trips to Microsoft; the applicant should not sit through them
   when the archive on disk — written first, and the real system of record —
   already guarantees nothing is lost.

   This changes nothing about outcomes: a delivery failure was already
   non-fatal and already returned success. It does make such a failure even
   less visible, which is why apply.php now emails on one. */
function respond_and_continue(int $status, array $payload): void {
    ignore_user_abort(true);
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $body = json_encode($payload);
    header('Content-Length: ' . strlen($body));
    echo $body;

    /* Close the response and keep running. Each SAPI spells this differently
       and only one of them exists at a time: fastcgi_finish_request on PHP-FPM,
       litespeed_finish_request under LiteSpeed/LSAPI — which is what GoDaddy
       shared hosting runs, and why the FPM call alone left submissions taking
       the full ~12 seconds. */
    foreach (['fastcgi_finish_request', 'litespeed_finish_request'] as $finish) {
        if (function_exists($finish)) {
            $finish();
            return;
        }
    }
    /* Otherwise flush what we can — best effort. The client may still hold the
       connection open, in which case this is simply no worse than before. */
    while (ob_get_level() > 0) @ob_end_flush();
    @flush();
}

function load_config(): array {
    $path = __DIR__ . '/config.php';
    /* is_readable also catches a present-but-wrongly-permissioned file
       (e.g. created 600 by another user) — fail clean, not with a fatal. */
    if (!is_file($path) || !is_readable($path)) {
        respond(503, ['ok' => false, 'error' => 'Form backend is not configured yet.']);
    }
    $cfg = require $path;
    if (!is_array($cfg)) {
        respond(503, ['ok' => false, 'error' => 'Form backend is not configured yet.']);
    }
    return $cfg;
}

function require_post(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        respond(405, ['ok' => false, 'error' => 'Method not allowed.']);
    }
}

/* One value from $_POST, trimmed, hard length cap, control chars stripped
   (except newlines when $multiline). Invalid UTF-8 input yields '' (preg_replace
   with /u returns null on bad UTF-8, which would otherwise fatal). */
function field(string $name, int $max, bool $multiline = false): string {
    $v = $_POST[$name] ?? '';
    if (!is_string($v)) return '';
    $v = trim($v);
    $v = ($multiline
        ? preg_replace('/[^\P{C}\r\n\t]+/u', '', $v)
        : preg_replace('/\p{C}+/u', '', $v)) ?? '';
    if (mb_strlen($v) > $max) $v = mb_substr($v, 0, $max);
    return $v;
}

/* Never allow CR/LF (or encoded forms) into anything used in a mail header. */
function header_safe(string $v): string {
    return str_replace(["\r", "\n", '%0a', '%0d', '%0A', '%0D'], '', $v);
}

/* Fixed-window file-based rate limit (shared-hosting friendly).
   Fails open if the temp dir is unwritable.
   CF-Connecting-IP is attacker-controlled unless Cloudflare really is the
   direct peer, so it is only honored when config trust_cloudflare_header
   is true (i.e. the origin is ONLY reachable through Cloudflare). */
function rate_limit(string $bucket, int $max, int $windowSeconds): void {
    static $trustCf = null;
    if ($trustCf === null) {
        $cfgPath = __DIR__ . '/config.php';
        $cfg = is_file($cfgPath) ? (require $cfgPath) : [];
        $trustCf = is_array($cfg) && !empty($cfg['trust_cloudflare_header']);
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if ($trustCf && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])
        && filter_var($_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP)) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    $file = sys_get_temp_dir() . '/impactfund-rl-' . $bucket . '-' . hash('sha256', $ip);
    $now = time();
    $stamps = [];
    if (is_file($file)) {
        $stamps = array_filter(
            array_map('intval', file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []),
            fn($t) => $t > $now - $windowSeconds
        );
    }
    if (count($stamps) >= $max) {
        respond(429, ['ok' => false, 'error' => 'Too many requests — please try again later.']);
    }
    $stamps[] = $now;
    @file_put_contents($file, implode("\n", $stamps), LOCK_EX);
}

/* Honeypot: bots fill the hidden "website" field. Pretend success so they
   don't learn anything. */
function honeypot_check(): void {
    if (($_POST['website'] ?? '') !== '') {
        respond(200, ['ok' => true]);
    }
}

/* ---------- Test copies of the site ----------
   A submission sent from a test copy (staging, its before./open./closed.
   preview copies, the retired september. copy, the NAS) is marked so a dry
   run can never pass for a real application: "[TEST] " in front of every
   email subject and of the SharePoint list item's Title, its documents filed
   under a top-level "TEST" folder, and "test": true in submission.json (so
   api/redeliver.php, which has no host, keeps the marks on a retry).
   Production (alumoimpact.ca, www.) and every other name — localhost and CI
   included — stay unmarked.
   config 'test_mode' (true/false) overrides the host either way; absent (or
   not a boolean) = by host. Production's own names are checked FIRST and are
   never marked, whatever test_mode says: a staging config copied to
   production must not turn every real application into "[TEST]". HTTP_HOST
   comes from the client, but it is also what picks the document root on the
   host, so production's own api/ only sees a test name if someone forges
   one — and marks only their own submission. A leading "www." (cPanel's
   alias) and a ":port" are ignored. */
const SITE_LIVE_HOSTS = ['alumoimpact.ca'];
const SITE_TEST_HOSTS = [
    'staging.alumoimpact.ca',
    'before.alumoimpact.ca',
    'open.alumoimpact.ca',
    'closed.alumoimpact.ca',
    'september.alumoimpact.ca',
    'impactfund.wareham.stream',
];

function site_is_test(array $cfg): bool {
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));   /* CLI: none */
    $host = rtrim((string)preg_replace('/:\d*$/', '', $host), '.');
    if (strpos($host, 'www.') === 0) $host = substr($host, 4);
    if (in_array($host, SITE_LIVE_HOSTS, true)) return false;   /* whatever test_mode says */
    if (isset($cfg['test_mode']) && is_bool($cfg['test_mode'])) {
        return $cfg['test_mode'];
    }
    return in_array($host, SITE_TEST_HOSTS, true);
}

/* "[TEST] " in front of a subject/title when $test, else it unchanged. */
function test_prefix(bool $test, string $text): string {
    return $test ? '[TEST] ' . $text : $text;
}

/* A schedule date from config as a Unix timestamp, or false. Only an ISO
   date-time WITH an explicit offset is accepted ("2026-10-15T23:59:59-04:00"
   or "...Z"): strtotime() would happily read "2026-10-15 23:59" in the
   server's default time zone (UTC on most shared hosting), four hours off
   yet still "valid". Same pattern as /js/main.js and
   _tools/verify_integrity.py check 6. */
function schedule_time(string $raw) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?([+-]\d{2}:\d{2}|Z)$/', $raw)) {
        return false;
    }
    return strtotime($raw);
}

/* ---------- SMTP transport ----------
   Used when config has smtp.host; otherwise send_mail() falls back to PHP
   mail(). Authenticated SMTP is strongly preferred here: alumoimpact.ca
   publishes DMARC p=quarantine with no SPF record, so mail sent from the
   web host as @alumoimpact.ca fails alignment and gets junked. Sending
   through the domain's real provider (e.g. M365) aligns properly.

   $envelopeTo: every actual recipient, including Bcc. The Bcc *header* must
   NOT be present in $data — with SMTP the recipient list is the envelope,
   and a leftover header would expose the blind copies. */
function smtp_send(array $smtp, string $from, array $envelopeTo, string $data): bool {
    $host = (string)($smtp['host'] ?? '');
    if ($host === '' || !$envelopeTo) return false;
    $port    = (int)($smtp['port'] ?? 587);
    $enc     = strtolower((string)($smtp['encryption'] ?? 'tls'));
    $timeout = (int)($smtp['timeout'] ?? 20);

    $transport = ($enc === 'ssl') ? "ssl://$host:$port" : "tcp://$host:$port";
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $fp = @stream_socket_client($transport, $errno, $errstr, $timeout,
                                STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) { error_log("smtp: connect failed $errno $errstr"); return false; }
    stream_set_timeout($fp, $timeout);

    $read = function () use ($fp) {
        $out = '';
        while (($line = fgets($fp, 515)) !== false) {
            $out .= $line;
            /* multi-line replies keep a '-' in the 4th column */
            if (strlen($line) < 4 || $line[3] !== '-') break;
        }
        return $out;
    };
    $cmd = function (string $c, string $expect) use ($fp, $read) {
        if ($c !== '') fwrite($fp, $c . "\r\n");
        $r = $read();
        if (strncmp($r, $expect, strlen($expect)) !== 0) {
            error_log('smtp: expected ' . $expect . ' got ' . trim(substr($r, 0, 120)));
            return false;
        }
        return true;
    };

    $ehlo = 'EHLO ' . (parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), PHP_URL_HOST) ?: 'localhost');
    $ok = $cmd('', '220') && $cmd($ehlo, '250');
    if ($ok && $enc === 'tls') {
        $ok = $cmd('STARTTLS', '220')
           && @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)
           && $cmd($ehlo, '250');
    }
    if ($ok && ($smtp['username'] ?? '') !== '') {
        $ok = $cmd('AUTH LOGIN', '334')
           && $cmd(base64_encode((string)$smtp['username']), '334')
           && $cmd(base64_encode((string)$smtp['password']), '235');
    }
    if ($ok) $ok = $cmd('MAIL FROM:<' . $from . '>', '250');
    if ($ok) {
        foreach ($envelopeTo as $rcpt) {
            if (!$cmd('RCPT TO:<' . $rcpt . '>', '250')) { $ok = false; break; }
        }
    }
    if ($ok && $cmd('DATA', '354')) {
        /* dot-stuffing: a line that is just "." would end the message early */
        $body = preg_replace('/^\./m', '..', str_replace("\n", "\r\n",
                    str_replace("\r\n", "\n", $data)));
        fwrite($fp, $body . "\r\n.\r\n");
        $ok = $cmd('', '250');
    } else {
        $ok = false;
    }
    @fwrite($fp, "QUIT\r\n");
    @fclose($fp);
    return $ok;
}

/* Send a MIME email, optionally with file attachments.
   $attachments: list of ['path' => ..., 'name' => ...]. */
function send_mail(array $cfg, string $to, string $subject, string $body,
                   string $replyTo = '', array $attachments = []): bool {
    $from     = header_safe($cfg['mail_from']);
    $fromName = header_safe($cfg['mail_from_name'] ?? '');
    $subject  = header_safe($subject);

    $headers = [];
    $headers[] = 'From: ' . ($fromName !== '' ? '"' . addslashes($fromName) . '" ' : '') . "<$from>";
    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: ' . header_safe($replyTo);
    }
    /* Optional blind copies (config mail_bcc: list of addresses). Sendmail
       strips the Bcc header before delivery, so recipients never see it. */
    $bcc = array_filter(array_map(
        fn($a) => filter_var(header_safe(trim((string)$a)), FILTER_VALIDATE_EMAIL) ?: null,
        (array)($cfg['mail_bcc'] ?? [])
    ));
    /* With SMTP the blind copies go in the envelope (RCPT TO), never a header
       — a Bcc header would be delivered verbatim and expose them. */
    $useSmtp = ((string)($cfg['smtp']['host'] ?? '')) !== '';
    if ($bcc && !$useSmtp) {
        $headers[] = 'Bcc: ' . implode(', ', $bcc);
    }
    $headers[] = 'MIME-Version: 1.0';

    /* -f sets the envelope sender so SPF aligns with the From: domain on
       GoDaddy (otherwise Return-Path is the hosting account's server identity
       and strict receivers junk the mail). Only used when it's a clean email. */
    $extra = filter_var($from, FILTER_VALIDATE_EMAIL) ? '-f' . $from : '';

    $encSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    /* SMTP needs the full RFC822 message (headers + blank line + body) and the
       complete recipient list; mail() takes them separately. */
    $viaSmtp = function (string $mimeHeaders, string $mimeBody) use ($cfg, $from, $to, $bcc, $encSubject) {
        $rcpt = array_values(array_unique(array_merge([$to], $bcc)));
        $data = "To: $to\r\nSubject: $encSubject\r\n" . $mimeHeaders . "\r\n\r\n" . $mimeBody;
        return smtp_send((array)$cfg['smtp'], $from, $rcpt, $data);
    };

    if (!$attachments) {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';
        if ($useSmtp) return $viaSmtp(implode("\r\n", $headers), $body);
        return mail($to, $encSubject, $body, implode("\r\n", $headers), $extra);
    }

    $boundary = 'b' . bin2hex(random_bytes(16));
    $headers[] = "Content-Type: multipart/mixed; boundary=\"$boundary\"";

    $msg  = "--$boundary\r\n";
    $msg .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n";
    $msg .= $body . "\r\n";
    foreach ($attachments as $att) {
        $data = file_get_contents($att['path']);
        if ($data === false) continue;
        $name = header_safe($att['name']);
        $msg .= "--$boundary\r\n";
        $msg .= "Content-Type: application/octet-stream; name=\"$name\"\r\n";
        $msg .= "Content-Transfer-Encoding: base64\r\n";
        $msg .= "Content-Disposition: attachment; filename=\"$name\"\r\n\r\n";
        $msg .= chunk_split(base64_encode($data)) . "\r\n";
    }
    $msg .= "--$boundary--\r\n";

    if ($useSmtp) return $viaSmtp(implode("\r\n", $headers), $msg);
    return mail($to, $encSubject, $msg, implode("\r\n", $headers), $extra);
}

/* ---------- Microsoft Graph (delivery_mode = 'graph') ---------- */

/* Tokens last an hour; fetching a fresh one per submission is a round trip to
   Microsoft nobody needs. $g['token_cache'] is a path OUTSIDE the webroot —
   apply.php points it at submissions_dir, which is already denied to the web. */
function graph_token(array $g): string {
    $cache = (string)($g['token_cache'] ?? '');
    if ($cache !== '' && is_readable($cache)) {
        $c = json_decode((string)@file_get_contents($cache), true);
        /* Five minutes of margin so a token cannot expire mid-upload. */
        if (is_array($c) && !empty($c['token']) && ($c['expires'] ?? 0) > time() + 300) {
            return (string)$c['token'];
        }
    }

    $resp = http_json(
        "https://login.microsoftonline.com/{$g['tenant_id']}/oauth2/v2.0/token",
        http_build_query([
            'client_id' => $g['client_id'],
            'client_secret' => $g['client_secret'],
            'scope' => 'https://graph.microsoft.com/.default',
            'grant_type' => 'client_credentials',
        ]),
        ['Content-Type: application/x-www-form-urlencoded']
    );
    if (empty($resp['access_token'])) {
        throw new RuntimeException('Graph auth failed');
    }
    if ($cache !== '') {
        @file_put_contents($cache, json_encode([
            'token'   => $resp['access_token'],
            'expires' => time() + (int)($resp['expires_in'] ?? 3600),
        ]), LOCK_EX);
        @chmod($cache, 0600);
    }
    return $resp['access_token'];
}

/* Throws on transport errors and HTTP >= 400 so callers can't silently
   "succeed" — apply.php catches and falls back to archive-only.

   $tolerate lists status codes that should be returned instead of thrown
   (folder creation treats 409 "already exists" as an answer, not a failure);
   $status receives the response code either way. */
function http_json(string $url, $body, array $headers, string $method = 'POST', array $tolerate = [], &$status = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 60,
    ]);
    $out = curl_exec($ch);
    $errno = curl_errno($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($out === false || $errno !== 0) {
        throw new RuntimeException("HTTP request failed (curl errno $errno): $url");
    }
    if ($status >= 400 && !in_array($status, $tolerate, true)) {
        throw new RuntimeException("HTTP $status from $url: " . substr((string)$out, 0, 500));
    }
    $decoded = json_decode((string)$out, true);
    return is_array($decoded) ? $decoded : [];
}

/* ---------- SharePoint document library layout ----------
   Files are filed as  Region / Project title - School /  — two levels, which
   is how the review is organised. There is no province level: the school in
   the folder name already says where it is, and every level costs characters
   against SharePoint's 255-character limit on link columns. (Submissions
   from a test copy of the site go one level down, under TEST /.)

   The submission id is not the folder name — it meant nothing to a human —
   but it still identifies the submission in the list, the archive and the
   logs, and it breaks the tie when two projects share a name. */

/* Quebec is split by language of instruction. That is a per-SCHOOL fact, not
   a per-province one, so it has to be a list rather than something derived.

   These are Quebec's three English-language universities plus its largest
   English CEGEP — between them 8 of the 50 Quebec rows in js/schools-data.js.
   Everything else in Quebec is French-language.

   Two deliberate exclusions. The École nationale de théâtre is the National
   Theatre School of Canada and genuinely co-lingual, with parallel English and
   French sections; the client's call is to file it French. HEC Montréal and
   the ITHQ deliver real English programming but have no English-only track and
   require French of every applicant, so they are French too.

   Entries are school names as they appear BEFORE the " - " in the institution
   label the form submits. Matching ignores case. */
function sp_quebec_english_schools(): array {
    return [
        'Bishops University',   /* Bishop's, Lennoxville — no apostrophe in our data */
        'Concordia University',
        'Dawson College',
        'McGill University',
    ];
}

/* "McGill University - SSMU" -> "McGill University". Verified against all 155
   entries in js/schools-data.js: splitting on the first " - " recovers the
   school exactly every time, because the label is built as school + ' - ' +
   association and no school name contains that separator. */
function sp_school_name(string $institution): string {
    return trim(explode(' - ', $institution, 2)[0]);
}

/* The single folder a submission files under. Ontario stands alone; Quebec
   splits by language; the Atlantic provinces join Quebec (EN); the four
   western provinces share one bucket. Note the name cannot contain "/" —
   SharePoint rejects it outright — hence "&". */
function sp_region_folder(string $province, string $institution): string {
    if ($province === 'ON') return 'Ontario';
    if (in_array($province, ['NB', 'NS'], true)) return 'Quebec (EN) & East';
    if (in_array($province, ['AB', 'BC', 'MB', 'SK'], true)) return 'West';
    if ($province === 'QC') {
        $school = sp_school_name($institution);
        foreach (sp_quebec_english_schools() as $english) {
            if (strcasecmp($school, $english) === 0) return 'Quebec (EN) & East';
        }
        return 'Quebec (FR)';
    }
    /* province is validated against those eight before we get here, so this is
       only reachable if the form and this list ever drift apart. */
    return 'Unfiled';
}

/* SharePoint rejects a set of characters and names outright, and a rejected
   name fails the upload — so names are cleaned here, not hoped over. */
function sp_safe_name(string $name, string $fallback): string {
    $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name);
    $name = str_replace(['"', '*', ':', '<', '>', '?', '/', '\\', '|', '#', '%'], '-', $name);
    $name = preg_replace('/\s+/u', ' ', $name);
    $name = trim($name, " .\t");
    /* Keep segments short: the full server-relative path has a hard limit and
       three user-supplied segments can otherwise blow past it. */
    if (mb_strlen($name) > 100) $name = rtrim(mb_substr($name, 0, 100), ' .');
    $reserved = '/^(\.lock|CON|PRN|AUX|NUL|COM[0-9]|LPT[0-9]|desktop\.ini)$/i';
    if ($name === '' || preg_match($reserved, $name)
        || strpos($name, '_vti_') !== false || strpos($name, '~$') === 0) {
        return $fallback;
    }
    return $name;
}

/* "a/b c" -> "a/b%20c" — Graph's root:/<path>: addressing needs each segment
   encoded but the separators left alone. */
function sp_encode_path(string $path): string {
    return implode('/', array_map('rawurlencode', explode('/', $path)));
}

function graph_create_folder(array $auth, string $driveId, string $parentPath, string $name, string $behavior, &$status = null): array {
    $url = $parentPath === ''
        ? "https://graph.microsoft.com/v1.0/drives/$driveId/root/children"
        : "https://graph.microsoft.com/v1.0/drives/$driveId/root:/" . sp_encode_path($parentPath) . ":/children";
    return http_json($url, json_encode([
        'name' => $name,
        'folder' => new stdClass(),
        '@microsoft.graph.conflictBehavior' => $behavior,
    ]), array_merge($auth, ['Content-Type: application/json']), 'POST', [409], $status);
}

/* Walk the region chain (re-using folders that already exist),
   then create a leaf that is this submission's alone: 409 on the leaf means
   another project here is called the same thing, so it gets the submission's
   short suffix appended. Returns ['path' =>, 'webUrl' =>]. */
function graph_make_folder(array $auth, string $driveId, array $segments, string $submissionId): array {
    $leaf = array_pop($segments);

    $parentPath = '';
    foreach ($segments as $segment) {
        graph_create_folder($auth, $driveId, $parentPath, $segment, 'fail');
        $parentPath = $parentPath === '' ? $segment : "$parentPath/$segment";
    }

    $folder = graph_create_folder($auth, $driveId, $parentPath, $leaf, 'fail', $status);
    if ($status === 409) {
        $suffix = substr($submissionId, strrpos($submissionId, '-') + 1);
        /* Trim first: sp_safe_name caps the length, and appending to a title
           that is already at the cap would truncate the suffix back off. */
        $leaf = sp_safe_name(rtrim(mb_substr($leaf, 0, 80), ' .') . " ($suffix)", $submissionId);
        /* 'rename' rather than 'fail': the suffix is already unique, so a
           second collision means something we did not predict — take the
           name SharePoint offers instead of losing the documents. */
        $folder = graph_create_folder($auth, $driveId, $parentPath, $leaf, 'rename');
    }
    if (!empty($folder['name'])) $leaf = $folder['name'];

    return [
        'path'   => $parentPath === '' ? $leaf : "$parentPath/$leaf",
        'webUrl' => (string)($folder['webUrl'] ?? ''),
        'id'     => (string)($folder['id'] ?? ''),
    ];
}

/* Fit our canonical field names onto whatever columns the target list really
   has. Creating lists/columns needs permissions beyond Sites.Selected, so the
   destination schema is often not ours to change — this keeps that a config
   change instead of a code change.

   $g['field_map']      canonical => real column. Map to '' to push a field
                        into the overflow text instead of its own column.
                        Omit field_map entirely to send names through as-is.
   $g['overflow_field'] one text column that receives every unmapped field as
                        "Label: value" lines (plus the attachments folder). */
function graph_map_fields(array $g, array $fields, string $folderPath = ''): array {
    $map = $g['field_map'] ?? null;
    if (!is_array($map) || !$map) return $fields;

    $mapped = $overflow = [];
    foreach ($fields as $key => $value) {
        $target = $map[$key] ?? null;
        if (is_string($target) && $target !== '') $mapped[$target] = $value;
        else $overflow[$key] = $value;
    }

    $of = (string)($g['overflow_field'] ?? '');
    if ($of !== '' && $overflow) {
        $lines = [];
        foreach ($overflow as $key => $value) {
            /* PrimaryEmail -> "Primary Email" for humans reading the field */
            $label = trim(preg_replace('/(?<!^)[A-Z]/', ' $0', $key));
            $lines[] = $label . ': ' . $value;
        }
        if ($folderPath !== '') {
            $lines[] = 'Documents: /' . $folderPath . '/';
        }
        $prefix = isset($mapped[$of]) && $mapped[$of] !== '' ? $mapped[$of] . "\n\n" : '';
        $mapped[$of] = $prefix . implode("\n", $lines);
    }
    return $mapped;
}

/* "$1,500.00" -> 1500.0, so the destination column can be a real number and
   the reporting on it can be arithmetic rather than string-scraping. The
   archive and the email keep the string exactly as the applicant typed it. */
function parse_amount(string $value): ?float {
    $value = str_replace(['$', ',', ' ', "\u{00A0}"], '', trim($value));
    if ($value === '') return null;
    $number = filter_var($value, FILTER_VALIDATE_FLOAT);
    return $number === false ? null : (float)$number;
}

/* $test: a submission from a test copy of the site (site_is_test, or the
   'test' flag in its submission.json on a retry). */
function graph_deliver(array $g, string $submissionId, array $fields, array $files, bool $test = false): void {
    $token = graph_token($g);
    $auth  = ["Authorization: Bearer $token"];

    /* Built from the canonical field names, before field_map renames them for
       whatever the destination list actually calls its columns.

       The school goes in the leaf rather than in a level of its own: it is
       what tells two identical project titles apart, and a separate level
       would cost characters the link columns cannot spare. */
    $province    = (string)($fields['Province'] ?? '');
    $institution = (string)($fields['Institution'] ?? '');
    $school      = sp_school_name($institution);
    $title       = trim((string)($fields['Title'] ?? ''));
    $leaf        = $school !== '' ? "$title - $school" : $title;

    $segments = [
        sp_safe_name(sp_region_folder($province, $institution), 'Unfiled'),
        sp_safe_name($leaf, 'Untitled project'),
    ];
    /* A test submission files under its own top-level folder
       (TEST / Region / Title - School) and its Title gets "[TEST] " — added
       after the folder name is built from the plain title, and BEFORE
       graph_map_fields renames the columns. The title is shortened, never
       the prefix, to keep within the 255 characters a single line of text
       column holds (the cap in apply.php's $FIELDS). */
    if ($test) {
        array_unshift($segments, 'TEST');
        $fields['Title'] = test_prefix(true, mb_substr((string)($fields['Title'] ?? ''), 0,
            255 - mb_strlen(test_prefix(true, ''))));
    }

    $folder = $files ? graph_make_folder($auth, $g['drive_id'], $segments, $submissionId)
        : ['path' => '', 'webUrl' => '', 'id' => ''];

    /* The drive-item id, not the path: it survives someone tidying the
       library, so scripts that read the documents keep working. */
    $fields['FolderId'] = $folder['id'];

    $item = http_json(
        "https://graph.microsoft.com/v1.0/sites/{$g['site_id']}/lists/{$g['list_id']}/items",
        json_encode(['fields' => graph_map_fields($g, $fields, $folder['path'])]),
        array_merge($auth, ['Content-Type: application/json'])
    );
    if (empty($item['id'])) {
        throw new RuntimeException('SharePoint list item was not created');
    }

    $links = [];
    foreach ($files as $f) {
        $path = sp_encode_path($folder['path'] . '/' . $f['name']);
        $base = "https://graph.microsoft.com/v1.0/drives/{$g['drive_id']}/root:/{$path}:";
        $size = (int)filesize($f['path']);

        /* An upload session is never completed by zero chunks, so an empty
           file would hang one open and deliver nothing. */
        if ($size === 0) {
            $uploaded = http_json("$base/content", '', array_merge($auth, ['Content-Type: application/octet-stream']), 'PUT');
        } else {
            $session = http_json(
                "$base/createUploadSession",
                json_encode(['item' => ['@microsoft.graph.conflictBehavior' => 'rename']]),
                array_merge($auth, ['Content-Type: application/json'])
            );
            if (empty($session['uploadUrl'])) {
                throw new RuntimeException("No upload session for {$f['name']}");
            }

            $fh = fopen($f['path'], 'rb');
            $chunkSize = 5 * 1024 * 1024; // multiple of 320 KiB
            $offset = 0;
            $uploaded = [];
            while ($offset < $size) {
                $chunk = fread($fh, $chunkSize);
                if ($chunk === false || $chunk === '') {
                    fclose($fh);
                    throw new RuntimeException("Read failed while uploading {$f['name']}");
                }
                $len = strlen($chunk);
                $end = $offset + $len - 1;
                /* The response to the final chunk is the finished driveItem. */
                $uploaded = http_json($session['uploadUrl'], $chunk, [
                    'Content-Length: ' . $len,
                    "Content-Range: bytes $offset-$end/$size",
                ], 'PUT');
                $offset += $len;
            }
            fclose($fh);
        }

        if (!empty($uploaded['webUrl'])) {
            $links[$f['slot']] = ['url' => $uploaded['webUrl'], 'label' => $f['label']];
        }
    }

    graph_write_links($g, $auth, (string)$item['id'], $folder, $links);
}

/* Hyperlink columns are filled in a second pass because the file URLs do not
   exist until the uploads finish. Failure here is logged, not thrown: the
   application itself and its documents are already delivered, and the target
   list may simply not have these columns yet.

   $g['link_field']  hyperlink column for the documents folder
   $g['file_links']  upload slot => hyperlink column, one per document */
function graph_write_links(array $g, array $auth, string $itemId, array $folder, array $links): void {
    $patch = [];

    /* Collected as plain url/label pairs; the Graph encoding is decided below. */
    $linkField = (string)($g['link_field'] ?? '');
    if ($linkField !== '' && $folder['webUrl'] !== '') {
        $patch[$linkField] = ['url' => $folder['webUrl'], 'label' => 'All documents'];
    }
    foreach ((array)($g['file_links'] ?? []) as $slot => $column) {
        if (is_string($column) && $column !== '' && isset($links[$slot])) {
            $patch[$column] = $links[$slot];
        }
    }
    if (!$patch) return;

    $url = "https://graph.microsoft.com/v1.0/sites/{$g['site_id']}/lists/{$g['list_id']}/items/{$itemId}/fields";
    $base = array_merge($auth, ['Content-Type: application/json']);

    /* Graph does not write list-item fields itself — it proxies to an internal
       SharePoint API whose default version (2.0) has no writer for URL fields,
       and refuses the whole request with a bare "invalidRequest" naming nothing.
       'Prefer: apiversion=2.1' selects the version that can. Text and number
       columns write fine without it, which is exactly why the first pass has
       always worked and only this one failed.

       The header is scoped to this request deliberately: on a shared client it
       also suppresses @microsoft.graph.downloadUrl on driveItem responses.

       The remaining shapes are fallbacks, tried in order, so an unexpected
       tenant costs a log line rather than another deploy. */
    $obj = fn(array $v) => (object)$v;  /* an array that lost its keys encodes as [] and 400s */
    $shapes = [
        'object+prefer' => ['prefer' => true,  'value' => fn($u, $l) => $obj(['Url' => $u, 'Description' => $l])],
        'url-only'      => ['prefer' => true,  'value' => fn($u, $l) => $obj(['Url' => $u])],
        'object'        => ['prefer' => false, 'value' => fn($u, $l) => $obj(['Url' => $u, 'Description' => $l])],
    ];

    /* A SharePoint URL field holds 255 characters, by design and not raisable.
       Province + institution + project title can reach that on their own, and
       an over-long URL fails as the same opaque 400 — so drop those here and
       say why, rather than leaving a mystery in the log. */
    foreach ($patch as $column => $link) {
        if (strlen($link['url']) > 255) {
            error_log("graph: link column '$column' skipped for item $itemId — URL is "
                . strlen($link['url']) . " chars, over SharePoint's 255 limit");
            unset($patch[$column]);
        }
    }
    if (!$patch) return;

    /* One PATCH per column, deliberately. Six columns in a single PATCH is
       five round trips cheaper and was tried — but a bulk write is one
       transaction, so a single column SharePoint dislikes takes the other five
       with it, and that is precisely what a reviewer notices. Per column, a bad
       one costs only itself and names itself in the log.

       The round trips no longer cost the applicant anything either: since the
       confirmation is returned before delivery starts, nobody is waiting. */
    $winner = null;
    foreach ($patch as $column => $link) {
        $written = false;
        /* Once one column succeeds, the rest almost certainly want the same
           encoding — try that first so the usual case is a single request. */
        $order = $winner ? [$winner => $shapes[$winner]] + $shapes : $shapes;

        foreach ($order as $name => $shape) {
            $headers = $shape['prefer'] ? array_merge($base, ['Prefer: apiversion=2.1']) : $base;
            try {
                http_json($url, json_encode([$column => $shape['value']($link['url'], $link['label'])]), $headers, 'PATCH');
                if ($winner !== $name) {
                    error_log("graph: link column encoding '$name' accepted for item $itemId");
                    $winner = $name;
                }
                $written = true;
                break;
            } catch (Throwable $e) {
                $last = $e->getMessage();
            }
        }
        if (!$written) {
            error_log("graph: link column '$column' not written to item $itemId (all encodings refused): " . ($last ?? '?'));
        }
    }
}

/* ---------- Application delivery (shared by apply.php and redeliver.php) ----------
   apply.php builds these at submission time; redeliver.php rebuilds them from
   the archive when a failed delivery is retried. One definition each, so the
   live submission and the retry can never drift apart. */

/* The application's document slots — input name => [required, label]. */
function apply_upload_slots(): array {
    return [
        'file_project_overview'  => [true, 'Project overview'],
        'file_budget'            => [true, 'Detailed budget'],
        'file_team_members'      => [true, 'Team members'],
        'file_action_plan'       => [true, 'Action plan and schedule'],
        /* Client renamed the document "Partner Sign-off Form" — the input name
           stays file_support_letter so nothing downstream has to move. */
        'file_support_letter'    => [true, 'Partner Sign-off Form'],
    ];
}

/* The canonical SharePoint list-item fields for one application (before
   graph_map_fields renames them for the destination's real columns).
   $data is the validated field set (apply.php) or submission.json's 'fields'
   (redeliver.php) — an older archive may lack a key, hence the '' fallbacks. */
function apply_graph_fields(array $data, string $submissionId): array {
    $d = fn(string $k): string => (string)($data[$k] ?? '');
    return [
        'Title'            => $d('project_title'),
        'SubmissionId'     => $submissionId,
        'Organization'     => $d('organization_name'),
        'Institution'      => $d('institution'),
        'Province'         => $d('province'),
        'CampusRecognised' => $d('campus_recognised'),
        'OffCampusOrg'     => $d('off_campus_org'),
        /* Added Sept 2026 with the two affiliation questions. The list MUST have
           a column with this exact internal name before this ships: field_map is
           empty, so these keys go to Graph verbatim, and an unrecognised column
           fails the entire list-item create — not just this value. */
        'OffCampusOrgName' => $d('off_campus_org_name'),
        'Category'         => ($d('category') === 'Other' && trim($d('category_other')) !== '')
                              ? ('Other — ' . trim($d('category_other'))) : $d('category'),
        'PrimaryContact'   => $d('primary_first_name') . ' ' . $d('primary_last_name'),
        'PrimaryEmail'     => $d('primary_email'),
        'PrimaryRole'      => $d('primary_role'),
        'SecondaryContact' => trim($d('secondary_first_name') . ' ' . $d('secondary_last_name')),
        'SecondaryEmail'   => $d('secondary_email'),
        'SecondaryRole'    => $d('secondary_role'),
        /* Numbers, not strings: these are the fields anything downstream
           will want to add up or filter on. */
        'FundingRequested' => parse_amount($d('funding_requested')),
        'TotalCost'        => parse_amount($d('total_cost')),
        'StudentsInOrg'    => $d('students_in_org') === '' ? null : (int)$d('students_in_org'),
        'StudentsReached'  => $d('students_reached') === '' ? null : (int)$d('students_reached'),
        'Summary'          => $d('project_summary'),
        /* Both are required tick-boxes on the form, i.e. they exist as
           evidence that the applicant agreed. Evidence the review team
           cannot see is not evidence, so it goes in the list rather than
           only into the server-side archive. */
        'Consent'          => $d('consent'),
        'FundAcknowledgement' => $d('fund_acknowledgement'),
    ];
}

/* The graph config ready to hand to graph_deliver: the hour-long auth token
   is cached beside the archive — outside the webroot, in a directory already
   denied to the web. Saves a round trip to Microsoft on every delivery. */
function apply_graph_config(array $cfg): array {
    $g = (array)($cfg['graph'] ?? []);
    $g['token_cache'] = rtrim((string)$cfg['submissions_dir'], '/\\') . '/.graph-token';
    return $g;
}

/* Plain-text body of the relay email (delivery_mode 'email').
   $fileNames: the archived names, "<slot>--<original>". */
function apply_summary_text(array $data, array $fileNames, string $submissionId): string {
    $lines = ["New Student Impact Fund application — $submissionId", ''];
    foreach ($data as $k => $v) {
        $v = (string)$v;
        if ($v !== '') $lines[] = str_pad($k, 22) . ': ' . str_replace(["\r", "\n"], [' ', ' '], $v);
    }
    $lines[] = '';
    $lines[] = 'Files: ' . implode(', ', $fileNames);
    return implode("\n", $lines);
}

/* ---------- Delivery failure bookkeeping ----------
   The archive directory is the system of record; delivery is the step that
   may fail. A failure leaves <dir>/DELIVERY-PENDING (JSON: mode, error,
   failed_at, attempts, notified) which api/redeliver.php — cron, every 30
   minutes — scans for and re-attempts. */

/* Rewrite the 'delivery' key inside an archived submission.json. Best effort
   and a single file_put_contents like the original write: bookkeeping must
   never break the delivery it describes, and the only competing writer is a
   cron run 30 minutes away. */
function delivery_update_record(string $dir, array $delivery): void {
    $path = $dir . '/submission.json';
    $record = json_decode((string)@file_get_contents($path), true);
    if (!is_array($record)) return;
    $record['delivery'] = $delivery;
    @file_put_contents($path, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/* First failure for a submission: write the retry marker, note the failure in
   submission.json, and email the team ONE notice (redeliver.php deliberately
   never repeats it). Everything here is shielded — the applicant already has
   ok:true, and nothing in this function may throw past it.
   $test: a submission from a test copy of the site — "[TEST] " on the notice. */
function delivery_record_failure(array $cfg, string $dir, string $submissionId, string $mode, string $error, array $data, bool $test = false): void {
    $error = mb_substr($error, 0, 500);
    $failedAt = gmdate('c');
    $marker = [
        'mode'      => $mode,
        'error'     => $error,
        'failed_at' => $failedAt,
        'attempts'  => 1,
        'notified'  => false,
    ];
    @file_put_contents($dir . '/DELIVERY-PENDING', json_encode($marker, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    delivery_update_record($dir, ['status' => 'pending-retry', 'mode' => $mode, 'error' => $error, 'failed_at' => $failedAt]);

    try {
        $to = (string)($cfg['failure_notify_to'] ?? '');
        if ($to === '') $to = (string)($cfg['relay_to'] ?? '');
        if ($to === '') return;
        $contact = trim((string)($data['primary_first_name'] ?? '') . ' ' . (string)($data['primary_last_name'] ?? ''));
        $body = "A Student Impact Fund application could not be delivered (mode: $mode).\n"
              . "The submission itself is safe — it is archived on the server and\n"
              . "nothing is lost.\n\n"
              . "Submission id:   $submissionId\n"
              . 'Project title:   ' . (string)($data['project_title'] ?? '') . "\n"
              . "Primary contact: $contact <" . (string)($data['primary_email'] ?? '') . ">\n"
              . "Archive:         $dir\n\n"
              . "Error:\n$error\n\n"
              . "The server retries delivery automatically every 30 minutes\n"
              . "(api/redeliver.php) and will email this address again to confirm\n"
              . "once the submission is delivered.\n";
        $sent = @send_mail($cfg, $to, test_prefix($test, "Submission delivery FAILED — will retry ($submissionId)"), $body);
        if ($sent) {
            $marker['notified'] = true;
            @file_put_contents($dir . '/DELIVERY-PENDING', json_encode($marker, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    } catch (Throwable $e) {
        error_log("delivery failure notice could not be sent for $submissionId: " . $e->getMessage());
    }
}

/* ======================================================================
   Winners page — api/winners.php (+ its branch in api/redeliver.php).
   Added Oct 2026 as an APPEND-ONLY block: apply.php and the retry cron share
   this file and were live while it was written, so nothing above this line
   was changed for it. The upload loop below is a copy of graph_deliver's
   rather than a refactor of it, for the same reason.

   Winner documents are a signed funding agreement, a finance form (tax and
   banking details) and a void cheque, and they should end up in exactly one
   place: Alumo's SharePoint. Hence, unlike applications:
   - delivery is Microsoft Graph ONLY. winners_config() keeps the page closed
     unless delivery_mode is 'graph' with credentials, so documents are never
     parked on the web host waiting for a transport that cannot file them;
   - every winners email goes through winners_send_mail(), which has no
     attachments parameter at all — no code path here can attach a document;
   - once SharePoint has the files the server copies are deleted, leaving a
     small submission.json with no file contents;
   - archives live one level deeper than applications
     (submissions_dir/winners/<id>/), so the application retry glob — one
     directory level, submissions_dir/<id>/DELIVERY-PENDING — cannot see them
     and can never push one down its email-capable path; and they list their
     files under 'documents', not 'files', as a second guard.
   A submission from a test copy of the site (site_is_test, above) is marked
   the way applications are: "[TEST] " in front of every winners email
   subject, its documents filed under a top-level TEST folder
   (TEST / <root_folder> / <round> / …), and 'test' => true in its
   submission.json, which the retry reads back (cron has no host). Test and
   real archives never count as each other's second submission.
   ====================================================================== */

/* Where winner documents go, read from config every time (the retry job
   needs it even when the 'winners' block is incomplete or the round is over):
   the drive is winners.drive_id, else the applications' graph.drive_id. */
function winners_graph_target(array $cfg): array {
    $w = is_array($cfg['winners'] ?? null) ? $cfg['winners'] : [];
    $g = is_array($cfg['graph'] ?? null) ? $cfg['graph'] : [];
    $drive = trim((string)($w['drive_id'] ?? ''));
    if ($drive === '') $drive = trim((string)($g['drive_id'] ?? ''));
    $ready = ($cfg['delivery_mode'] ?? 'off') === 'graph'
        && trim((string)($g['tenant_id'] ?? '')) !== ''
        && trim((string)($g['client_id'] ?? '')) !== ''
        && trim((string)($g['client_secret'] ?? '')) !== ''
        && $drive !== '';
    return [
        'ready'       => $ready,
        'drive_id'    => $drive,
        /* false unless explicitly true: the first submission must not be the
           thing that creates the Winners folder, because a folder created by
           the app inherits the library's permissions (everyone who reviews
           applications) instead of the restricted ones Alumo sets. */
        'create_root' => ($w['create_root'] ?? false) === true,
    ];
}

/* "2026-10-30T23:59:59-04:00" -> epoch seconds, or null. Only an ISO date
   WITH an explicit offset, and only a real one: strtotime() quietly rolls an
   impossible date forward ('2026-11-31' becomes December 1), which would move
   a deadline without a word, so the calendar date and the clock are checked
   first. */
function winners_parse_time(string $value): ?int {
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})(?::(\d{2}))?([+-](\d{2}):(\d{2})|Z)$/', $value, $m)) {
        return null;
    }
    if (!checkdate((int)$m[2], (int)$m[3], (int)$m[1])) return null;
    if ((int)$m[4] > 23 || (int)$m[5] > 59 || (int)$m[6] > 59) return null;
    if ($m[7] !== 'Z' && ((int)$m[8] > 14 || (int)$m[9] > 59)) return null;
    $ts = strtotime($value);
    return $ts === false ? null : $ts;
}

/* The 'winners' block of api/config.php with defaults filled in, or null when
   the page must stay CLOSED (the endpoint answers 503 'unconfigured'):
   - no 'winners' block, or an empty round / root_folder;
   - opens_at / closes_at neither '' nor an ISO date WITH an explicit offset
     (winners_parse_time). strtotime would read a bare "2026-10-30 23:59" in
     the server's own time zone (GoDaddy's is not Toronto's), and a typo it
     cannot parse would otherwise read as "no deadline" — both fail closed
     instead;
   - closes_at empty or missing. "No deadline" has to be said, as 'none':
     otherwise a config that left the key out would keep a page that takes
     banking documents open for good. Only allow_without_graph (CI) skips this;
   - opens_at at or after closes_at;
   - SharePoint delivery not ready (see winners_graph_target). Only
     'allow_without_graph' => true skips this rule, and it exists for
     api/config.ci.php alone: CI has no SharePoint. Never set it on a server.
   Adds opens_at_ts / closes_at_ts (int|null), graph_ready, drive_id and
   create_root. */
function winners_config(array $cfg): ?array {
    $w = $cfg['winners'] ?? null;
    if (!is_array($w)) return null;
    $w += [
        'round'               => '',
        'root_folder'         => '',
        'opens_at'            => '',
        'closes_at'           => '',
        'close_grace_minutes' => 15,
        'notify_to'           => '',
        'drive_id'            => '',
        'allow_without_graph' => false,
    ];
    $w['round'] = trim((string)$w['round']);
    $w['root_folder'] = trim((string)$w['root_folder']);
    if ($w['round'] === '' || $w['root_folder'] === '') {
        error_log('winners: config needs a round and a root_folder — page stays closed');
        return null;
    }
    foreach (['opens_at', 'closes_at'] as $key) {
        $value = trim((string)$w[$key]);
        $w[$key . '_ts'] = null;
        if ($key === 'closes_at' && strtolower($value) === 'none') continue;
        if ($value === '') {
            if ($key === 'opens_at' || $w['allow_without_graph'] === true) continue;
            error_log("winners: closes_at is not set — give the deadline (e.g. 2026-10-30T23:59:59-04:00) "
                . "or 'none' for no deadline — page stays closed");
            return null;
        }
        $ts = winners_parse_time($value);
        if ($ts === null) {
            error_log("winners: $key '$value' is not a real ISO date with an explicit offset "
                . '(e.g. 2026-10-30T23:59:59-04:00) — page stays closed');
            return null;
        }
        $w[$key . '_ts'] = $ts;
    }
    if ($w['opens_at_ts'] !== null && $w['closes_at_ts'] !== null && $w['opens_at_ts'] >= $w['closes_at_ts']) {
        error_log('winners: opens_at is not before closes_at — page stays closed');
        return null;
    }
    $w['close_grace_minutes'] = max(0, min(120, (int)$w['close_grace_minutes']));

    $target = winners_graph_target($cfg);
    $w['graph_ready'] = $target['ready'];
    $w['drive_id']    = $target['drive_id'];
    $w['create_root'] = $target['create_root'];
    if (!$w['graph_ready'] && $w['allow_without_graph'] !== true) {
        error_log("winners: SharePoint delivery is not set up (delivery_mode 'graph', graph "
            . 'tenant_id/client_id/client_secret and a drive id) — page stays closed');
        return null;
    }
    return $w;
}

/* 'open' | 'not-open' | 'closed' at $now. The window is [opens_at, closes_at).
   Submissions get close_grace_minutes extra, so an upload that was already on
   its way at the deadline is not refused; the page itself (the state check)
   shows 'closed' from closes_at exactly. */
function winners_window_state(array $w, int $now, bool $forSubmit): string {
    if ($w['opens_at_ts'] !== null && $now < $w['opens_at_ts']) return 'not-open';
    if ($w['closes_at_ts'] !== null) {
        $end = $w['closes_at_ts'] + ($forSubmit ? 60 * $w['close_grace_minutes'] : 0);
        if ($now >= $end) return 'closed';
    }
    return 'open';
}

/* The three documents — input name => label + accepted extensions. The label
   starts the file name in SharePoint ("Void cheque - Jane Doe.jpg"). Must
   match SLOTS in js/winners-form.js and the accept= lists on both pages. */
function winners_upload_slots(): array {
    $documents = ['pdf', 'docx', 'jpg', 'jpeg', 'png'];
    return [
        'file_agreement'    => ['label' => 'Funding agreement', 'exts' => $documents],
        'file_finance_form' => ['label' => 'Finance form',      'exts' => $documents],
        'file_void_cheque'  => ['label' => 'Void cheque',       'exts' => ['pdf', 'jpg', 'jpeg', 'png']],
    ];
}

/* One uploaded document -> ['ext' => extension to store it under] or
   ['error' => key]. The keys are what the page maps to its EN/FR copy:
   required, upload_failed, file_size, file_heic, file_type.
   The CONTENT is checked, not just the name — a finance team opens these:
     jpg/jpeg/png -> getimagesize() must see a real JPEG or PNG; the stored
                     extension follows what the image really is
     pdf          -> '%PDF-' within the first 1024 bytes
     docx         -> a ZIP holding [Content_Types].xml (ZipArchive where the
                     host has it, otherwise just the ZIP signature)
   plus, for every type, the executable/HTML MIME blocklist apply.php uses.
   A file with no extension (some Android pickers) is judged by its sniffed
   type: PDF, JPEG and PNG only.
   Order — HEIC, then type, then size — matches fileProblem() in
   js/winners-form.js, so both sides name the same problem first. */
function winners_check_upload($f, array $exts, int $maxBytes): array {
    if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['error' => 'required'];
    }
    if (is_array($f['error'])) return ['error' => 'upload_failed'];  /* name[] = several files */
    /* Over upload_max_filesize: PHP dropped the file, only its name arrived. */
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) {
        return ['error' => 'file_size'];
    }
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string)$f['tmp_name'])) {
        return ['error' => 'upload_failed'];
    }

    $path = (string)$f['tmp_name'];
    $ext  = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
    /* iPhone photos sent from Files or a Mac. Their own message, because
       "wrong type" means nothing to someone holding a photo. */
    if ($ext === 'heic' || $ext === 'heif') return ['error' => 'file_heic'];
    if ($ext !== '' && !in_array($ext, $exts, true)) return ['error' => 'file_type'];

    if ((int)$f['size'] > $maxBytes) return ['error' => 'file_size'];
    /* Zero bytes is what an iCloud/Drive placeholder that never downloaded
       looks like — a failed upload, not a wrong type. */
    if ((int)$f['size'] === 0) return ['error' => 'upload_failed'];

    $mime = strtolower((string)(new finfo(FILEINFO_MIME_TYPE))->file($path));
    if (preg_match('#^image/hei[cf]#', $mime)) return ['error' => 'file_heic'];
    if (preg_match('#^(text/html|application/x-(php|httpd-php|sh)|application/javascript)#i', $mime)) {
        return ['error' => 'file_type'];
    }
    if ($ext === '') {
        $byMime = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
        $ext = $byMime[$mime] ?? '';
    }
    if (!in_array($ext, $exts, true)) return ['error' => 'file_type'];

    if ($ext === 'jpg' || $ext === 'jpeg' || $ext === 'png') {
        $info = @getimagesize($path);
        $type = is_array($info) ? (int)$info[2] : 0;
        if ($type === IMAGETYPE_JPEG) return ['ext' => 'jpg'];
        if ($type === IMAGETYPE_PNG) return ['ext' => 'png'];
        return ['error' => 'file_type'];
    }
    $head = (string)@file_get_contents($path, false, null, 0, 1024);
    if ($ext === 'pdf') {
        return strpos($head, '%PDF-') !== false ? ['ext' => 'pdf'] : ['error' => 'file_type'];
    }
    if ($ext === 'docx') {
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($path) !== true) return ['error' => 'file_type'];
            $found = $zip->locateName('[Content_Types].xml') !== false;
            $zip->close();
            return $found ? ['ext' => 'docx'] : ['error' => 'file_type'];
        }
        return strncmp($head, "PK\x03\x04", 4) === 0 ? ['ext' => 'docx'] : ['error' => 'file_type'];
    }
    return ['error' => 'file_type'];
}

/* Was this record sent from a test copy of the site? Read from the archived
   'test' flag (winners.php sets it from site_is_test), never from the
   current request: api/redeliver.php runs from cron, with no host to ask.
   Absent (or anything but true) = a real submission. */
function winners_is_test(array $record): bool {
    return ($record['test'] ?? false) === true;
}

/* "Void cheque - Jane Doe.jpg". The extension goes on AFTER sp_safe_name,
   whose 100-character cap would otherwise cut it off a long name. */
function winners_doc_name(string $label, string $fullName, string $ext): string {
    return sp_safe_name($label . ' - ' . $fullName, $label) . '.' . $ext;
}

/* The ONLY way winners code sends mail. It deliberately has no attachments
   parameter: winner documents never travel by email, and routing every
   message through here makes that a property of the code, not a convention. */
function winners_send_mail(array $cfg, string $to, string $subject, string $body, string $replyTo = ''): bool {
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    return send_mail($cfg, $to, $subject, $body, $replyTo);
}

/* Team notices: winners.notify_to, else failure_notify_to, else relay_to. */
function winners_notify_address(array $cfg): string {
    $w = is_array($cfg['winners'] ?? null) ? $cfg['winners'] : [];
    foreach ([$w['notify_to'] ?? '', $cfg['failure_notify_to'] ?? '', $cfg['relay_to'] ?? ''] as $to) {
        $to = trim((string)$to);
        if ($to !== '') return $to;
    }
    return '';
}

/* Plain-text block naming the winner, shared by every team notice. */
function winners_details_text(array $record): string {
    $f = is_array($record['fields'] ?? null) ? $record['fields'] : [];
    $v = fn(string $k): string => str_replace(["\r", "\n"], ' ', (string)($f[$k] ?? ''));
    $listed = $v('school_listed');
    return 'Name:          ' . $v('full_name') . "\n"
         . 'Email:         ' . $v('email') . "\n"
         . 'School:        ' . $v('school')
         . ($listed === 'no' ? '   (NOT on the Fall 2026 list)'
            : ($listed === 'unchecked' ? '   (not checked: the Fall 2026 list could not be read)' : '')) . "\n"
         . 'Project title: ' . $v('project_title') . "\n"
         . 'Round:         ' . (string)($record['round'] ?? '') . "\n"
         . 'Submission id: ' . (string)($record['id'] ?? '') . "\n";
}

/* Is $school on the frozen list the page offers? Read from the page's own
   file (js/winners-schools-fall-2026.js, generated by
   _tools/freeze_winner_schools.py; its array is plain JSON), so school_listed
   is the server's finding rather than the browser's say-so and the two can
   never drift. Case-insensitive. null when the list cannot be read. */
function winners_school_listed(string $listFile, string $school): ?bool {
    $src = (string)@file_get_contents($listFile);
    $start = strpos($src, '= [');
    $end = strrpos($src, ']');
    $rows = ($start !== false && $end !== false && $end > $start)
        ? json_decode(substr($src, $start + 2, $end - $start - 1), true) : null;
    if (!is_array($rows) || !$rows) {
        error_log("winners: could not read the school list $listFile — school_listed 'unchecked'");
        return null;
    }
    $want = mb_strtolower(trim($school));
    foreach ($rows as $row) {
        if (is_array($row) && mb_strtolower(trim((string)($row['school'] ?? ''))) === $want) return true;
    }
    return false;
}

/* Comparison keys for a winner's name, for spotting a second submission by
   the same person however it was typed: case, accents, spacing, punctuation
   and word order are ignored, and Cyrillic/Greek letters that look like
   Latin ones are read as those. "Jane Doe", "jane  doe.", "Doe, Jane",
   "Jané Doe" and "Jаne Doe" (Cyrillic а) all share a key. Two keys: the
   words in order (catches "JaneDoe") and sorted (catches "Doe Jane").
   Normalizer (intl) is used where the host has it; the table below covers
   French and common Latin accents without it. */
function winners_name_keys(string $name): array {
    $s = mb_strtolower($name, 'UTF-8');
    if (class_exists('Normalizer')) {
        $n = Normalizer::normalize($s, Normalizer::FORM_D);
        if (is_string($n)) $s = preg_replace('/\p{Mn}+/u', '', $n) ?? $s;
    }
    $s = strtr($s, [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'ā' => 'a', 'ą' => 'a',
        'æ' => 'ae', 'ç' => 'c', 'ć' => 'c', 'č' => 'c', 'ď' => 'd', 'đ' => 'd',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e', 'ę' => 'e', 'ě' => 'e', 'ğ' => 'g',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i', 'ı' => 'i', 'ł' => 'l', 'ľ' => 'l',
        'ñ' => 'n', 'ń' => 'n', 'ň' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ø' => 'o', 'ő' => 'o', 'œ' => 'oe', 'ř' => 'r', 'ś' => 's', 'š' => 's', 'ş' => 's', 'ș' => 's',
        'ß' => 'ss', 'ť' => 't', 'ț' => 't', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ū' => 'u',
        'ů' => 'u', 'ű' => 'u', 'ý' => 'y', 'ÿ' => 'y', 'ź' => 'z', 'ż' => 'z', 'ž' => 'z',
        /* Cyrillic look-alikes (lower case of А В Е К М Н О Р С Т Х too) */
        'а' => 'a', 'в' => 'b', 'е' => 'e', 'ё' => 'e', 'к' => 'k', 'м' => 'm', 'н' => 'h', 'о' => 'o',
        'р' => 'p', 'с' => 'c', 'т' => 't', 'у' => 'y', 'х' => 'x', 'і' => 'i', 'ї' => 'i', 'ј' => 'j',
        'ѕ' => 's', 'ԁ' => 'd', 'һ' => 'h', 'ԛ' => 'q', 'ԝ' => 'w',
        /* Greek look-alikes */
        'α' => 'a', 'β' => 'b', 'ε' => 'e', 'η' => 'h', 'ι' => 'i', 'κ' => 'k', 'μ' => 'm', 'ν' => 'v',
        'ο' => 'o', 'ρ' => 'p', 'τ' => 't', 'υ' => 'u', 'χ' => 'x', 'ζ' => 'z',
    ]);
    $words = preg_split('/[^\p{L}\p{N}]+/u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (!$words) return [];
    $inOrder = implode('', $words);
    sort($words, SORT_STRING);
    return array_values(array_unique([$inOrder, implode('', $words)]));
}

/* Earlier submissions this round under the same name (winners_name_keys),
   oldest first, read from the archived submission.json records. Those stay
   after SharePoint has the documents and the copies are deleted, so this
   covers the whole round. A changed project title, or a sender who doesn't
   know it, therefore still reads as a second submission; the folder-name
   clash in winners_make_folder only catches an exact repeat. Called before
   this submission's own record is written, so every record found is an
   earlier (or simultaneous) one. Only records on the same side of $test
   (winners_is_test) count: a dry run under a real winner's name must never
   put a SECOND SUBMISSION warning on their real documents, nor a real
   submission be flagged because someone tested with that name first. */
function winners_earlier_submissions(string $winnersDir, string $round, string $fullName, string $ownId, bool $test): array {
    $keys = winners_name_keys($fullName);
    if (!$keys) return [];
    $found = [];
    foreach (glob($winnersDir . '/*/submission.json') ?: [] as $path) {
        $other = json_decode((string)@file_get_contents($path), true);
        if (!is_array($other) || ($other['kind'] ?? '') !== 'winners') continue;
        $otherId = (string)($other['id'] ?? '');
        if ($otherId === '' || $otherId === $ownId) continue;
        if ((string)($other['round'] ?? '') !== $round) continue;
        if (winners_is_test($other) !== $test) continue;
        $otherName = (string)($other['fields']['full_name'] ?? '');
        if (!array_intersect($keys, winners_name_keys($otherName))) continue;
        $found[] = [
            'id'           => $otherId,
            'full_name'    => $otherName,
            'email'        => (string)($other['fields']['email'] ?? ''),
            'received_utc' => (string)($other['received_utc'] ?? ''),
            'folder'       => (string)($other['delivery']['folder'] ?? ''),
        ];
    }
    return $found;
}

/* The SECOND SUBMISSION block that heads a team notice, or '' when this is
   the first submission under this name in the round. Two triggers: an
   earlier record with the same name (winners_earlier_submissions), and a
   SharePoint folder for the same name and project that already existed
   ($folder['second'], winners_make_folder). */
function winners_second_text(array $record, array $folder): string {
    $earlier = is_array($record['earlier_submissions'] ?? null) ? $record['earlier_submissions'] : [];
    $folderClash = !empty($folder['second']);
    if (!$earlier && !$folderClash) return '';
    $one = fn($v): string => str_replace(["\r", "\n"], ' ', (string)$v);
    $text = "SECOND SUBMISSION for this name — confirm banking details with the winner before paying.\n";
    if ($earlier) {
        $text .= "Earlier submission(s) this round under the same name (ignoring case, accents,\n"
               . "spacing, punctuation and word order):\n";
        foreach ($earlier as $e) {
            $text .= '  ' . $one($e['id'] ?? '') . '  ' . $one($e['full_name'] ?? '')
                   . ' <' . $one($e['email'] ?? '') . '>'
                   . ($one($e['folder'] ?? '') !== '' ? '  ' . $one($e['folder']) : '  (not filed yet)') . "\n";
        }
    }
    if ($folderClash) {
        $text .= "A folder for the same name and project was already in SharePoint, so this\n"
               . "submission was filed in a separate folder beside it rather than added to it.\n";
    }
    return $text . "Check with the winner (at the email on their original application, or by\n"
                 . "phone) that these documents are theirs before using the banking details.\n\n";
}

/* GET for Graph. http_json() always sends a body, which a GET should not. */
function winners_graph_get(array $auth, string $url, array $tolerate = [], &$status = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET        => true,
        CURLOPT_HTTPHEADER     => $auth,
        CURLOPT_TIMEOUT        => 60,
    ]);
    $out = curl_exec($ch);
    $errno = curl_errno($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($out === false || $errno !== 0) {
        throw new RuntimeException("HTTP request failed (curl errno $errno): $url");
    }
    if ($status >= 400 && !in_array($status, $tolerate, true)) {
        throw new RuntimeException("HTTP $status from $url: " . substr((string)$out, 0, 500));
    }
    $decoded = json_decode((string)$out, true);
    return is_array($decoded) ? $decoded : [];
}

/* The folder a record is filed in, as [segments, index of the root]:
   <root_folder> / <round> / "<Full name> - <Project title>" (root at 0), or
   for a test submission (winners_is_test) the same one level down,
   TEST / <root_folder> / … (root at 1) — the top-level TEST folder test
   applications use. So test documents never sit beside a real winner's, and
   a test folder can never clash with a real one. */
function winners_folder_segments(array $record): array {
    $f = is_array($record['fields'] ?? null) ? $record['fields'] : [];
    $segments = [
        sp_safe_name((string)($record['root_folder'] ?? ''), 'Winners'),
        sp_safe_name((string)($record['round'] ?? ''), 'Round'),
        sp_safe_name((string)($f['full_name'] ?? '') . ' - ' . (string)($f['project_title'] ?? ''), 'Winner'),
    ];
    if (!winners_is_test($record)) return [$segments, 0];
    array_unshift($segments, 'TEST');
    return [$segments, 1];
}

/* Root / round / "<Full name> - <Project title>". The round level is created
   when missing; the root only when create_root is true (see
   winners_graph_target) — otherwise a missing root fails the delivery, which
   leaves the documents queued for retry instead of filed somewhere open.
   $rootIndex is the root's place in $segments (winners_folder_segments):
   anything above it — the TEST folder — is created like the round, and the
   restricted root then has to exist inside it (TEST / <root_folder>).

   A second submission NEVER goes into the existing folder. Folder names come
   from what the sender typed, and winners' names and projects are public once
   Past Winners is back, so writing into an existing folder would let anyone
   with the link drop a different void cheque beside the real winner's. A 409
   on the leaf instead gets a sibling "<leaf> (2 - <id suffix>)" — the
   graph_make_folder suffix pattern — and 'second' => true, which puts the
   SECOND SUBMISSION warning on the team notice. */
function winners_make_folder(array $auth, string $driveId, array $segments, string $submissionId, bool $createRoot, int $rootIndex = 0): array {
    $leaf = array_pop($segments);

    $parentPath = '';
    foreach (array_values($segments) as $i => $segment) {
        $path = $parentPath === '' ? $segment : "$parentPath/$segment";
        if ($i === $rootIndex && !$createRoot) {
            winners_graph_get($auth, "https://graph.microsoft.com/v1.0/drives/$driveId/root:/"
                . sp_encode_path($path), [404], $rootStatus);
            if ($rootStatus === 404) {
                /* A test copy's TEST / <root_folder> usually sits in Alumo's
                   own library, so never suggest create_root for it. */
                throw new RuntimeException("SharePoint folder '$path' does not exist. " . ($rootIndex > 0
                    ? "Create it by hand in the same drive, restricted like '$segment' (keep "
                      . 'create_root false while this drive also holds the real winners\' folders).'
                    : 'Alumo creates it (with restricted permissions) before winners get the link; or set '
                      . 'winners.create_root => true to let the site create it.'));
            }
        } else {
            graph_create_folder($auth, $driveId, $parentPath, $segment, 'fail');
        }
        $parentPath = $path;
    }

    $folder = graph_create_folder($auth, $driveId, $parentPath, $leaf, 'fail', $status);
    $second = false;
    if ($status === 409) {
        $second = true;
        $suffix = substr($submissionId, strrpos($submissionId, '-') + 1);
        /* Trim first so sp_safe_name's length cap cannot cut the suffix off. */
        $leaf = sp_safe_name(rtrim(mb_substr($leaf, 0, 80), ' .') . " (2 - $suffix)", $submissionId);
        /* 'rename' rather than 'fail': the suffix is already unique, so a second
           collision is something we did not predict — take SharePoint's name. */
        $folder = graph_create_folder($auth, $driveId, $parentPath, $leaf, 'rename');
    }
    if (!empty($folder['name'])) $leaf = $folder['name'];

    return [
        'path'   => $parentPath === '' ? $leaf : "$parentPath/$leaf",
        'webUrl' => (string)($folder['webUrl'] ?? ''),
        'id'     => (string)($folder['id'] ?? ''),
        'second' => $second,
    ];
}

/* One file into an existing folder — a copy of graph_deliver's upload loop
   (5 MB chunks, a multiple of 320 KiB). Returns the finished driveItem.
   The folder is addressed by its item ID, never by path: an upload by path
   creates every missing folder on the way, so a retry after Alumo moved or
   deleted the restricted Winners folder would quietly rebuild it with the
   library's open permissions. By ID the upload follows a moved folder, and a
   deleted one answers 404, which leaves the archive pending. */
function winners_graph_upload(array $auth, string $driveId, string $folderId, string $localPath, string $name, string $conflict): array {
    $base = "https://graph.microsoft.com/v1.0/drives/$driveId/items/" . rawurlencode($folderId)
        . ':/' . rawurlencode($name) . ':';
    $size = (int)@filesize($localPath);
    if ($size === 0) {
        /* winners_check_upload refuses empty files, so this means the archive
           copy itself is damaged — never deliver half a submission. */
        throw new RuntimeException("Archived file is empty or unreadable: $name");
    }
    $session = http_json(
        "$base/createUploadSession",
        json_encode(['item' => ['@microsoft.graph.conflictBehavior' => $conflict]]),
        array_merge($auth, ['Content-Type: application/json'])
    );
    if (empty($session['uploadUrl'])) {
        throw new RuntimeException("No upload session for $name");
    }
    $fh = fopen($localPath, 'rb');
    if ($fh === false) throw new RuntimeException("Could not open $name for upload");
    $chunkSize = 5 * 1024 * 1024;
    $offset = 0;
    $uploaded = [];
    try {
        while ($offset < $size) {
            $chunk = fread($fh, $chunkSize);
            if ($chunk === false || $chunk === '') {
                throw new RuntimeException("Read failed while uploading $name");
            }
            $len = strlen($chunk);
            $end = $offset + $len - 1;
            $uploaded = http_json($session['uploadUrl'], $chunk, [
                'Content-Length: ' . $len,
                "Content-Range: bytes $offset-$end/$size",
            ], 'PUT');
            $offset += $len;
        }
    } finally {
        fclose($fh);
    }
    return $uploaded;
}

/* File one winner's documents. $folder is in/out: empty on a first attempt
   (the folder is created and written back BEFORE any upload, so a failure
   part-way still tells the caller which folder is this submission's); a
   retry passes the folder recorded in the DELIVERY-PENDING marker and
   re-uploads into it with 'replace', overwriting its own partial copies
   instead of mistaking them for a second submission. No list item is
   created — the folder and the team notice are the record. */
function winners_graph_deliver(array $cfg, array $record, array $docs, array &$folder): void {
    $target = winners_graph_target($cfg);
    if (!$target['ready']) {
        throw new RuntimeException("SharePoint delivery is not configured (delivery_mode is '"
            . ($cfg['delivery_mode'] ?? 'off') . "')");
    }
    $g = apply_graph_config($cfg);
    $auth = ['Authorization: Bearer ' . graph_token($g)];

    $retry = !empty($folder['path']);
    if (!$retry) {
        [$segments, $rootIndex] = winners_folder_segments($record);
        $folder = winners_make_folder($auth, $target['drive_id'], $segments,
            (string)($record['id'] ?? ''), $target['create_root'], $rootIndex);
    }
    if ((string)($folder['id'] ?? '') === '') {
        /* A recorded folder without its id: look it up where it was made. If it
           is no longer there the archive stays pending — it is never
           re-created by path (see winners_graph_upload). */
        $found = winners_graph_get($auth, "https://graph.microsoft.com/v1.0/drives/{$target['drive_id']}/root:/"
            . sp_encode_path((string)$folder['path']), [404], $lookup);
        if ($lookup === 404 || empty($found['id'])) {
            throw new RuntimeException("SharePoint folder '" . (string)$folder['path'] . "' is no longer there; "
                . 'left pending rather than re-created. Restore the folder, or file these documents by hand.');
        }
        $folder['id'] = (string)$found['id'];
    }
    foreach ($docs as $doc) {
        winners_graph_upload($auth, $target['drive_id'], (string)$folder['id'], $doc['path'], $doc['name'],
            $retry ? 'replace' : 'rename');
    }
}

/* Rewrite top-level keys of an archived submission.json (delivery_update_record
   only knows 'delivery'). Best effort, single write — same reasoning. */
function winners_update_record(string $dir, array $changes): void {
    $path = $dir . '/submission.json';
    $record = json_decode((string)@file_get_contents($path), true);
    if (!is_array($record)) return;
    foreach ($changes as $key => $value) $record[$key] = $value;
    @file_put_contents($path, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/* SharePoint has the documents: delete the server copies (they hold banking
   details) and say so in submission.json. A copy that will not delete is
   logged and listed in the record rather than silently left behind.
   Returns true when every server copy is gone. */
function winners_finish_delivery(string $dir, array $docs, array $folder, string $status, int $attempts): bool {
    $left = [];
    foreach ($docs as $doc) {
        if (is_file($doc['path']) && !@unlink($doc['path'])) $left[] = $doc['name'];
    }
    $changes = ['delivery' => [
        'status'            => $status,
        'at'                => gmdate('c'),
        'attempts'          => $attempts,
        'folder'            => (string)($folder['path'] ?? ''),
        'web_url'           => (string)($folder['webUrl'] ?? ''),
        'folder_id'         => (string)($folder['id'] ?? ''),
        /* Folder-name clash only; same-name repeats are 'earlier_submissions'
           at the top of the record. */
        'folder_existed'    => !empty($folder['second']),
    ]];
    if ($left) {
        error_log("winners: delivered, but could not delete the server copy of: " . implode(', ', $left) . " ($dir)");
        $changes['files_delete_failed'] = $left;
    } else {
        $changes['files_deleted_at'] = gmdate('c');
    }
    winners_update_record($dir, $changes);
    return !$left;
}

/* Team notice once SharePoint has the documents (first try or a retry).
   "[TEST] " in front of the subject for a test record. */
function winners_notify_delivered(array $cfg, array $record, array $folder, bool $afterRetry, bool $copiesDeleted): void {
    try {
        $to = winners_notify_address($cfg);
        if ($to === '') return;
        $body = winners_second_text($record, $folder);
        $name = (string)($record['fields']['full_name'] ?? '');
        $subject = ($body !== '' ? 'SECOND SUBMISSION — ' : '')
            . ($afterRetry ? 'Winner documents filed after retry — ' : 'Winner documents received — ')
            . $name . ' (' . (string)($record['round'] ?? '') . ')';
        $body .= ($afterRetry
                ? "These winner documents failed to file earlier and are now in SharePoint.\n"
                : "A winner sent their documents. They are filed in SharePoint (sign-in required):\n")
               . '  ' . (string)($folder['path'] ?? '') . "\n"
               . ((string)($folder['webUrl'] ?? '') !== '' ? '  ' . $folder['webUrl'] . "\n" : '')
               . "\n" . winners_details_text($record) . "\n"
               . "Documents: funding agreement, finance form, void cheque. They are NOT\n"
               . "attached — winner documents only ever go to SharePoint.\n"
               . ($copiesDeleted
                   ? "The copies on the web server have been deleted.\n"
                   : "WARNING: the copies on the web server could NOT all be deleted — see\n"
                     . "files_delete_failed in the archive's submission.json and remove them by hand.\n");
        winners_send_mail($cfg, $to, test_prefix(winners_is_test($record), $subject), $body);
    } catch (Throwable $e) {
        error_log('winners: team notice failed for ' . (string)($record['id'] ?? '?') . ': ' . $e->getMessage());
    }
}

/* First failure: write the retry marker, note it in submission.json, and send
   the team ONE notice (redeliver.php never repeats it) — without attachments.
   $mode is 'graph' (SharePoint refused) or 'awaiting-graph' (delivery not
   configured; only reachable with allow_without_graph, i.e. in CI).
   "[TEST] " in front of the subject for a test record.
   Shielded: the winner already has ok:true, and nothing here may throw. */
function winners_record_failure(array $cfg, string $dir, array $record, string $mode, string $error, array $folder): void {
    $id = (string)($record['id'] ?? basename($dir));
    $error = mb_substr($error, 0, 500);
    $failedAt = gmdate('c');
    $marker = [
        'kind'      => 'winners',
        'mode'      => $mode,
        'error'     => $error,
        'failed_at' => $failedAt,
        'attempts'  => 1,
        'notified'  => false,
        'folder'    => $folder,
    ];
    @file_put_contents($dir . '/DELIVERY-PENDING', json_encode($marker, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    winners_update_record($dir, ['delivery' => ['status' => 'pending-retry', 'mode' => $mode, 'error' => $error, 'failed_at' => $failedAt]]);

    try {
        $to = winners_notify_address($cfg);
        if ($to === '') return;
        $second = winners_second_text($record, $folder);
        $body = $second
              . "Winner documents could not be filed in SharePoint yet (mode: $mode).\n"
              . "They are safe on the server and nothing is lost. They are NOT attached\n"
              . "to this email, and never will be: winner documents only go to SharePoint.\n\n"
              . winners_details_text($record)
              . "Archive:       $dir\n\n"
              . "Error:\n$error\n\n"
              . ($mode === 'graph'
                  ? "The server retries every 30 minutes (api/redeliver.php) and will email\n"
                    . "this address again once the documents are filed.\n"
                  : "delivery_mode is not 'graph' on this server, so nothing can file them\n"
                    . "until SharePoint delivery is switched on; the retry job then picks\n"
                    . "them up.\n");
        $sent = winners_send_mail($cfg, $to, test_prefix(winners_is_test($record),
            ($second !== '' ? 'SECOND SUBMISSION — ' : '') . "Winner documents NOT yet filed — will retry ($id)"), $body);
        if ($sent) {
            $marker['notified'] = true;
            @file_put_contents($dir . '/DELIVERY-PENDING', json_encode($marker, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    } catch (Throwable $e) {
        error_log("winners: failure notice could not be sent for $id: " . $e->getMessage());
    }
}

/* A name or title as it may appear in the winner's confirmation, or '' to
   leave it out. The page is a public URL and the confirmation goes to
   whatever address was typed, from Alumo's own mail identity — so anything
   that could turn it into someone else's message (a link, an address, a
   domain, or just a lot of text) is dropped and the email falls back to its
   generic wording. A real name or title rarely trips this; if one does, the
   only cost is a less personal greeting. */
function winners_ack_value(string $value, int $max): string {
    $value = trim((string)preg_replace('/\s+/u', ' ', $value));
    if ($value === '' || mb_strlen($value) > $max) return '';
    if (preg_match('~(https?:|www\.|://|@|[\p{L}\p{N}-]\.\p{L}{2,})~iu', $value)) return '';
    return $value;
}

/* Site-wide cap on winner confirmations, whoever sends them: the per-IP
   rate limit alone would still let many addresses use the confirmation to
   mail strangers. A round has a few dozen winners, far below the cap; past
   it the documents are still archived, filed and notified to the team as
   usual — only the confirmation is skipped (and logged). Same fixed-window
   file as rate_limit(), and it fails open the same way. Test confirmations
   ($test) have a budget of their own, so a day of testing on a copy of the
   site that shares this server's temp directory can never use up the real
   winners' confirmations. */
function winners_ack_budget(int $max, int $windowSeconds, bool $test = false): bool {
    $file = sys_get_temp_dir() . '/impactfund-winners-ack' . ($test ? '-test' : '');
    $now = time();
    $stamps = [];
    if (is_file($file)) {
        $stamps = array_filter(
            array_map('intval', file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []),
            fn($t) => $t > $now - $windowSeconds
        );
    }
    if (count($stamps) >= $max) return false;
    $stamps[] = $now;
    @file_put_contents($file, implode("\n", $stamps), LOCK_EX);
    return true;
}

/* Confirmation to the winner, in the page's language. Names the document
   TYPES only; nothing is attached. Replies go to winners.notify_to when set,
   because the email invites them ("just reply to this email"). The name and
   title go through winners_ack_value, and at most 30 confirmations a day go
   out (winners_ack_budget). "[TEST] " in front of the subject for a test
   record. */
function winners_ack(array $cfg, array $record): void {
    $f = is_array($record['fields'] ?? null) ? $record['fields'] : [];
    $email = (string)($f['email'] ?? '');
    if ($email === '') return;
    $test = winners_is_test($record);
    if (!winners_ack_budget(30, 86400, $test)) {
        error_log('winners: daily confirmation cap reached — no confirmation sent for '
            . (string)($record['id'] ?? '?') . ' (documents are safe)');
        return;
    }
    $name  = winners_ack_value((string)($f['full_name'] ?? ''), 60);
    $title = winners_ack_value((string)($f['project_title'] ?? ''), 80);
    if (strtolower((string)($f['locale'] ?? '')) === 'fr') {
        $subject = "Nous avons bien reçu vos documents — Fonds d'impact étudiant";
        $body = ($name !== '' ? "Bonjour $name,\n\n" : "Bonjour,\n\n")
              . 'Merci ! Nous avons bien reçu vos documents '
              . ($title !== '' ? "pour « $title »" : 'pour votre projet') . ' : votre entente de '
              . "financement signée, votre formulaire financier rempli et votre spécimen de chèque.\n\n"
              . "Notre équipe les examinera et communiquera avec vous au sujet de votre paiement. "
              . "S'il faut modifier quoi que ce soit, répondez simplement à ce courriel.\n\n"
              . "Fonds d'impact étudiant par Alumo\n";
    } else {
        $subject = 'We received your documents — Student Impact Fund';
        $body = ($name !== '' ? "Hi $name,\n\n" : "Hi,\n\n")
              . 'Thank you! We received your documents '
              . ($title !== '' ? "for \"$title\"" : 'for your project') . ': your signed funding '
              . "agreement, your completed finance form and your void cheque.\n\n"
              . 'Our team will review them and be in touch about your payment. If anything '
              . "needs to change, just reply to this email.\n\n"
              . "Student Impact Fund by Alumo\n";
    }
    $w = is_array($cfg['winners'] ?? null) ? $cfg['winners'] : [];
    winners_send_mail($cfg, $email, test_prefix($test, $subject), $body, trim((string)($w['notify_to'] ?? '')));
}

/* Retry one winner archive (api/redeliver.php, cron). Mirrors redeliver_one's
   bookkeeping, but delivers through Graph ONLY: with any other delivery_mode
   the archive is left pending, never emailed. Returns the one-line summary.
   A test record keeps its marks on the retry ("[TEST] " subjects, the TEST
   folder): they come from its archived 'test' flag (winners_is_test), as a
   cron run has no host. */
function winners_redeliver_one(array $cfg, string $dir, string $id): string {
    $label = "winners/$id";
    $markerPath = $dir . '/DELIVERY-PENDING';
    if (!is_file($markerPath)) {
        return "$label: marker gone — another run already delivered it";
    }
    $marker = json_decode((string)@file_get_contents($markerPath), true);
    if (!is_array($marker)) $marker = [];
    $attempts = (int)($marker['attempts'] ?? 1) + 1;

    $record = json_decode((string)@file_get_contents($dir . '/submission.json'), true);
    if (!is_array($record) || ($record['kind'] ?? '') !== 'winners'
        || !is_array($record['fields'] ?? null) || !is_array($record['documents'] ?? null)) {
        return "$label: left pending — submission.json missing or not a winners record";
    }

    if (!winners_graph_target($cfg)['ready']) {
        return "$label: left pending — winner documents go to SharePoint only (delivery_mode is '"
            . ($cfg['delivery_mode'] ?? 'off') . "')";
    }

    $docs = [];
    $error = '';
    foreach ($record['documents'] as $doc) {
        $name = basename((string)($doc['name'] ?? ''));  /* a doctored record must not escape the dir */
        $path = $dir . '/' . $name;
        if ($name === '' || !is_file($path)) {
            /* Not retryable: never file a winner with a document missing. */
            $error = "archived file missing: $name";
            break;
        }
        $docs[] = ['slot' => (string)($doc['slot'] ?? ''), 'label' => (string)($doc['label'] ?? ''), 'name' => $name, 'path' => $path];
    }

    $folder = is_array($marker['folder'] ?? null) ? $marker['folder'] : [];
    if ($error === '') {
        try {
            winners_graph_deliver($cfg, $record, $docs, $folder);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }

    if ($error === '') {
        @unlink($markerPath);
        $deleted = winners_finish_delivery($dir, $docs, $folder, 'delivered-after-retry', $attempts);
        winners_notify_delivered($cfg, $record, $folder, true, $deleted);
        return "$label: delivered after retry (attempt $attempts)";
    }

    /* Still failing: update the marker and wait for the next run — no email,
       the first failure already sent the one notice. */
    $marker['kind']            = 'winners';
    $marker['mode']            = 'graph';
    $marker['attempts']        = $attempts;
    $marker['last_error']      = mb_substr($error, 0, 500);
    $marker['last_attempt_at'] = gmdate('c');
    $marker['folder']          = $folder;
    if ($attempts >= 20 && empty($marker['stuck_since'])) {
        $marker['stuck_since'] = gmdate('c');
    }
    @file_put_contents($markerPath, json_encode($marker, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return "$label: still failing (attempt $attempts) — " . mb_substr($error, 0, 200);
}
