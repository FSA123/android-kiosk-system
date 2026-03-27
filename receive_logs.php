<?php
/**
 * receive_logs.php – Receives playback events from tablet devices.
 *
 * Expected POST fields:
 *   device_id  – unique device identifier (e.g. "tablet-01")
 *   asset      – filename of the media file that was played
 *   played_at  – (optional) ISO-8601 timestamp; defaults to server time
 *   device_ip  – (optional) reported IP of the device
 *   device_name– (optional) human-readable device name
 *
 * Returns JSON: {"status":"ok"} or {"status":"error","message":"..."}
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$deviceId  = trim($_POST['device_id']  ?? '');
$assetName = trim($_POST['asset']      ?? '');
$playedAt  = trim($_POST['played_at']  ?? '');
$deviceIp  = trim($_POST['device_ip']  ?? $_SERVER['REMOTE_ADDR'] ?? '');
$deviceName= trim($_POST['device_name']?? '');

if ($deviceId === '' || $assetName === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing device_id or asset']);
    exit;
}

// Basic validation: no path traversal in filename
if (strpos($assetName, '/') !== false || strpos($assetName, '\\') !== false) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid asset name']);
    exit;
}

// Validate / default timestamp
if ($playedAt === '' || !strtotime($playedAt)) {
    $playedAt = gmdate('Y-m-d H:i:s');
}

try {
    $pdo = db();

    // Upsert the device (register on first contact)
    $stmt = $pdo->prepare("
        INSERT INTO devices (id, ip, name, last_seen)
        VALUES (:id, :ip, :name, CURRENT_TIMESTAMP)
        ON CONFLICT(id) DO UPDATE SET
            ip        = excluded.ip,
            name      = CASE WHEN excluded.name != '' THEN excluded.name ELSE devices.name END,
            last_seen = CURRENT_TIMESTAMP
    ");
    $stmt->execute([':id' => $deviceId, ':ip' => $deviceIp, ':name' => $deviceName]);

    // Look up the asset by filename
    $assetStmt = $pdo->prepare("SELECT id FROM assets WHERE filename = :fn LIMIT 1");
    $assetStmt->execute([':fn' => $assetName]);
    $asset = $assetStmt->fetch();

    if (!$asset) {
        // Auto-register unknown assets (tablet may play files not yet in CMS)
        $ext  = strtolower(pathinfo($assetName, PATHINFO_EXTENSION));
        $type = in_array($ext, ['mp4', 'webm', 'mov']) ? 'video' : 'image';
        $ins  = $pdo->prepare("INSERT OR IGNORE INTO assets (filename, filepath, type) VALUES (:fn, :fp, :t)");
        $ins->execute([':fn' => $assetName, ':fp' => 'media/' . $assetName, ':t' => $type]);
        $assetId = (int)$pdo->lastInsertId();
    } else {
        $assetId = (int)$asset['id'];
    }

    // Insert the playback log entry
    $log = $pdo->prepare("
        INSERT INTO playback_logs (device_id, asset_id, played_at)
        VALUES (:did, :aid, :ts)
    ");
    $log->execute([':did' => $deviceId, ':aid' => $assetId, ':ts' => $playedAt]);

    echo json_encode(['status' => 'ok']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
