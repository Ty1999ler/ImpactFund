<?php
// Retry application deliveries that failed. CLI ONLY — cron, every 30 minutes:
//   */30 * * * * /usr/local/bin/php /home/aseqhosting/public_html/api/redeliver.php >/dev/null 2>&1

/* The recovery half of apply.php's "archive first, deliver after" design.
   When SharePoint or the relay mailbox refuses a submission, apply.php leaves
   a DELIVERY-PENDING marker (JSON: mode, error, failed_at, attempts, notified)
   in that submission's archive directory. This script scans submissions_dir
   for markers and re-attempts each one, rebuilding the exact payload apply.php
   would have sent (shared builders in _lib.php). On success the marker goes
   away and the team gets a confirmation; on another failure the marker's
   attempt count grows and the next run tries again — forever, on purpose:
   giving up silently is the one outcome this file exists to prevent. */

/* Never via the web. api/.htaccess on the server denies config*.php and
   _lib.php but NOT this file, so this guard — before anything else runs — is
   the real gate. (Adding redeliver.php to that .htaccess deny list too is
   good hygiene; the guard must stay regardless.) */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit(1);
}

require __DIR__ . '/_lib.php';

$cfg = load_config();

/* One line per submission to stdout (cron-mail/log friendly); exit 0 always —
   a broken submission is reported, never allowed to stop the loop. */
$base = rtrim((string)$cfg['submissions_dir'], '/\\');
$markers = glob($base . '/*/DELIVERY-PENDING') ?: [];
if (!$markers) {
    echo "redeliver: nothing pending\n";
    exit(0);
}
foreach ($markers as $markerPath) {
    $dir = dirname($markerPath);
    $id  = basename($dir);
    try {
        echo redeliver_one($cfg, $dir, $id) . "\n";
    } catch (Throwable $e) {
        echo "$id: ERROR — " . $e->getMessage() . "\n";
    }
}
exit(0);

/* Re-attempt one submission; returns its one-line summary for stdout. */
function redeliver_one(array $cfg, string $dir, string $id): string {
    $markerPath = $dir . '/DELIVERY-PENDING';
    /* Concurrent runs: at a 30-minute cadence two can only overlap if one
       hangs, so this existence re-check stands in for a lock — the worst
       case (both retry, the item doubles in SharePoint) is visible and
       recoverable by hand, which is not worth a flock on this host. */
    if (!is_file($markerPath)) {
        return "$id: marker gone — another run already delivered it";
    }

    $marker = json_decode((string)@file_get_contents($markerPath), true);
    if (!is_array($marker)) $marker = [];
    $attempts = (int)($marker['attempts'] ?? 1) + 1;

    $record = json_decode((string)@file_get_contents($dir . '/submission.json'), true);
    if (!is_array($record) || !is_array($record['fields'] ?? null)) {
        return "$id: left pending — submission.json missing or unreadable";
    }
    $data = $record['fields'];

    /* Deliver with today's configured mode, not the marker's: if the mode was
       switched because the old transport is what broke, the retry should use
       the fix. 'off' means archive-only — leave the marker for later. */
    $mode = $cfg['delivery_mode'] ?? 'off';
    if ($mode !== 'email' && $mode !== 'graph') {
        return "$id: left pending — delivery_mode is '$mode'";
    }

    /* Rebuild the stored-file list exactly as apply.php recorded it:
       submission.json lists each archived name as "<slot>--<original>". */
    $labels = apply_upload_slots();
    $stored = [];
    $error  = '';
    foreach ((array)($record['files'] ?? []) as $name) {
        $name = basename((string)$name);  /* a doctored record must not escape the dir */
        $path = $dir . '/' . $name;
        if (!is_file($path)) {
            /* A damaged archive is not retryable — deliver nothing and say so
               every run, rather than deliver an application whose documents
               are silently missing. Needs a human. */
            $error = "archived file missing: $name";
            break;
        }
        $sep  = strpos($name, '--');
        $slot = $sep === false ? '' : substr($name, 0, $sep);
        $stored[] = [
            'slot'  => $slot,
            'label' => $labels[$slot][1] ?? $slot,
            'name'  => $name,
            'path'  => $path,
        ];
    }

    if ($error === '') {
        if ($mode === 'email') {
            $sent = send_mail(
                $cfg, $cfg['relay_to'],
                'Application — ' . (string)($data['project_title'] ?? '') . " ($id)",
                apply_summary_text($data, array_map(fn($s) => $s['name'], $stored), $id),
                (string)($data['primary_email'] ?? ''),
                array_map(fn($s) => ['path' => $s['path'], 'name' => $s['name']], $stored)
            );
            if (!$sent) $error = 'send_mail returned false (transport details are in the server error log)';
        } else {
            try {
                graph_deliver(apply_graph_config($cfg), $id, apply_graph_fields($data, $id), $stored);
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }

    if ($error === '') {
        @unlink($markerPath);
        delivery_update_record($dir, ['status' => 'delivered-after-retry', 'at' => gmdate('c'), 'attempts' => $attempts]);
        /* Close the loop on the failure notice apply.php sent. Best effort —
           a confirmation that cannot send must not resurrect the marker. */
        try {
            $to = (string)($cfg['failure_notify_to'] ?? '');
            if ($to === '') $to = (string)($cfg['relay_to'] ?? '');
            if ($to !== '') {
                @send_mail($cfg, $to,
                    "Submission delivered after retry ($id)",
                    "The application below failed to deliver earlier and has now gone through.\n\n"
                    . "Submission id: $id\n"
                    . 'Project title: ' . (string)($data['project_title'] ?? '') . "\n"
                    . 'Delivered:     ' . gmdate('c') . " (attempt $attempts, mode $mode)\n\n"
                    . "No action is needed.\n");
            }
        } catch (Throwable $e) {
            error_log("redeliver: confirmation email failed for $id: " . $e->getMessage());
        }
        return "$id: delivered after retry (attempt $attempts, $mode)";
    }

    /* Still failing: update the marker and wait for the next run. Deliberately
       NO email here — apply.php already sent the one failure notice, and a
       repeat every 30 minutes would only teach the inbox to ignore it. */
    $marker['mode']            = $mode;
    $marker['attempts']        = $attempts;
    $marker['last_error']      = mb_substr($error, 0, 500);
    $marker['last_attempt_at'] = gmdate('c');
    if ($attempts >= 20 && empty($marker['stuck_since'])) {
        /* Never give up — but after ~10 hours of failures leave a flag no one
           scanning the marker can miss. */
        $marker['stuck_since'] = gmdate('c');
    }
    @file_put_contents($markerPath, json_encode($marker, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return "$id: still failing (attempt $attempts, $mode) — " . mb_substr($error, 0, 200);
}
