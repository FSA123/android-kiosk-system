<?php
/**
 * dashboard.php – Real-time analytics dashboard.
 *
 * Requires authentication. Live playback feed delivered via SSE (sse.php).
 */

require_once __DIR__ . '/auth.php';
require_auth();

$pdo = db();

// ── Aggregate daily plays per asset (last 30 days) ─────────────────────────
$rows = $pdo->query("
    SELECT
        date(pl.played_at) AS day,
        a.filename          AS media,
        COUNT(*)            AS plays
    FROM playback_logs pl
    JOIN assets a ON a.id = pl.asset_id
    WHERE pl.played_at >= date('now', '-30 days')
    GROUP BY day, a.filename
    ORDER BY day ASC
")->fetchAll();

$dailyStats  = [];
$mediaTotals = [];

foreach ($rows as $row) {
    $dailyStats[$row['day']][$row['media']] = (int)$row['plays'];
    $mediaTotals[$row['media']] = ($mediaTotals[$row['media']] ?? 0) + (int)$row['plays'];
}

$labels       = array_keys($dailyStats);
$allMediaNames = array_keys($mediaTotals);
arsort($mediaTotals);
$grandTotal = array_sum($mediaTotals);

// ── Recent individual log entries ──────────────────────────────────────────
$recentLogs = $pdo->query("
    SELECT pl.id, pl.played_at, pl.device_id, a.filename
    FROM playback_logs pl
    JOIN assets a ON a.id = pl.asset_id
    ORDER BY pl.id DESC
    LIMIT 50
")->fetchAll();

// ── Device health ──────────────────────────────────────────────────────────
$devices = $pdo->query("
    SELECT id, name, ip, last_seen,
           CASE
               WHEN last_seen IS NULL                                  THEN 'never'
           WHEN (strftime('%s','now') - strftime('%s', last_seen)) <= " . (int)HEARTBEAT_TIMEOUT . " THEN 'online'
               ELSE 'offline'
           END AS health
    FROM devices
    ORDER BY name ASC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kiosk Analytics Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body   { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; margin: 0; color: #333; }
        header {
            background: #1a73e8; color: #fff; padding: 14px 24px;
            display: flex; align-items: center; justify-content: space-between;
        }
        header h1 { margin: 0; font-size: 1.2rem; }
        header nav a {
            color: #fff; text-decoration: none; margin-left: 18px;
            font-size: .9rem; opacity: .85;
        }
        header nav a:hover { opacity: 1; }
        .container { max-width: 1200px; margin: 24px auto; padding: 0 16px; }
        .grid2 { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-top: 20px; }
        .card  {
            background: #fff; padding: 20px; border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,.06);
        }
        h2 { margin-top: 0; color: #1a73e8; font-size: 1.1rem; }
        table  { width: 100%; border-collapse: collapse; font-size: .88rem; }
        th, td { text-align: left; padding: 9px 10px; border-bottom: 1px solid #eee; }
        th     { color: #555; font-weight: 600; }
        .badge {
            background: #1a73e8; color: #fff;
            padding: 2px 8px; border-radius: 12px; font-weight: bold; font-size: .8rem;
        }
        .dot {
            display: inline-block; width: 10px; height: 10px;
            border-radius: 50%; margin-right: 6px;
        }
        .dot-online  { background: #34a853; }
        .dot-offline { background: #ea4335; }
        .dot-never   { background: #bbb; }
        #live-feed { max-height: 280px; overflow-y: auto; }
        #live-feed tr:first-child { animation: fadeIn .4s ease; }
        @keyframes fadeIn { from { background: #e8f0fe; } to { background: transparent; } }
        .sse-status {
            font-size: .78rem; padding: 4px 10px; border-radius: 20px;
            display: inline-block; margin-bottom: 8px;
        }
        .sse-status.connected    { background: #e6f4ea; color: #2d7d46; }
        .sse-status.disconnected { background: #fce8e6; color: #c62828; }
        .logout-form { display: inline; }
        .logout-btn {
            background: transparent; border: 1px solid rgba(255,255,255,.6);
            color: #fff; padding: 5px 14px; border-radius: 20px;
            cursor: pointer; font-size: .85rem;
        }
        .logout-btn:hover { background: rgba(255,255,255,.15); }
    </style>
</head>
<body>

<header>
    <h1>📊 Kiosk Analytics Dashboard</h1>
    <nav>
        <a href="/cms.php">📁 Media Manager</a>
        <a href="/commands.php">⚡ Commands</a>
        <form class="logout-form" method="POST" action="/logout.php">
            <?php echo csrf_field(); ?>
            <button class="logout-btn" type="submit">Sign Out</button>
        </form>
    </nav>
</header>

<div class="container">

    <!-- ── Daily trend chart ─────────────────────────────────────────── -->
    <div class="card">
        <h2>Daily Play Trends (Last 30 Days)</h2>
        <canvas id="mainChart" height="90"></canvas>
    </div>

    <!-- ── Summary + top content ─────────────────────────────────────── -->
    <div class="grid2">
        <div class="card">
            <h2>Top Content (Total Plays)</h2>
            <?php if (empty($mediaTotals)): ?>
                <p style="color:#888">No playback data yet.</p>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Filename</th>
                        <th>Total Plays</th>
                        <th>Share</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mediaTotals as $name => $count):
                        $pct = $grandTotal > 0 ? round(($count / $grandTotal) * 100, 1) : 0;
                    ?>
                    <tr>
                        <td><strong><?php echo e($name); ?></strong></td>
                        <td><span class="badge"><?php echo $count; ?></span></td>
                        <td><?php echo $pct; ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <div class="card" style="text-align:center">
            <h2>Grand Total</h2>
            <div style="font-size:3rem;font-weight:bold;color:#1a73e8;margin:20px 0">
                <?php echo $grandTotal; ?>
            </div>
            <p>plays recorded</p>
        </div>
    </div>

    <!-- ── Device health ──────────────────────────────────────────────── -->
    <div class="card" style="margin-top:20px">
        <h2>Device Health</h2>
        <?php if (empty($devices)): ?>
            <p style="color:#888">No devices have checked in yet.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Device ID</th>
                    <th>Name</th>
                    <th>IP Address</th>
                    <th>Last Seen</th>
                </tr>
            </thead>
            <tbody id="device-rows">
                <?php foreach ($devices as $dev): ?>
                <tr>
                    <td>
                        <span class="dot dot-<?php echo e($dev['health']); ?>"></span>
                        <?php echo e(ucfirst($dev['health'])); ?>
                    </td>
                    <td><?php echo e($dev['id']); ?></td>
                    <td><?php echo e($dev['name'] ?? '—'); ?></td>
                    <td><?php echo e($dev['ip']  ?? '—'); ?></td>
                    <td><?php echo $dev['last_seen'] ? e($dev['last_seen']) : '—'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- ── Live playback feed (SSE) ───────────────────────────────────── -->
    <div class="card" style="margin-top:20px">
        <h2>
            Live Playback Feed
            <span id="sse-status" class="sse-status disconnected">Connecting…</span>
        </h2>
        <table>
            <thead>
                <tr><th>Time</th><th>Device</th><th>Media</th></tr>
            </thead>
            <tbody id="live-feed">
                <?php foreach ($recentLogs as $log): ?>
                <tr>
                    <td><?php echo e($log['played_at']); ?></td>
                    <td><?php echo e($log['device_id']); ?></td>
                    <td><?php echo e($log['filename']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div><!-- /container -->

<script>
// ── Chart ────────────────────────────────────────────────────────────────
const labels       = <?php echo json_encode($labels); ?>;
const allMedia     = <?php echo json_encode($allMediaNames); ?>;
const dailyStats   = <?php echo json_encode($dailyStats); ?>;

const datasets = allMedia.map((name, i) => ({
    label: name,
    data:  labels.map(day => (dailyStats[day] && dailyStats[day][name]) ? dailyStats[day][name] : 0),
    backgroundColor: `hsla(${(i * 47) % 360},70%,50%,.45)`,
    borderColor:     `hsla(${(i * 47) % 360},70%,50%,1)`,
    borderWidth: 2,
    tension: 0.3,
    fill: false,
}));

new Chart(document.getElementById('mainChart').getContext('2d'), {
    type: 'line',
    data: { labels, datasets },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } },
        scales:  { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
    },
});

// ── Server-Sent Events – live feed ───────────────────────────────────────
(function connectSSE() {
    const feed      = document.getElementById('live-feed');
    const statusEl  = document.getElementById('sse-status');
    const MAX_ROWS  = 100;

    let es;

    function connect() {
        es = new EventSource('/sse.php');

        es.addEventListener('open', () => {
            statusEl.textContent = 'Live';
            statusEl.className   = 'sse-status connected';
        });

        es.addEventListener('log', (ev) => {
            try {
                const d  = JSON.parse(ev.data);
                const tr = document.createElement('tr');
                tr.innerHTML = `<td>${escHtml(d.played_at)}</td><td>${escHtml(d.device_id)}</td><td>${escHtml(d.filename)}</td>`;
                feed.insertBefore(tr, feed.firstChild);
                // Trim old rows
                while (feed.rows.length > MAX_ROWS) feed.deleteRow(-1);
            } catch(_) {}
        });

        es.addEventListener('error', () => {
            statusEl.textContent = 'Reconnecting…';
            statusEl.className   = 'sse-status disconnected';
            es.close();
            setTimeout(connect, 5000);
        });
    }

    connect();
})();

function escHtml(s) {
    return String(s)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>

</body>
</html>