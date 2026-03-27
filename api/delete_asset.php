<?php
/**
 * api/delete_asset.php – Deletes a media asset from the filesystem and DB.
 *
 * POST fields:
 *   asset_id – integer ID of the asset to delete
 *
 * Cascades: playback_logs rows referencing this asset are removed by the FK
 * ON DELETE CASCADE defined in the schema.
 */

require_once __DIR__ . '/../auth.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

verify_csrf();

$assetId = (int)($_POST['asset_id'] ?? 0);

if ($assetId <= 0) {
    session_boot();
    $_SESSION['cms_error'] = 'Invalid asset ID.';
    header('Location: /cms.php');
    exit;
}

$pdo  = db();
$stmt = $pdo->prepare("SELECT filename, filepath FROM assets WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $assetId]);
$asset = $stmt->fetch();

if (!$asset) {
    session_boot();
    $_SESSION['cms_error'] = 'Asset not found.';
    header('Location: /cms.php');
    exit;
}

// Remove from filesystem
$fullPath = ROOT_DIR . '/' . $asset['filepath'];
if (file_exists($fullPath)) {
    unlink($fullPath);
}

// Remove from database (cascade removes playback_logs rows)
$del = $pdo->prepare("DELETE FROM assets WHERE id = :id");
$del->execute([':id' => $assetId]);

session_boot();
$_SESSION['cms_notice'] = "Asset '{$asset['filename']}' deleted successfully.";
header('Location: /cms.php');
exit;
