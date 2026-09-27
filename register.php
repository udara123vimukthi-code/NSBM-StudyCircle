<?php
session_start();
require_once __DIR__ . '/config/db.php';

function escape($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$fullName = '';
$email = '';
$studentId = '';

$success = $_SESSION['registration_success'] ?? '';
unset($_SESSION['registration_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $studentId = trim((string) ($_POST['student_id'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
    $token = (string) ($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $errors[] = 'Your form expired. Please try again.';
    }

    if ($fullName === '' || mb_strlen($fullName) > 100) {
        $errors[] = 'Enter your full name using up to 100 characters.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($email) > 254) {
        $errors[] = 'Enter a valid email address.';
    }

    if ($studentId === '' || mb_strlen($studentId) > 50) {
        $errors[] = 'Enter your student ID using up to 50 characters.';
    }

    if (strlen($password) < 8 || strlen($password) > 72) {
        $errors[] = 'Use a password of 8–72 bytes (ordinary English characters use one byte each).';
    }

    if (strpos($password, "\0") !== false) {
        $errors[] = 'Your password contains an unsupported character.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'The passwords do not match.';
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO users
                    (full_name, email, student_id, password_hash, role, status)
                 VALUES
                    (:full_name, :email, :student_id, :password_hash,
                     'student', 'active')"
            );

            $stmt->execute([
                'full_name' => $fullName,
                'email' => $email,
                'student_id' => $studentId,
                'password_hash' => password_hash(
                    $password,
                    PASSWORD_BCRYPT
                )
            ]);

            $_SESSION['registration_success'] =
                'Account created successfully! Your student account is ready.';

            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            header('Location: register.php');
            exit;
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                $errors[] = 'That email or student ID is already registered.';
            } else {
                error_log($e->getMessage());
                $errors[] = 'Registration failed. Please try again later.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account | NSBM StudyCircle</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 32px 16px;
            background: #eff8f2;
            color: #173e2c;
            font-family: Arial, sans-serif;
        }
        main {
            max-width: 520px;
            margin: 0 auto;
            padding: 36px;
            background: #fff;
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
        h1 {
            margin-bottom: 10px;
            font-size: 32px;
        }
        .intro {
            color: #5a6f62;
            line-height: 1.6;
            margin-bottom: 28px;
        }
        label {
            display: block;
            margin: 18px 0 8px;
            font-size: 14px;
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
        .hint {
            color: #5a6f62;
            font-size: 12px;
            line-height: 1.5;
        }
        button {
            width: 100%;
            margin-top: 24px;
            padding: 14px;
            background: #16834a;
            color: white;
            border: 0;
            border-radius: 9px;
            font: inherit;
            font-weight: bold;
            cursor: pointer;
        }
        button:hover { background: #106337; }
        button:focus-visible, a:focus-visible {
            outline: 3px solid #16834a;
            outline-offset: 4px;
        }
        .message {
            padding: 14px;
            border-radius: 9px;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }
        .success { background: #e4f6eb; color: #145c33; }
        .error { background: #fff0f0; color: #952525; }
        .error ul { margin: 0; padding-left: 20px; }
        .back {
            display: block;
            margin-top: 24px;
            text-align: center;
            color: #146b3d;
        }
        @media (max-width: 480px) {
            main { padding: 26px 20px; }
            h1 { font-size: 28px; }
        }
    </style>
</head>
<body>
<main>
    <p class="brand">NSBM GREEN UNIVERSITY</p>
    <h1>Join StudyCircle</h1>
    <p class="intro">
        Create your student account to share notes,
        ask questions, and learn together.
    </p>

    <?php if ($success): ?>
        <p class="message success" role="status">
            <?= escape($success) ?>
        </p>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="message error" role="alert">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= escape($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="register.php">
        <input type="hidden" name="csrf_token"
               value="<?= escape($_SESSION['csrf_token']) ?>">

        <label for="full_name">Full name</label>
        <input type="text" id="full_name" name="full_name"
               value="<?= escape($fullName) ?>"
               maxlength="100" autocomplete="name" required>

        <label for="student_id">Student ID</label>
        <input type="text" id="student_id" name="student_id"
               value="<?= escape($studentId) ?>"
               maxlength="50" required>

        <label for="email">Email address</label>
        <input type="email" id="email" name="email"
               value="<?= escape($email) ?>"
               maxlength="254" autocomplete="email" required>

        <label for="password">Password</label>
        <input type="password" id="password" name="password"
               minlength="8" maxlength="72"
               autocomplete="new-password"
               aria-describedby="password_hint" required>
        <p class="hint" id="password_hint">
            Use at least 8 characters. A longer, unique password is better.
            Maximum: 72 bytes.
        </p>

        <label for="confirm_password">Confirm password</label>
        <input type="password" id="confirm_password"
               name="confirm_password"
               minlength="8" maxlength="72"
               autocomplete="new-password" required>

        <button type="submit">Create student account</button>
    </form>

    <a class="back" href="index.php">Back to homepage</a>
</main>
</body>
</html>