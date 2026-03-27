<?php
/**
 * config.php – central configuration & PDO database factory.
 *
 * Include this file at the top of every server-side script.
 */

// ── Paths ──────────────────────────────────────────────────────────────────
define('ROOT_DIR',   __DIR__);
define('MEDIA_DIR',  ROOT_DIR . '/media/');
define('DB_PATH',    ROOT_DIR . '/db/kiosk.db');

// ── Application settings ───────────────────────────────────────────────────
define('SESSION_NAME',     'kiosk_session');
define('CSRF_TOKEN_NAME',  'csrf_token');

// Device considered offline after this many seconds without a heartbeat
define('HEARTBEAT_TIMEOUT', 90);

// Allowed media extensions
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'webm', 'mov']);
define('MAX_UPLOAD_SIZE',    524288000); // 500 MB in bytes

/**
 * Return a singleton PDO connection to the SQLite database.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dbDir = dirname(DB_PATH);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }

        $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // Enable WAL mode and foreign keys on every connection
        $pdo->exec("PRAGMA journal_mode = WAL");
        $pdo->exec("PRAGMA foreign_keys = ON");
    }

    return $pdo;
}
