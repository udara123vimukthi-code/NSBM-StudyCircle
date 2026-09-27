<?php
require_once __DIR__ . '/config/auth.php';
$user = require_admin();

function post_text($key) {
    return isset($_POST[$key]) && is_string($_POST[$key])
        ? trim($_POST[$key])
        : '';
}

$errors = [];
$id = 0;
$code = '';
$name = '';

$success = $_SESSION['subject_success'] ?? '';
unset($_SESSION['subject_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post_text('action');

    if (!valid_csrf()) {
        $errors[] = 'Your form expired. Please try again.';
    } elseif ($action === 'save') {
        $submittedId = filter_var(
            post_text('id'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0]]
        );

        $id = $submittedId === false ? 0 : $submittedId;
        $code = strtoupper(post_text('subject_code'));
        $name = post_text('subject_name');

        if ($submittedId === false) {
            $errors[] = 'Invalid subject ID.';
        }

        if ($code === '' || mb_strlen($code) > 30) {
            $errors[] = 'Enter a subject code of up to 30 characters.';
        }

        if ($name === '' || mb_strlen($name) > 150) {
            $errors[] = 'Enter a subject name of up to 150 characters.';
        }

        if (!$errors) {
            try {
                if ($id > 0) {
                    $check = $pdo->prepare(
                        'SELECT id FROM subjects WHERE id = ?'
                    );
                    $check->execute([$id]);

                    if (!$check->fetch()) {
                        $errors[] = 'This subject no longer exists.';
                    } else {
                        $stmt = $pdo->prepare(
                            'UPDATE subjects
                             SET subject_code = ?, subject_name = ?
                             WHERE id = ?'
                        );
                        $stmt->execute([$code, $name, $id]);
                        $message = 'Subject updated successfully.';
                    }
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO subjects (subject_code, subject_name)
                         VALUES (?, ?)'
                    );
                    $stmt->execute([$code, $name]);
                    $message = 'Subject added successfully.';
                }

                if (!$errors) {
                    $_SESSION['subject_success'] = $message;
                    header('Location: admin_subjects.php');
                    exit;
                }
            } catch (PDOException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                    $errors[] = 'That subject code already exists.';
                } else {
                    error_log($e->getMessage());
                    $errors[] = 'Unable to save the subject. Please try again.';
                }
            }
        }
    } elseif ($action === 'delete') {
        $deleteId = filter_var(
            post_text('id'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($deleteId === false) {
            $errors[] = 'Invalid subject ID.';
        } else {
            try {
                $stmt = $pdo->prepare(
                    'DELETE FROM subjects WHERE id = ?'
                );
                $stmt->execute([$deleteId]);

                $_SESSION['subject_success'] = $stmt->rowCount()
                    ? 'Subject deleted successfully.'
                    : 'That subject has already been removed.';

                header('Location: admin_subjects.php');
                exit;
            } catch (PDOException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) === 1451) {
                    $errors[] =
                        'This subject is in use and cannot be deleted.';
                } else {
                    error_log($e->getMessage());
                    $errors[] = 'Unable to delete the subject.';
                }
            }
        }
    } else {
        $errors[] = 'Invalid action.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edit'])) {
    $editId = filter_var(
        $_GET['edit'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($editId === false) {
        http_response_code(400);
        exit('Invalid subject ID.');
    }

    $stmt = $pdo->prepare('SELECT * FROM subjects WHERE id = ?');
    $stmt->execute([$editId]);
    $subject = $stmt->fetch();

    if (!$subject) {
        http_response_code(404);
        exit('Subject not found.');
    }

    $id = (int) $subject['id'];
    $code = $subject['subject_code'];
    $name = $subject['subject_name'];
}

$subjects = $pdo->query(
    'SELECT id, subject_code, subject_name
     FROM subjects ORDER BY subject_code'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subjects | NSBM StudyCircle</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #eff8f2;
            color: #173e2c;
        }
        header {
            background: white;
            padding: 20px 6%;
            border-bottom: 1px solid #dce9df;
        }
        a { color: #146b3d; }
        .logo {
            font-size: 22px;
            font-weight: bold;
            text-decoration: none;
        }
        main {
            max-width: 1050px;
            margin: auto;
            padding: 32px 20px;
        }
        .intro { color: #52685b; line-height: 1.6; }
        .card {
            background: white;
            padding: 26px;
            margin-top: 24px;
            border: 1px solid #dce9df;
            border-radius: 16px;
        }
        label {
            display: block;
            margin: 18px 0 8px;
            font-weight: bold;
        }
        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #b6cabb;
            border-radius: 8px;
            font: inherit;
        }
        input:focus {
            outline: 3px solid #d1efdc;
            border-color: #16834a;
        }
        button {
            padding: 11px 16px;
            border: 0;
            border-radius: 8px;
            background: #16834a;
            color: white;
            cursor: pointer;
            font: inherit;
        }
        button:hover { background: #106337; }
        .form-actions {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-top: 22px;
        }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td {
            padding: 15px 12px;
            text-align: left;
            border-bottom: 1px solid #e2ece5;
        }
        th { background: #f0f8f3; }
        td { overflow-wrap: anywhere; }
        .actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
        }
        .delete {
            background: #fff0f0;
            color: #952525;
        }
        .delete:hover { background: #fbdede; }
        .message {
            padding: 15px;
            border-radius: 10px;
            line-height: 1.6;
        }
        .success { background: #dff3e6; color: #145c33; }
        .error { background: #fff0f0; color: #952525; }
        .error ul { margin: 0; padding-left: 20px; }
    </style>
</head>
<body>
<header>
    <a class="logo" href="admin_dashboard.php">StudyCircle Admin</a>
</header>

<main>
    <a href="admin_dashboard.php">&larr; Back to dashboard</a>
    <h1>Manage subjects</h1>
    <p class="intro">
        Organise the subjects students will use when sharing study materials.
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

    <section class="card" id="subject-form">
        <h2><?= $id > 0 ? 'Edit subject' : 'Add a subject' ?></h2>

        <form method="post" action="admin_subjects.php">
            <input type="hidden" name="csrf_token"
                   value="<?= escape(csrf_token()) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $id ?>">

            <label for="subject_code">Subject code</label>
            <input id="subject_code" name="subject_code"
                   value="<?= escape($code) ?>"
                   maxlength="30" placeholder="Example: DEMO101" required>

            <label for="subject_name">Subject name</label>
            <input id="subject_name" name="subject_name"
                   value="<?= escape($name) ?>"
                   maxlength="150"
                   placeholder="Example: Introduction to Computing" required>

            <div class="form-actions">
                <button type="submit">
                    <?= $id > 0 ? 'Save changes' : 'Add subject' ?>
                </button>

                <?php if ($id > 0): ?>
                    <a href="admin_subjects.php">Cancel editing</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="card">
        <h2>Subjects (<?= count($subjects) ?>)</h2>

        <?php if (!$subjects): ?>
            <p>No subjects yet. Add your first subject above.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th scope="col">Code</th>
                        <th scope="col">Subject name</th>
                        <th scope="col">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($subjects as $subject): ?>
                        <tr>
                            <td><?= escape($subject['subject_code']) ?></td>
                            <td><?= escape($subject['subject_name']) ?></td>
                            <td>
                                <div class="actions">
                                    <a href="admin_subjects.php?edit=<?= (int) $subject['id'] ?>#subject-form">
                                        Edit
                                    </a>

                                    <form method="post"
                                          action="admin_subjects.php"
                                          onsubmit="return confirm('Delete this subject? This cannot be undone.');">
                                        <input type="hidden" name="csrf_token"
                                               value="<?= escape(csrf_token()) ?>">
                                        <input type="hidden" name="action"
                                               value="delete">
                                        <input type="hidden" name="id"
                                               value="<?= (int) $subject['id'] ?>">
                                        <button class="delete" type="submit">
                                            Delete
                                        </button>
                                    </form>
                                </div>
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