<?php
/**
 * cms.php – Admin Media Manager.
 *
 * Allows authenticated admins to upload new media files and delete existing
 * ones. All changes are reflected in both the filesystem and the database.
 */

require_once __DIR__ . '/auth.php';
require_auth();

$pdo    = db();
$assets = $pdo->query("SELECT * FROM assets ORDER BY created_at DESC")->fetchAll();
$error  = '';
$notice = '';

// Retrieve flash messages stored in session
if (!empty($_SESSION['cms_notice'])) { $notice = $_SESSION['cms_notice']; unset($_SESSION['cms_notice']); }
if (!empty($_SESSION['cms_error']))  { $error  = $_SESSION['cms_error'];  unset($_SESSION['cms_error']);  }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Media Manager – Kiosk Admin</title>
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
        table { width:100%; border-collapse:collapse; font-size:.88rem; }
        th,td { text-align:left; padding:9px 10px; border-bottom:1px solid #eee; }
        th    { color:#555; font-weight:600; }
        .badge { background:#34a853; color:#fff; padding:2px 8px; border-radius:12px; font-size:.78rem; }
        .badge-video { background:#1a73e8; }
        .btn { padding:8px 18px; border-radius:6px; border:none; cursor:pointer; font-size:.88rem; }
        .btn-del { background:#ea4335; color:#fff; }
        .btn-del:hover { background:#c62828; }
        .btn-up  { background:#1a73e8; color:#fff; }
        .btn-up:hover  { background:#1558b0; }
        .notice { background:#e6f4ea; color:#2d7d46; padding:10px 14px; border-radius:6px; margin-bottom:16px; font-size:.88rem; }
        .error  { background:#fdecea; color:#c62828; padding:10px 14px; border-radius:6px; margin-bottom:16px; font-size:.88rem; }
        input[type=file] { font-size:.9rem; }
        .drop-area {
            border: 2px dashed #1a73e8; border-radius: 10px; padding: 30px;
            text-align: center; color: #1a73e8; cursor: pointer;
            transition: background .2s;
        }
        .drop-area.over { background: #e8f0fe; }
        .logout-form { display:inline; }
        .logout-btn { background:transparent; border:1px solid rgba(255,255,255,.6); color:#fff; padding:5px 14px; border-radius:20px; cursor:pointer; font-size:.85rem; }
        .logout-btn:hover { background:rgba(255,255,255,.15); }
    </style>
</head>
<body>

<header>
    <h1>📁 Media Manager</h1>
    <nav>
        <a href="/dashboard.php">📊 Dashboard</a>
        <a href="/commands.php">⚡ Commands</a>
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

    <!-- ── Upload ──────────────────────────────────────────────────── -->
    <div class="card">
        <h2>Upload New Media</h2>
        <form method="POST" action="/api/upload.php" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <div class="drop-area" id="drop-area" onclick="document.getElementById('fileInput').click()">
                <p>📤 Click or drag &amp; drop files here</p>
                <p style="font-size:.8rem;color:#555">
                    Allowed: jpg, jpeg, png, gif, mp4, webm, mov &nbsp;|&nbsp; Max <?php echo round(MAX_UPLOAD_SIZE / 1048576); ?> MB each
                </p>
                <input type="file" id="fileInput" name="media[]" multiple
                       accept=".jpg,.jpeg,.png,.gif,.mp4,.webm,.mov"
                       style="display:none" onchange="updateLabel(this)">
            </div>
            <p id="file-label" style="margin:8px 0;font-size:.85rem;color:#555">No files selected.</p>
            <button type="submit" class="btn btn-up">Upload</button>
        </form>
    </div>

    <!-- ── Asset list ──────────────────────────────────────────────── -->
    <div class="card">
        <h2>Media Library (<?php echo count($assets); ?> files)</h2>
        <?php if (empty($assets)): ?>
            <p style="color:#888">No media files uploaded yet.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Filename</th>
                    <th>Type</th>
                    <th>Path</th>
                    <th>Added</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assets as $asset): ?>
                <tr>
                    <td><strong><?php echo e($asset['filename']); ?></strong></td>
                    <td>
                        <span class="badge <?php echo $asset['type'] === 'video' ? 'badge-video' : ''; ?>">
                            <?php echo e($asset['type']); ?>
                        </span>
                    </td>
                    <td><a href="/<?php echo e($asset['filepath']); ?>" target="_blank" rel="noopener"><?php echo e($asset['filepath']); ?></a></td>
                    <td><?php echo e($asset['created_at']); ?></td>
                    <td>
                        <form method="POST" action="/api/delete_asset.php"
                              onsubmit="return confirm('Delete <?php echo e(addslashes($asset['filename'])); ?>?')">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="asset_id" value="<?php echo (int)$asset['id']; ?>">
                            <button type="submit" class="btn btn-del">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<script>
const dropArea = document.getElementById('drop-area');

['dragenter','dragover'].forEach(ev => {
    dropArea.addEventListener(ev, e => { e.preventDefault(); dropArea.classList.add('over'); });
});
['dragleave','drop'].forEach(ev => {
    dropArea.addEventListener(ev, e => { e.preventDefault(); dropArea.classList.remove('over'); });
});
dropArea.addEventListener('drop', e => {
    const input = document.getElementById('fileInput');
    // DataTransfer items → FileList replacement
    const dt    = e.dataTransfer;
    if (dt.files.length) {
        // Assign to input using a DataTransfer shim (modern browsers support this)
        input.files = dt.files;
        updateLabel(input);
    }
});

function updateLabel(input) {
    const label = document.getElementById('file-label');
    if (input.files.length === 0) {
        label.textContent = 'No files selected.';
    } else {
        const names = Array.from(input.files).map(f => f.name).join(', ');
        label.textContent = `Selected (${input.files.length}): ${names}`;
    }
}
</script>
</body>
</html>
