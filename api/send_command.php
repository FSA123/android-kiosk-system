<?php
/**
 * api/send_command.php – Enqueue a remote command for a device.
 *
 * POST fields:
 *   device_id – target device ID ('*' for broadcast to all devices)
 *   command   – one of: REBOOT, SYNC_NOW, RELOAD_PLAYLIST, CLEAR_CACHE
 *   payload   – (optional) JSON string with additional parameters
 *
 * Redirects back to commands.php with a flash message.
 */

require_once __DIR__ . '/../auth.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

verify_csrf();

$ALLOWED_COMMANDS = ['REBOOT', 'SYNC_NOW', 'RELOAD_PLAYLIST', 'CLEAR_CACHE'];

$deviceId = trim($_POST['device_id'] ?? '');
$command  = trim($_POST['command']   ?? '');
$payload  = trim($_POST['payload']   ?? '');

session_boot();

if ($deviceId === '' || $command === '') {
    $_SESSION['cmd_error'] = 'Device ID and command are required.';
    header('Location: /commands.php');
    exit;
}

if (!in_array($command, $ALLOWED_COMMANDS, true)) {
    $_SESSION['cmd_error'] = "Unknown command: {$command}";
    header('Location: /commands.php');
    exit;
}

// Validate optional JSON payload
if ($payload !== '' && json_decode($payload) === null) {
    $_SESSION['cmd_error'] = 'Payload must be valid JSON if provided.';
    header('Location: /commands.php');
    exit;
}

try {
    $pdo = db();

    if ($deviceId === '*') {
        // Broadcast: enqueue for every registered device
        $devices = $pdo->query("SELECT id FROM devices")->fetchAll();
        if (empty($devices)) {
            $_SESSION['cmd_error'] = 'No devices registered.';
            header('Location: /commands.php');
            exit;
        }
        $ins = $pdo->prepare("INSERT INTO command_queue (device_id, command, payload) VALUES (:did, :cmd, :pay)");
        foreach ($devices as $dev) {
            $ins->execute([':did' => $dev['id'], ':cmd' => $command, ':pay' => $payload ?: null]);
        }
        $_SESSION['cmd_notice'] = "Command '{$command}' queued for all " . count($devices) . " device(s).";
    } else {
        $ins = $pdo->prepare("INSERT INTO command_queue (device_id, command, payload) VALUES (:did, :cmd, :pay)");
        $ins->execute([':did' => $deviceId, ':cmd' => $command, ':pay' => $payload ?: null]);
        $_SESSION['cmd_notice'] = "Command '{$command}' queued for device '{$deviceId}'.";
    }

} catch (PDOException $e) {
    $_SESSION['cmd_error'] = 'Database error while queuing command.';
}

header('Location: /commands.php');
exit;
