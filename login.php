<?php
require_once __DIR__ . '/config/auth.php';

header('Cache-Control: no-store');

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = is_string($_POST['email'] ?? null)
        ? strtolower(trim($_POST['email']))
        : '';

    $password = is_string($_POST['password'] ?? null)
        ? $_POST['password']
        : '';

    if (!valid_csrf()) {
        $error = 'Your form expired. Please try again.';
    } elseif (
        !filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($email) > 254
        || $password === ''
        || strlen($password) > 72
        || strpos($password, "\0") !== false
    ) {
        $error = 'Enter a valid email address and password.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, password_hash, status,role
             FROM users WHERE email = ?'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (
            $user
            && password_verify($password, $user['password_hash'])
            && $user['status'] === 'active'
        ) {
            session_regenerate_id(true);

            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            if ($user['role'] === 'admin') {
    		header('Location: admin_dashboard.php');
	    } else {
		header('Location: dashboard.php');
	    }
	    exit;
        }

        $error = 'Unable to sign in. Check your details and account status.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | NSBM StudyCircle</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            font-family: Arial, sans-serif;
            background: #eff8f2;
            color: #173e2c;
        }
        main {
            width: 100%;
            max-width: 460px;
            padding: 36px;
            background: white;
            border-top: 6px solid #16834a;
            border-radius: 20px;
            box-shadow: 0 16px 48px #153d2a12;
        }
        .brand {
            color: #16834a;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 2px;
        }
        h1 { margin-bottom: 10px; }
        p { line-height: 1.6; }
        label {
            display: block;
            margin: 20px 0 8px;
            font-weight: bold;
        }
        input {
            width: 100%;
            padding: 13px;
            border: 1px solid #b6cabb;
            border-radius: 9px;
            font: inherit;
        }
        input:focus {
            outline: 3px solid #d1efdc;
            border-color: #16834a;
        }
        button {
            width: 100%;
            padding: 14px;
            margin-top: 24px;
            border: 0;
            border-radius: 9px;
            background: #16834a;
            color: white;
            font: inherit;
            font-weight: bold;
            cursor: pointer;
        }
        button:hover { background: #106337; }
        a { color: #146b3d; }
        .links { text-align: center; }
        .error {
            background: #fff0f0;
            color: #952525;
            padding: 14px;
            border-radius: 9px;
        }
    </style>
</head>
<body>
<main>
    <p class="brand">NSBM GREEN UNIVERSITY</p>
    <h1>Welcome back</h1>
    <p>Sign in to your StudyCircle account.</p>

    <?php if ($error): ?>
        <p class="error" role="alert"><?= escape($error) ?></p>
    <?php endif; ?>

    <form method="post" action="login.php">
        <input type="hidden" name="csrf_token"
               value="<?= escape(csrf_token()) ?>">

        <label for="email">Email address</label>
        <input type="email" id="email" name="email"
               value="<?= escape($email) ?>"
               maxlength="254" autocomplete="username" required>

        <label for="password">Password</label>
        <input type="password" id="password" name="password"
               maxlength="72" autocomplete="current-password" required>

        <button type="submit">Sign in</button>
    </form>

    <p class="links">
        New to StudyCircle?
        <a href="register.php">Create an account</a>
    </p>
    <p class="links"><a href="index.php">Back to homepage</a></p>
</main>
</body>
</html>