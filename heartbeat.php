<?php
/**
 * heartbeat.php – Lightweight device ping endpoint.
 *
 * The tablet sends a POST every ~60 seconds so the dashboard can track
 * which devices are online.
 *
 * POST fields:
 *   device_id   – unique device identifier
 *   device_name – (optional) human-readable label
 *   device_ip   – (optional) self-reported IP
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'POST required']);
    exit;
}

$deviceId  = trim($_POST['device_id']   ?? '');
$deviceName= trim($_POST['device_name'] ?? '');
$deviceIp  = trim($_POST['device_ip']   ?? $_SERVER['REMOTE_ADDR'] ?? '');

if ($deviceId === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing device_id']);
    exit;
}

try {
    $pdo = db();

    $stmt = $pdo->prepare("
        INSERT INTO devices (id, ip, name, last_seen)
        VALUES (:id, :ip, :name, CURRENT_TIMESTAMP)
        ON CONFLICT(id) DO UPDATE SET
            ip        = excluded.ip,
            name      = CASE WHEN excluded.name != '' THEN excluded.name ELSE devices.name END,
            last_seen = CURRENT_TIMESTAMP
    ");
    $stmt->execute([':id' => $deviceId, ':ip' => $deviceIp, ':name' => $deviceName]);

    echo json_encode(['status' => 'ok', 'server_time' => gmdate('c')]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
