<?php
/**
 * sse.php – Server-Sent Events endpoint.
 *
 * The dashboard connects here to receive new playback log entries in real
 * time without polling or page refreshes.
 *
 * Protected: requires an active admin session.
 *
 * Protocol:
 *   event: log
 *   data:  {"id":42,"played_at":"...","device_id":"tablet-01","filename":"video.mp4"}
 */

require_once __DIR__ . '/auth.php';
require_auth();

// SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');   // Disable nginx proxy buffering

// Disable PHP output buffering as much as possible
if (ob_get_level()) {
    ob_end_flush();
}

// Send a keep-alive comment every ~20 s so the connection stays open
// through proxies and load balancers.

$pdo       = db();
$lastId    = (int)($pdo->query("SELECT MAX(id) FROM playback_logs")->fetchColumn() ?? 0);
$keepalive = 0;

set_time_limit(0);

while (true) {
    if (connection_aborted()) {
        exit;
    }

    // Fetch any new log entries since we last checked
    $stmt = $pdo->prepare("
        SELECT pl.id, pl.played_at, pl.device_id, a.filename
        FROM playback_logs pl
        JOIN assets a ON a.id = pl.asset_id
        WHERE pl.id > :last
        ORDER BY pl.id ASC
        LIMIT 50
    ");
    $stmt->execute([':last' => $lastId]);
    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        $lastId = max($lastId, (int)$row['id']);
        $payload = json_encode([
            'id'        => (int)$row['id'],
            'played_at' => $row['played_at'],
            'device_id' => $row['device_id'],
            'filename'  => $row['filename'],
        ]);
        echo "event: log\n";
        echo "data: {$payload}\n\n";
    }

    // Every ~20 iterations (20 s) send a keep-alive comment
    $keepalive++;
    if ($keepalive >= 20) {
        echo ": keepalive\n\n";
        $keepalive = 0;
    }

    flush();
    sleep(1);
}
