<?php
require_once __DIR__ . '/config/auth.php';
$admin = require_admin();

$error = '';
$success = $_SESSION['user_management_success'] ?? '';
unset($_SESSION['user_management_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_var(
        $_POST['user_id'] ?? '',
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    $status = isset($_POST['status']) && is_string($_POST['status'])
        ? $_POST['status']
        : '';

    if (!valid_csrf()) {
        $error = 'Your form expired. Please refresh and try again.';
    } elseif (
        $id === false
        || !in_array($status, ['active', 'blocked'], true)
    ) {
        $error = 'Invalid account update.';
    } else {
        try {
            // Only student accounts can be changed here.
            $stmt = $pdo->prepare(
                "SELECT id FROM users
                 WHERE id = ? AND role = 'student'"
            );
            $stmt->execute([$id]);

            if (!$stmt->fetch()) {
                $error = 'Student account not found.';
            } else {
                $stmt = $pdo->prepare(
                    "UPDATE users SET status = ?
                     WHERE id = ? AND role = 'student'"
                );
                $stmt->execute([$status, $id]);

                $_SESSION['user_management_success'] =
                    $status === 'blocked'
                    ? 'Student account blocked.'
                    : 'Student account reactivated.';

                header('Location: admin_users.php');
                exit;
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = 'Unable to update the account. Please try again.';
        }
    }
}

$search = isset($_GET['q']) && is_string($_GET['q'])
    ? mb_substr(trim($_GET['q']), 0, 150)
    : '';

$statusFilter = isset($_GET['status']) && is_string($_GET['status'])
    ? $_GET['status']
    : '';

if (!in_array($statusFilter, ['', 'active', 'blocked'], true)) {
    $statusFilter = '';
}

$sql = "
    SELECT id, full_name, email, student_id, status, created_at
    FROM users
    WHERE role = 'student'
";
$params = [];

if ($search !== '') {
    $term = '%' . str_replace(
        ['!', '%', '_'],
        ['!!', '!%', '!_'],
        $search
    ) . '%';

    $sql .= "
        AND (
            full_name LIKE ? ESCAPE '!'
            OR email LIKE ? ESCAPE '!'
            OR student_id LIKE ? ESCAPE '!'
        )
    ";
    $params = [$term, $term, $term];
}

if ($statusFilter !== '') {
    $sql .= ' AND status = ?';
    $params[] = $statusFilter;
}

$sql .= ' ORDER BY created_at DESC, id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

$totals = $pdo->query(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(status = 'active'), 0) AS active_count,
            COALESCE(SUM(status = 'blocked'), 0) AS blocked_count
     FROM users WHERE role = 'student'"
)->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students | NSBM StudyCircle</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #eff8f2;
            color: #173e2c;
        }
        header {
            padding: 20px 6%;
            background: white;
            border-bottom: 1px solid #dce9df;
        }
        a { color: #146b3d; }
        .logo {
            font-size: 22px;
            font-weight: bold;
            text-decoration: none;
        }
        main {
            max-width: 1150px;
            margin: auto;
            padding: 32px 20px;
        }
        p { line-height: 1.6; }
        .stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            margin: 24px 0;
        }
        .stat, .card {
            padding: 24px;
            background: white;
            border: 1px solid #dce9df;
            border-radius: 16px;
        }
        .number {
            margin: 10px 0 0;
            font-size: 36px;
            font-weight: bold;
            color: #16834a;
        }
        .card { margin-top: 24px; }
        .filter-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 16px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }
        input, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #b6cabb;
            border-radius: 8px;
            background: white;
            color: #173e2c;
            font: inherit;
        }
        input:focus, select:focus {
            outline: 3px solid #d1efdc;
            border-color: #16834a;
        }
        .actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-top: 18px;
        }
        button {
            padding: 11px 16px;
            background: #16834a;
            color: white;
            border: 0;
            border-radius: 8px;
            font: inherit;
            cursor: pointer;
        }
        button:hover { background: #106337; }
        .block { background: #fff0f0; color: #952525; }
        .block:hover { background: #fbdede; }
        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 13px;
        }
        .active { background: #dff3e6; color: #145c33; }
        .blocked { background: #fff0f0; color: #952525; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td {
            padding: 14px 12px;
            text-align: left;
            border-bottom: 1px solid #e2ece5;
            vertical-align: top;
        }
        th { background: #f0f8f3; }
        td { overflow-wrap: anywhere; }
        .message { padding: 15px; border-radius: 10px; }
        .success { background: #dff3e6; color: #145c33; }
        .error { background: #fff0f0; color: #952525; }
        @media (max-width: 650px) {
            .stats, .filter-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<header>
    <a class="logo" href="admin_dashboard.php">StudyCircle Admin</a>
</header>

<main>
    <a href="admin_dashboard.php">&larr; Back to dashboard</a>
    <h1>Manage student accounts</h1>
    <p>Search students and manage their access to StudyCircle.</p>

    <?php if ($success): ?>
        <p class="message success" role="status">
            <?= escape($success) ?>
        </p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p class="message error" role="alert">
            <?= escape($error) ?>
        </p>
    <?php endif; ?>

    <section class="stats" aria-label="Student account totals">
        <article class="stat">
            <h2>Total students</h2>
            <p class="number"><?= (int) $totals['total'] ?></p>
        </article>
        <article class="stat">
            <h2>Active</h2>
            <p class="number"><?= (int) $totals['active_count'] ?></p>
        </article>
        <article class="stat">
            <h2>Blocked</h2>
            <p class="number"><?= (int) $totals['blocked_count'] ?></p>
        </article>
    </section>

    <form class="card" method="get" action="admin_users.php">
        <div class="filter-grid">
            <div>
                <label for="q">Find a student</label>
                <input type="search" id="q" name="q"
                       value="<?= escape($search) ?>"
                       maxlength="150"
                       placeholder="Name, email, or student ID">
            </div>

            <div>
                <label for="status">Account status</label>
                <select id="status" name="status">
                    <option value="">All students</option>
                    <option value="active"
                        <?= $statusFilter === 'active' ? 'selected' : '' ?>>
                        Active
                    </option>
                    <option value="blocked"
                        <?= $statusFilter === 'blocked' ? 'selected' : '' ?>>
                        Blocked
                    </option>
                </select>
            </div>
        </div>

        <div class="actions">
            <button type="submit">Search students</button>
            <a href="admin_users.php">Clear filters</a>
        </div>
    </form>

    <section class="card">
        <h2>Matching students (<?= count($students) ?>)</h2>

        <?php if (!$students): ?>
            <p>No students match your search.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th scope="col">Student</th>
                        <th scope="col">Email</th>
                        <th scope="col">Status</th>
                        <th scope="col">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td>
                                <strong>
                                    <?= escape($student['full_name']) ?>
                                </strong><br>
                                <?= escape($student['student_id']) ?>
                            </td>
                            <td><?= escape($student['email']) ?></td>
                            <td>
                                <span class="badge <?= escape($student['status']) ?>">
                                    <?= escape(ucfirst($student['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <?php $isActive = $student['status'] === 'active'; ?>

                                <form method="post" action="admin_users.php"
                                      onsubmit="return confirm('Change access for this student account?');">
                                    <input type="hidden" name="csrf_token"
                                           value="<?= escape(csrf_token()) ?>">
                                    <input type="hidden" name="user_id"
                                           value="<?= (int) $student['id'] ?>">
                                    <input type="hidden" name="status"
                                           value="<?= $isActive ? 'blocked' : 'active' ?>">

                                    <button type="submit"
                                            class="<?= $isActive ? 'block' : '' ?>">
                                        <?= $isActive ? 'Block account' : 'Reactivate' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>