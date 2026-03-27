<?php
/**
 * login.php – Admin login page.
 */

require_once __DIR__ . '/auth.php';

session_boot();

// Already logged in → go to dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: /dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } elseif (!attempt_login($username, $password)) {
        // Constant-time delay to slow brute-force
        sleep(1);
        $error = 'Invalid username or password.';
    } else {
        header('Location: /dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kiosk Admin – Login</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f0f2f5;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }
        .card {
            background: #fff;
            padding: 40px 36px;
            border-radius: 12px;
            box-shadow: 0 6px 24px rgba(0,0,0,.1);
            width: 100%;
            max-width: 380px;
        }
        h1 { margin: 0 0 24px; font-size: 1.4rem; color: #1a73e8; text-align: center; }
        label { display: block; font-size: .85rem; font-weight: 600; margin-bottom: 6px; color: #444; }
        input[type=text], input[type=password] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: .95rem;
            margin-bottom: 16px;
            outline: none;
            transition: border-color .2s;
        }
        input:focus { border-color: #1a73e8; }
        .btn {
            width: 100%;
            padding: 11px;
            background: #1a73e8;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
            transition: background .2s;
        }
        .btn:hover { background: #1558b0; }
        .error {
            background: #fdecea;
            color: #c62828;
            padding: 10px 14px;
            border-radius: 6px;
            margin-bottom: 16px;
            font-size: .88rem;
        }
    </style>
</head>
<body>
<div class="card">
    <h1>🖥️ Kiosk Admin</h1>

    <?php if ($error): ?>
        <div class="error"><?php echo e($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="/login.php" novalidate>
        <?php echo csrf_field(); ?>

        <label for="username">Username</label>
        <input type="text" id="username" name="username"
               value="<?php echo e($_POST['username'] ?? ''); ?>"
               autocomplete="username" autofocus required>

        <label for="password">Password</label>
        <input type="password" id="password" name="password"
               autocomplete="current-password" required>

        <button type="submit" class="btn">Sign In</button>
    </form>
</div>
</body>
</html>
