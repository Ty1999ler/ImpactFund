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

/* Throws on transport errors and HTTP >= 400 so callers can't silently
   "succeed" — apply.php catches and falls back to archive-only. */
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
    $errno = curl_errno($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($out === false || $errno !== 0) {
        throw new RuntimeException("HTTP request failed (curl errno $errno): $url");
    }
    if ($status >= 400) {
        throw new RuntimeException("HTTP $status from $url: " . substr((string)$out, 0, 500));
    }
    $decoded = json_decode((string)$out, true);
    return is_array($decoded) ? $decoded : [];
}

/* Create the SharePoint list item, then upload each file into a submission
   folder in the document library (upload session handles files > 4 MB). */
function graph_deliver(array $g, string $submissionId, array $fields, array $files): void {
    $token = graph_token($g);
    $auth  = ["Authorization: Bearer $token"];

    $item = http_json(
        "https://graph.microsoft.com/v1.0/sites/{$g['site_id']}/lists/{$g['list_id']}/items",
        json_encode(['fields' => $fields]),
        array_merge($auth, ['Content-Type: application/json'])
    );
    if (empty($item['id'])) {
        throw new RuntimeException('SharePoint list item was not created');
    }

    foreach ($files as $f) {
        $path = "/{$submissionId}/" . rawurlencode($f['name']);
        $session = http_json(
            "https://graph.microsoft.com/v1.0/drives/{$g['drive_id']}/root:{$path}:/createUploadSession",
            json_encode(['item' => ['@microsoft.graph.conflictBehavior' => 'rename']]),
            array_merge($auth, ['Content-Type: application/json'])
        );
        if (empty($session['uploadUrl'])) {
            throw new RuntimeException("No upload session for {$f['name']}");
        }

        $size = filesize($f['path']);
        $fh = fopen($f['path'], 'rb');
        $chunkSize = 5 * 1024 * 1024; // multiple of 320 KiB
        $offset = 0;
        while ($offset < $size) {
            $chunk = fread($fh, $chunkSize);
            if ($chunk === false || $chunk === '') {
                fclose($fh);
                throw new RuntimeException("Read failed while uploading {$f['name']}");
            }
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
