<?php
/* Shared helpers for the two form endpoints. PHP 7.4+ / 8.x, no dependencies. */

function respond(int $status, array $payload): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload);
    exit;
}

function load_config(): array {
    $path = __DIR__ . '/config.php';
    if (!is_file($path)) {
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
   (except newlines when $multiline). */
function field(string $name, int $max, bool $multiline = false): string {
    $v = $_POST[$name] ?? '';
    if (!is_string($v)) return '';
    $v = trim($v);
    $v = $multiline
        ? preg_replace('/[^\P{C}\r\n\t]+/u', '', $v)
        : preg_replace('/\p{C}+/u', '', $v);
    if (mb_strlen($v) > $max) $v = mb_substr($v, 0, $max);
    return $v;
}

/* Never allow CR/LF (or encoded forms) into anything used in a mail header. */
function header_safe(string $v): string {
    return str_replace(["\r", "\n", '%0a', '%0d', '%0A', '%0D'], '', $v);
}

/* Fixed-window file-based rate limit (shared-hosting friendly).
   Fails open if the temp dir is unwritable. */
function rate_limit(string $bucket, int $max, int $windowSeconds): void {
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
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
    $headers[] = 'MIME-Version: 1.0';

    if (!$attachments) {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';
        return mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers));
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

    return mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $msg, implode("\r\n", $headers));
}

/* ---------- Microsoft Graph (delivery_mode = 'graph') ---------- */

function graph_token(array $g): string {
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
    return $resp['access_token'];
}

function http_json(string $url, $body, array $headers, string $method = 'POST'): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 60,
    ]);
    $out = curl_exec($ch);
    curl_close($ch);
    $decoded = json_decode((string)$out, true);
    return is_array($decoded) ? $decoded : [];
}

/* Create the SharePoint list item, then upload each file into a submission
   folder in the document library (upload session handles files > 4 MB). */
function graph_deliver(array $g, string $submissionId, array $fields, array $files): void {
    $token = graph_token($g);
    $auth  = ["Authorization: Bearer $token"];

    http_json(
        "https://graph.microsoft.com/v1.0/sites/{$g['site_id']}/lists/{$g['list_id']}/items",
        json_encode(['fields' => $fields]),
        array_merge($auth, ['Content-Type: application/json'])
    );

    foreach ($files as $f) {
        $path = "/{$submissionId}/" . rawurlencode($f['name']);
        $session = http_json(
            "https://graph.microsoft.com/v1.0/drives/{$g['drive_id']}/root:{$path}:/createUploadSession",
            json_encode(['item' => ['@microsoft.graph.conflictBehavior' => 'rename']]),
            array_merge($auth, ['Content-Type: application/json'])
        );
        if (empty($session['uploadUrl'])) continue;

        $size = filesize($f['path']);
        $fh = fopen($f['path'], 'rb');
        $chunkSize = 5 * 1024 * 1024; // multiple of 320 KiB
        $offset = 0;
        while ($offset < $size) {
            $chunk = fread($fh, $chunkSize);
            $len = strlen($chunk);
            $end = $offset + $len - 1;
            http_json($session['uploadUrl'], $chunk, [
                'Content-Length: ' . $len,
                "Content-Range: bytes $offset-$end/$size",
            ], 'PUT');
            $offset += $len;
        }
        fclose($fh);
    }
}
