<?php
/**
 * api/upload.php – Handles multipart/form-data media uploads.
 *
 * Accepts one or more files in the 'media[]' field, saves them to the
 * media/ directory, and records each asset in the database.
 *
 * Redirects back to cms.php with a flash message on completion.
 */

require_once __DIR__ . '/../auth.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

verify_csrf();

$pdo = db();

if (!is_dir(MEDIA_DIR)) {
    mkdir(MEDIA_DIR, 0755, true);
}

$uploaded = 0;
$errors   = [];

$files = $_FILES['media'] ?? [];

// Normalise to an array of individual file entries
$count = is_array($files['name']) ? count($files['name']) : 0;

for ($i = 0; $i < $count; $i++) {
    $tmpPath  = $files['tmp_name'][$i];
    $origName = $files['name'][$i];
    $size     = (int)$files['size'][$i];
    $err      = (int)$files['error'][$i];

    if ($err !== UPLOAD_ERR_OK) {
        $errors[] = "Upload error for '{$origName}' (code {$err}).";
        continue;
    }

    if ($size > MAX_UPLOAD_SIZE) {
        $errors[] = "'{$origName}' exceeds the maximum upload size.";
        continue;
    }

    // Sanitise the filename: keep only safe characters
    $basename  = pathinfo($origName, PATHINFO_FILENAME);
    $ext       = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $safeName  = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $basename) . '.' . $ext;

    if (!in_array($ext, ALLOWED_EXTENSIONS, true)) {
        $errors[] = "'{$origName}' has a disallowed file type.";
        continue;
    }

    // Validate the file's MIME type using finfo (not just the extension)
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($tmpPath);
    $allowed  = [
        'image/jpeg', 'image/png', 'image/gif',
        'video/mp4', 'video/webm', 'video/quicktime',
    ];
    if (!in_array($mimeType, $allowed, true)) {
        $errors[] = "'{$origName}' has an invalid MIME type ({$mimeType}).";
        continue;
    }

    $type      = str_starts_with($mimeType, 'video/') ? 'video' : 'image';
    $destPath  = MEDIA_DIR . $safeName;
    $dbPath    = 'media/' . $safeName;

    // Avoid overwriting – append a counter if the file already exists
    $counter   = 1;
    while (file_exists($destPath)) {
        $safeName = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $basename) . "_{$counter}." . $ext;
        $destPath = MEDIA_DIR . $safeName;
        $dbPath   = 'media/' . $safeName;
        $counter++;
    }

    if (!move_uploaded_file($tmpPath, $destPath)) {
        $errors[] = "Could not save '{$origName}' to the media directory.";
        continue;
    }

    try {
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO assets (filename, filepath, type) VALUES (:fn, :fp, :t)");
        $stmt->execute([':fn' => $safeName, ':fp' => $dbPath, ':t' => $type]);
    } catch (PDOException $e) {
        $errors[] = "Database error for '{$origName}'.";
        // Remove uploaded file to keep FS and DB in sync
        @unlink($destPath);
        continue;
    }

    $uploaded++;
}

// Flash messages back to CMS
session_boot();
if ($uploaded > 0) {
    $_SESSION['cms_notice'] = "{$uploaded} file(s) uploaded successfully." .
        ($errors ? ' Some files had errors.' : '');
} elseif (!empty($errors)) {
    $_SESSION['cms_error'] = implode(' ', $errors);
}

header('Location: /cms.php');
exit;
