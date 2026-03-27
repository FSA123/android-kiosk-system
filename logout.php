<?php
/**
 * logout.php – Destroys the admin session and redirects to login.
 */

require_once __DIR__ . '/auth.php';

verify_csrf();
logout();

header('Location: /login.php');
exit;
