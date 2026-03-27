<?php
/**
 * db/init_db.php
 *
 * One-time initialisation script.
 * Run from the project root:  php db/init_db.php
 *
 * Creates kiosk.db and seeds a default admin account.
 */

require_once __DIR__ . '/../config.php';

// Apply schema
$sql = file_get_contents(__DIR__ . '/schema.sql');
$pdo = db();
$pdo->exec($sql);

// Seed a default admin user if none exists
$stmt = $pdo->query("SELECT COUNT(*) FROM admin_users");
if ((int)$stmt->fetchColumn() === 0) {
    $username = 'admin';
    $password = bin2hex(random_bytes(8)); // 16-char random hex password
    $hash     = password_hash($password, PASSWORD_BCRYPT);

    $ins = $pdo->prepare("INSERT INTO admin_users (username, password_hash) VALUES (:u, :h)");
    $ins->execute([':u' => $username, ':h' => $hash]);

    echo "Database initialised.\n";
    echo "Default admin created  →  username: admin  |  password: {$password}\n";
    echo "IMPORTANT: Store this password securely – it will not be shown again.\n";
} else {
    echo "Database already initialised.\n";
}
