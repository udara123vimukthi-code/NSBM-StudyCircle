<?php
require_once __DIR__ . '/config/auth.php';
$user = require_login();

$dashboard = $user['role'] === 'admin'
    ? 'admin_dashboard.php'
    : 'dashboard.php';

function forum_input($key) {
    return isset($_POST[$key]) && is_string($_POST[$key])
        ? trim($_POST[$key])
        : '';
}

$subjects = $pdo->query(
    'SELECT id, subject_code, subject_name
     FROM subjects ORDER BY subject_code'
)->fetchAll();

$error = '';
$formId = 0;
$title = '';
$body = '';
$subjectId = 0;

$success = $_SESSION['forum_success'] ?? '';
unset($_SESSION['forum_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = forum_input('action');

    $id = filter_var(
        forum_input('id'),
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 0]]
    );

    if (!valid_csrf()) {
        $error = 'Your form expired. Please refresh and try again.';
    } elseif ($id === false) {
        $error = 'Invalid question ID.';
    } elseif ($action === 'save' && $user['role'] === 'student') {
        $formId = $id;
        $title = forum_input('title');
        $body = forum_input('body');

        $subjectId = filter_var(
            forum_input('subject_id'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        ) ?: 0;

        $subjectIds = array_map(
            'intval',
            array_column($subjects, 'id')
        );

        if ($title === '' || mb_strlen($title) > 150) {
            $error = 'Enter a question title of up to 150 characters.';
        } elseif ($body === '' || mb_strlen($body) > 5000) {
            $error = 'Enter question details of 1–5,000 characters.';
        } elseif (!in_array($subjectId, $subjectIds, true)) {
            $error = 'Select a valid subject.';
        } else {
            try {
                if ($id > 0) {
                    $check = $pdo->prepare(
                        'SELECT id FROM forum_questions
                         WHERE id = ? AND user_id = ?'
                    );
                    $check->execute([$id, $user['id']]);

                    if (!$check->fetch()) {
                        $error = 'Question not found in your posts.';
                    } else {
                        $stmt = $pdo->prepare(
                            'UPDATE forum_questions
                             SET title = ?, body = ?, subject_id = ?
                             WHERE id = ? AND user_id = ?'
                        );
                        $stmt->execute([
                            $title, $body, $subjectId, $id, $user['id']
                        ]);
                    }
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO forum_questions
                         (user_id, subject_id, title, body)
                         VALUES (?, ?, ?, ?)'
                    );
                    $stmt->execute([
                        $user['id'], $subjectId, $title, $body
                    ]);
                }

                if (!$error) {
                    $_SESSION['forum_success'] = $id > 0
                        ? 'Question updated.'
                        : 'Your question has been posted.';

                    header('Location: forum.php');
                    exit;
                }
            } catch (PDOException $e) {
                error_log($e->getMessage());
                $error = 'Unable to save your question. Please try again.';
            }
        }
    } elseif ($action === 'delete' && $id > 0) {
        try {
            $sql = 'DELETE FROM forum_questions WHERE id = ?';
            $params = [$id];

            if ($user['role'] !== 'admin') {
                $sql .= ' AND user_id = ?';
                $params[] = $user['id'];
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            if ($stmt->rowCount() > 0) {
                $_SESSION['forum_success'] = 'Question deleted.';
                header('Location: forum.php');
                exit;
            }

            $error = 'Question not found or you cannot delete it.';
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = 'Unable to delete the question.';
        }
    } else {
        $error = 'This action is not available.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edit'])) {
    if ($user['role'] !== 'student') {
        http_response_code(403);
        exit('Only student authors can edit their questions.');
    }

    $editId = filter_var(
        $_GET['edit'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($editId === false) {
        http_response_code(400);
        exit('Invalid question ID.');
    }

    $stmt = $pdo->prepare(
        'SELECT id, title, body, subject_id
         FROM forum_questions WHERE id = ? AND user_id = ?'
    );
    $stmt->execute([$editId, $user['id']]);
    $question = $stmt->fetch();

    if (!$question) {
        http_response_code(404);
        exit('Question not found in your posts.');
    }

    $formId = (int) $question['id'];
    $title = $question['title'];
    $body = $question['body'];
    $subjectId = (int) $question['subject_id'];
}

$questions = $pdo->query(
    'SELECT q.id, q.user_id, q.title, q.body, q.created_at,
            u.full_name, s.subject_code, s.subject_name
     FROM forum_questions q
     JOIN users u ON u.id = q.user_id
     JOIN subjects s ON s.id = q.subject_id
     ORDER BY q.created_at DESC, q.id DESC'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discussion Forum | NSBM StudyCircle</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #eff8f2;
            color: #173e2c;
            font-family: Arial, sans-serif;
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
            max-width: 950px;
            margin: auto;
            padding: 32px 20px;
        }
        .hero {
            margin-top: 24px;
            padding: 30px;
            border-radius: 18px;
            background: #146b3d;
            color: white;
        }
        .hero h1 { margin-top: 0; }
        p { line-height: 1.7; }
        .card {
            margin-top: 24px;
            padding: 26px;
            background: white;
            border: 1px solid #dce9df;
            border-radius: 16px;
            overflow-wrap: anywhere;
        }
        .muted { color: #52685b; font-size: 14px; }
        .badge {
            display: inline-block;
            padding: 6px 10px;
            background: #e7f7ec;
            color: #146b3d;
            border-radius: 20px;
            font-size: 13px;
        }
        label {
            display: block;
            margin: 18px 0 8px;
            font-weight: bold;
        }
        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #b6cabb;
            border-radius: 8px;
            background: white;
            color: #173e2c;
            font: inherit;
        }
        textarea { min-height: 140px; resize: vertical; }
        input:focus, select:focus, textarea:focus {
            outline: 3px solid #d1efdc;
            border-color: #16834a;
        }
        .actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-top: 20px;
        }
        button {
            padding: 11px 16px;
            border: 0;
            border-radius: 8px;
            background: #16834a;
            color: white;
            font: inherit;
            cursor: pointer;
        }
        button:hover { background: #106337; }
        .delete { background: #fff0f0; color: #952525; }
        .delete:hover { background: #fbdede; }
        .message {
            padding: 15px;
            border-radius: 10px;
        }
        .success { background: #dff3e6; color: #145c33; }
        .error { background: #fff0f0; color: #952525; }
    </style>
</head>
<body>
<header>
    <a class="logo" href="<?= escape($dashboard) ?>">
        NSBM StudyCircle
    </a>
</header>

<main>
    <a href="<?= escape($dashboard) ?>">&larr; Back to dashboard</a>

    <section class="hero">
        <h1>Ask. Discuss. Learn.</h1>
        <p>Share academic questions with the NSBM community.</p>
    </section>

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

    <?php if ($user['role'] === 'student'): ?>
        <section class="card" id="question-form">
            <h2><?= $formId ? 'Edit your question' : 'Ask a question' ?></h2>

            <?php if (!$subjects): ?>
                <p>An admin must add a subject before you can post.</p>
            <?php else: ?>
                <form method="post" action="forum.php">
                    <input type="hidden" name="csrf_token"
                           value="<?= escape(csrf_token()) ?>">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= $formId ?>">

                    <label for="title">Question title</label>
                    <input id="title" name="title"
                           value="<?= escape($title) ?>"
                           maxlength="150"
                           placeholder="Example: How does a foreign key work?"
                           required>

                    <label for="subject_id">Subject</label>
                    <select id="subject_id" name="subject_id" required>
                        <option value="">Select a subject</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?= (int) $subject['id'] ?>"
                                <?= $subjectId === (int) $subject['id'] ? 'selected' : '' ?>>
                                <?= escape($subject['subject_code'] . ' — ' . $subject['subject_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="body">Question details</label>
                    <textarea id="body" name="body" maxlength="5000"
                              placeholder="Explain your question and what you have tried."
                              required><?= escape($body) ?></textarea>

                    <div class="actions">
                        <button type="submit">
                            <?= $formId ? 'Save changes' : 'Post question' ?>
                        </button>
                        <?php if ($formId): ?>
                            <a href="forum.php">Cancel editing</a>
                        <?php endif; ?>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <h2>Community questions (<?= count($questions) ?>)</h2>

    <?php if (!$questions): ?>
        <section class="card">
            <p>No questions yet. Start a discussion above.</p>
        </section>
    <?php endif; ?>

    <?php foreach ($questions as $question): ?>
        <article class="card">
            <span class="badge">
                <?= escape($question['subject_code']) ?>
            </span>

            <h3><?= escape($question['title']) ?></h3>

            <p>
                <a href="question.php?id=<?= (int) $question['id'] ?>">
                    View discussion &amp; answers &rarr;
                </a>
            </p>

            <p class="muted">
                <?= escape($question['subject_name']) ?><br>
                Asked by <?= escape($question['full_name']) ?>
                · <?= escape($question['created_at']) ?>
            </p>

            <p><?= nl2br(escape($question['body'])) ?></p>

            <div class="actions">
                <?php if (
                    $user['role'] === 'student'
                    && (int) $question['user_id'] === (int) $user['id']
                ): ?>
                    <a href="forum.php?edit=<?= (int) $question['id'] ?>#question-form">
                        Edit question
                    </a>
                <?php endif; ?>

                <?php if (
                    $user['role'] === 'admin'
                    || (int) $question['user_id'] === (int) $user['id']
                ): ?>
                    <form method="post" action="forum.php"
                          onsubmit="return confirm('Delete this question and all its answers? This cannot be undone.');">
                        <input type="hidden" name="csrf_token"
                               value="<?= escape(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id"
                               value="<?= (int) $question['id'] ?>">
                        <button class="delete" type="submit">
                            Delete question
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
</main>
</body>
</html>