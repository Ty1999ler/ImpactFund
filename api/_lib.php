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

    /* PHP-FPM/FastCGI closes the response and lets the script continue. */
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
        return;
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
   against SharePoint's 255-character limit on link columns.

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

function graph_deliver(array $g, string $submissionId, array $fields, array $files): void {
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

    $folder = $files ? graph_make_folder($auth, $g['drive_id'], [
        sp_safe_name(sp_region_folder($province, $institution), 'Unfiled'),
        sp_safe_name($leaf, 'Untitled project'),
    ], $submissionId) : ['path' => '', 'webUrl' => '', 'id' => ''];

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

    /* Six columns in one PATCH is six round trips saved. It is also a single
       transaction, so one unhappy column loses the lot — hence the per-column
       pass below, which only runs when the cheap path fails. */
    $primary = $shapes['object+prefer'];
    try {
        $bulk = [];
        foreach ($patch as $column => $link) {
            $bulk[$column] = $primary['value']($link['url'], $link['label']);
        }
        http_json($url, json_encode($bulk), array_merge($base, ['Prefer: apiversion=2.1']), 'PATCH');
        return;
    } catch (Throwable $e) {
        error_log("graph: bulk link write failed for item $itemId, retrying per column: " . $e->getMessage());
    }

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
   ok:true, and nothing in this function may throw past it. */
function delivery_record_failure(array $cfg, string $dir, string $submissionId, string $mode, string $error, array $data): void {
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
        $sent = @send_mail($cfg, $to, "Submission delivery FAILED — will retry ($submissionId)", $body);
        if ($sent) {
            $marker['notified'] = true;
            @file_put_contents($dir . '/DELIVERY-PENDING', json_encode($marker, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    } catch (Throwable $e) {
        error_log("delivery failure notice could not be sent for $submissionId: " . $e->getMessage());
    }
}
