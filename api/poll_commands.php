<?php
/**
 * api/poll_commands.php – Tablet polls for pending commands.
 *
 * The tablet's background task calls this endpoint periodically (e.g. every
 * 10–30 s) to fetch any commands queued for it and mark them as executed.
 *
 * GET / POST fields:
 *   device_id – the calling device's ID
 *
 * Returns JSON array of pending commands:
 *   [{"id":1,"command":"REBOOT","payload":null}, ...]
 *
 * After the tablet processes a command it should call this endpoint again
 * with an additional field:
 *   ack_id    – ID of the command that was executed (or failed)
 *   ack_status– "executed" or "failed"
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$deviceId  = trim($_REQUEST['device_id']   ?? '');
$ackId     = (int)($_REQUEST['ack_id']     ?? 0);
$ackStatus = trim($_REQUEST['ack_status']  ?? '');

if ($deviceId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing device_id']);
    exit;
}

try {
    $pdo = db();

    // Acknowledge a previously delivered command
    if ($ackId > 0 && in_array($ackStatus, ['executed', 'failed'], true)) {
        $upd = $pdo->prepare("
            UPDATE command_queue
            SET status = :s, executed_at = CURRENT_TIMESTAMP
            WHERE id = :id AND device_id = :did
        ");
        $upd->execute([':s' => $ackStatus, ':id' => $ackId, ':did' => $deviceId]);
    }

    // Return all pending commands for this device
    $stmt = $pdo->prepare("
        SELECT id, command, payload
        FROM command_queue
        WHERE device_id = :did AND status = 'pending'
        ORDER BY id ASC
        LIMIT 10
    ");
    $stmt->execute([':did' => $deviceId]);
    $commands = $stmt->fetchAll();

    echo json_encode($commands);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
