<?php
/**
 * auth.php – authentication & CSRF helper functions.
 *
 * Include after config.php in every protected page.
 */

require_once __DIR__ . '/config.php';

// ── Session bootstrap ──────────────────────────────────────────────────────

function session_boot(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

// ── Authentication ─────────────────────────────────────────────────────────

/**
 * Redirect to login page if the current session has no authenticated user.
 */
function require_auth(): void
{
    session_boot();
    if (empty($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
}

/**
 * Attempt to log in with the supplied credentials.
 * Returns true on success and populates $_SESSION, false otherwise.
 */
function attempt_login(string $username, string $password): bool
{
    $stmt = db()->prepare("SELECT id, password_hash FROM admin_users WHERE username = :u LIMIT 1");
    $stmt->execute([':u' => $username]);
    $row = $stmt->fetch();

    if ($row && password_verify($password, $row['password_hash'])) {
        session_boot();
        session_regenerate_id(true);
        $_SESSION['user_id']  = $row['id'];
        $_SESSION['username'] = $username;
        return true;
    }
    return false;
}

/**
 * Destroy the current session (logout).
 */
function logout(): void
{
    session_boot();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ── CSRF protection ────────────────────────────────────────────────────────

/**
 * Return (and lazily create) the session-scoped CSRF token.
 */
function csrf_token(): string
{
    session_boot();
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Emit a hidden <input> field containing the CSRF token.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . csrf_token() . '">';
}

/**
 * Validate the CSRF token sent with a POST request.
 * Terminates the request with 403 on failure.
 */
function verify_csrf(): void
{
    session_boot();
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        exit('CSRF token mismatch.');
    }
}

// ── Output escaping ────────────────────────────────────────────────────────

/**
 * Escape a string for safe HTML output (XSS prevention).
 */
function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
