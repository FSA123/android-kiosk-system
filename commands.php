<?php
/**
 * commands.php – Remote Command Queue admin UI.
 *
 * Allows admins to send commands (REBOOT, SYNC_NOW, etc.) to individual
 * devices or broadcast to all devices.
 */

require_once __DIR__ . '/auth.php';
require_auth();

$pdo     = db();
$devices = $pdo->query("SELECT id, name FROM devices ORDER BY name ASC")->fetchAll();
$history = $pdo->query("
    SELECT cq.id, cq.device_id, cq.command, cq.payload, cq.status,
           cq.created_at, cq.executed_at
    FROM command_queue cq
    ORDER BY cq.id DESC
    LIMIT 100
")->fetchAll();

$notice = '';
$error  = '';
session_boot();
if (!empty($_SESSION['cmd_notice'])) { $notice = $_SESSION['cmd_notice']; unset($_SESSION['cmd_notice']); }
if (!empty($_SESSION['cmd_error']))  { $error  = $_SESSION['cmd_error'];  unset($_SESSION['cmd_error']);  }

$AVAILABLE_COMMANDS = ['REBOOT', 'SYNC_NOW', 'RELOAD_PLAYLIST', 'CLEAR_CACHE'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Command Queue – Kiosk Admin</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body   { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; margin: 0; color: #333; }
        header {
            background: #1a73e8; color: #fff; padding: 14px 24px;
            display: flex; align-items: center; justify-content: space-between;
        }
        header h1 { margin: 0; font-size: 1.2rem; }
        header nav a { color:#fff; text-decoration:none; margin-left:18px; font-size:.9rem; opacity:.85; }
        header nav a:hover { opacity:1; }
        .container { max-width: 1100px; margin: 24px auto; padding: 0 16px; }
        .card { background:#fff; padding:20px; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,.06); margin-bottom:20px; }
        h2   { margin-top:0; color:#1a73e8; font-size:1.1rem; }
        .form-row { display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; margin-top:12px; }
        select, input[type=text] {
            padding:9px 12px; border:1px solid #ccc; border-radius:6px;
            font-size:.9rem; min-width:180px;
        }
        select:focus, input:focus { outline:none; border-color:#1a73e8; }
        label { font-size:.85rem; font-weight:600; color:#444; display:block; margin-bottom:4px; }
        .btn    { padding:9px 20px; border-radius:6px; border:none; cursor:pointer; font-size:.9rem; }
        .btn-primary { background:#1a73e8; color:#fff; }
        .btn-primary:hover { background:#1558b0; }
        table { width:100%; border-collapse:collapse; font-size:.85rem; }
        th,td { text-align:left; padding:9px 10px; border-bottom:1px solid #eee; }
        th    { color:#555; font-weight:600; }
        .badge { padding:2px 8px; border-radius:12px; font-size:.78rem; font-weight:bold; }
        .badge-pending  { background:#fff3e0; color:#e65100; }
        .badge-executed { background:#e6f4ea; color:#2d7d46; }
        .badge-failed   { background:#fdecea; color:#c62828; }
        .notice { background:#e6f4ea; color:#2d7d46; padding:10px 14px; border-radius:6px; margin-bottom:16px; font-size:.88rem; }
        .error  { background:#fdecea; color:#c62828; padding:10px 14px; border-radius:6px; margin-bottom:16px; font-size:.88rem; }
        .logout-form { display:inline; }
        .logout-btn { background:transparent; border:1px solid rgba(255,255,255,.6); color:#fff; padding:5px 14px; border-radius:20px; cursor:pointer; font-size:.85rem; }
        .logout-btn:hover { background:rgba(255,255,255,.15); }
    </style>
</head>
<body>

<header>
    <h1>⚡ Remote Command Queue</h1>
    <nav>
        <a href="/dashboard.php">📊 Dashboard</a>
        <a href="/cms.php">📁 Media Manager</a>
        <form class="logout-form" method="POST" action="/logout.php">
            <?php echo csrf_field(); ?>
            <button class="logout-btn" type="submit">Sign Out</button>
        </form>
    </nav>
</header>

<div class="container">

    <?php if ($notice): ?>
        <div class="notice"><?php echo e($notice); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="error"><?php echo e($error); ?></div>
    <?php endif; ?>

    <!-- ── Send command ─────────────────────────────────────────────── -->
    <div class="card">
        <h2>Send Command</h2>
        <form method="POST" action="/api/send_command.php">
            <?php echo csrf_field(); ?>
            <div class="form-row">
                <div>
                    <label for="device_id">Target Device</label>
                    <select name="device_id" id="device_id" required>
                        <option value="*">📡 All Devices (Broadcast)</option>
                        <?php foreach ($devices as $dev): ?>
                            <option value="<?php echo e($dev['id']); ?>">
                                <?php echo e($dev['name'] ?? $dev['id']); ?> (<?php echo e($dev['id']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="command">Command</label>
                    <select name="command" id="command" required>
                        <?php foreach ($AVAILABLE_COMMANDS as $cmd): ?>
                            <option value="<?php echo e($cmd); ?>"><?php echo e($cmd); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="payload">Payload (JSON, optional)</label>
                    <input type="text" name="payload" id="payload" placeholder='{"key":"value"}' style="min-width:240px">
                </div>
                <div>
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary">Send Command</button>
                </div>
            </div>
        </form>
    </div>

    <!-- ── Command history ──────────────────────────────────────────── -->
    <div class="card">
        <h2>Command History (Last 100)</h2>
        <?php if (empty($history)): ?>
            <p style="color:#888">No commands sent yet.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Device</th>
                    <th>Command</th>
                    <th>Payload</th>
                    <th>Status</th>
                    <th>Queued At</th>
                    <th>Executed At</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $row): ?>
                <tr>
                    <td><?php echo (int)$row['id']; ?></td>
                    <td><?php echo e($row['device_id']); ?></td>
                    <td><strong><?php echo e($row['command']); ?></strong></td>
                    <td><?php echo $row['payload'] ? e($row['payload']) : '—'; ?></td>
                    <td>
                        <span class="badge badge-<?php echo e($row['status']); ?>">
                            <?php echo e(strtoupper($row['status'])); ?>
                        </span>
                    </td>
                    <td><?php echo e($row['created_at']); ?></td>
                    <td><?php echo $row['executed_at'] ? e($row['executed_at']) : '—'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
