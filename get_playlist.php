<?php
/**
 * get_playlist.php – Returns the current media playlist as JSON.
 *
 * The tablet calls this endpoint to fetch (or refresh) its playlist.
 * Falls back to scanning the media/ directory if the database is empty,
 * maintaining backward compatibility during the migration.
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

try {
    $pdo  = db();
    $rows = $pdo->query("SELECT filename, filepath, type FROM assets ORDER BY filename ASC")->fetchAll();

    if (!empty($rows)) {
        $playlist = array_map(fn($r) => [
            'type' => $r['type'],
            'src'  => '/' . ltrim($r['filepath'], '/'),
        ], $rows);
        echo json_encode($playlist);
        exit;
    }
} catch (PDOException $e) {
    // Fall through to filesystem scan on DB error
}

// ── Filesystem fallback (pre-migration / DB unavailable) ─────────────────
$dir      = MEDIA_DIR;
$playlist = [];

if (is_dir($dir)) {
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            $playlist[] = ['type' => 'image', 'src' => '/media/' . $file];
        } elseif (in_array($ext, ['mp4', 'webm', 'mov'])) {
            $playlist[] = ['type' => 'video', 'src' => '/media/' . $file];
        }
    }
}

echo json_encode($playlist);
