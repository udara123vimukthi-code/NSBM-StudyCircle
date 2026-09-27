<?php
require_once __DIR__ . '/config/auth.php';
$user = require_login();

$questionId = filter_var(
    $_GET['id'] ?? '',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($questionId === false) {
    http_response_code(400);
    exit('Invalid question ID.');
}

$stmt = $pdo->prepare(
    'SELECT q.id, q.title, q.body, q.created_at,
            u.full_name, s.subject_code, s.subject_name
     FROM forum_questions q
     JOIN users u ON u.id = q.user_id
     JOIN subjects s ON s.id = q.subject_id
     WHERE q.id = ?'
);
$stmt->execute([$questionId]);
$question = $stmt->fetch();

if (!$question) {
    http_response_code(404);
    exit('Question not found. It may have been deleted.');
}

function answer_input($key) {
    return isset($_POST[$key]) && is_string($_POST[$key])
        ? trim($_POST[$key])
        : '';
}

$error = '';
$formId = 0;
$body = '';

$success = $_SESSION['answer_success'][$questionId] ?? '';
unset($_SESSION['answer_success'][$questionId]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = answer_input('action');

    $answerId = filter_var(
        answer_input('answer_id'),
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 0]]
    );

    if (!valid_csrf()) {
        $error = 'Your form expired. Please refresh and try again.';
    } elseif ($answerId === false) {
        $error = 'Invalid answer ID.';
    } elseif ($action === 'save' && $user['role'] === 'student') {
        $formId = $answerId;
        $body = answer_input('body');

        if ($body === '' || mb_strlen($body) > 5000) {
            $error = 'Enter an answer of 1–5,000 characters.';
        } else {
            try {
                if ($answerId > 0) {
                    $check = $pdo->prepare(
                        'SELECT id FROM forum_answers
                         WHERE id = ? AND question_id = ? AND user_id = ?'
                    );
                    $check->execute([
                        $answerId, $questionId, $user['id']
                    ]);

                    if (!$check->fetch()) {
                        $error = 'Answer not found in your posts.';
                    } else {
                        $stmt = $pdo->prepare(
                            'UPDATE forum_answers SET body = ?
                             WHERE id = ? AND question_id = ? AND user_id = ?'
                        );
                        $stmt->execute([
                            $body, $answerId, $questionId, $user['id']
                        ]);
                    }
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO forum_answers
                         (question_id, user_id, body)
                         VALUES (?, ?, ?)'
                    );
                    $stmt->execute([
                        $questionId, $user['id'], $body
                    ]);
                }

                if (!$error) {
                    $_SESSION['answer_success'][$questionId] = $answerId > 0
                        ? 'Your answer has been updated.'
                        : 'Your answer has been posted.';

                    header('Location: question.php?id=' . $questionId);
                    exit;
                }
            } catch (PDOException $e) {
                error_log($e->getMessage());
                $error = 'Unable to save your answer. The question may have been removed.';
            }
        }
    } elseif ($action === 'delete' && $answerId > 0) {
        try {
            $sql = 'DELETE FROM forum_answers
                    WHERE id = ? AND question_id = ?';
            $params = [$answerId, $questionId];

            if ($user['role'] !== 'admin') {
                $sql .= ' AND user_id = ?';
                $params[] = $user['id'];
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            if ($stmt->rowCount() > 0) {
                $_SESSION['answer_success'][$questionId] = 'Answer deleted.';
                header('Location: question.php?id=' . $questionId);
                exit;
            }

            $error = 'Answer not found or you cannot delete it.';
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = 'Unable to delete the answer.';
        }
    } else {
        $error = 'This action is not available.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edit'])) {
    if ($user['role'] !== 'student') {
        http_response_code(403);
        exit('Only student authors can edit their answers.');
    }

    $editId = filter_var(
        $_GET['edit'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($editId === false) {
        http_response_code(400);
        exit('Invalid answer ID.');
    }

    $stmt = $pdo->prepare(
        'SELECT id, body FROM forum_answers
         WHERE id = ? AND question_id = ? AND user_id = ?'
    );
    $stmt->execute([$editId, $questionId, $user['id']]);
    $editing = $stmt->fetch();

    if (!$editing) {
        http_response_code(404);
        exit('Answer not found in your posts.');
    }

    $formId = (int) $editing['id'];
    $body = $editing['body'];
}

$stmt = $pdo->prepare(
    'SELECT a.id, a.user_id, a.body, a.created_at, a.updated_at,
            u.full_name
     FROM forum_answers a
     JOIN users u ON u.id = a.user_id
     WHERE a.question_id = ?
     ORDER BY a.created_at ASC, a.id ASC'
);
$stmt->execute([$questionId]);
$answers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($question['title']) ?> | StudyCircle</title>
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
            max-width: 950px;
            margin: auto;
            padding: 32px 20px;
        }
        .card {
            margin-top: 24px;
            padding: 26px;
            background: white;
            border: 1px solid #dce9df;
            border-radius: 16px;
            overflow-wrap: anywhere;
        }
        .question { border-top: 5px solid #16834a; }
        p { line-height: 1.7; }
        .muted { color: #52685b; font-size: 14px; }
        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            background: #e7f7ec;
            color: #146b3d;
            font-size: 13px;
        }
        label {
            display: block;
            margin-bottom: 10px;
            font-weight: bold;
        }
        textarea {
            width: 100%;
            min-height: 160px;
            padding: 12px;
            border: 1px solid #b6cabb;
            border-radius: 8px;
            font: inherit;
            resize: vertical;
        }
        textarea:focus {
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
    <a class="logo" href="forum.php">NSBM StudyCircle</a>
</header>

<main>
    <a href="forum.php">&larr; Back to discussion forum</a>

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

    <article class="card question">
        <span class="badge">
            <?= escape($question['subject_code']) ?>
        </span>

        <h1><?= escape($question['title']) ?></h1>

        <p class="muted">
            <?= escape($question['subject_name']) ?><br>
            Asked by <?= escape($question['full_name']) ?>
            · <?= escape($question['created_at']) ?>
        </p>

        <p><?= nl2br(escape($question['body'])) ?></p>
    </article>

    <h2>Answers (<?= count($answers) ?>)</h2>

    <?php if (!$answers): ?>
        <section class="card">
            <p>No answers yet.</p>
        </section>
    <?php endif; ?>

    <?php foreach ($answers as $answer): ?>
        <article class="card">
            <h3><?= escape($answer['full_name']) ?></h3>

            <p class="muted">
                <?= escape($answer['created_at']) ?>
                <?php if ($answer['updated_at'] !== $answer['created_at']): ?>
                    · Edited <?= escape($answer['updated_at']) ?>
                <?php endif; ?>
            </p>

            <p><?= nl2br(escape($answer['body'])) ?></p>

            <div class="actions">
                <?php if (
                    $user['role'] === 'student'
                    && (int) $answer['user_id'] === (int) $user['id']
                ): ?>
                    <a href="question.php?id=<?= $questionId ?>&amp;edit=<?= (int) $answer['id'] ?>#answer-form">
                        Edit answer
                    </a>
                <?php endif; ?>

                <?php if (
                    $user['role'] === 'admin'
                    || (int) $answer['user_id'] === (int) $user['id']
                ): ?>
                    <form method="post"
                          action="question.php?id=<?= $questionId ?>"
                          onsubmit="return confirm('Delete this answer?');">
                        <input type="hidden" name="csrf_token"
                               value="<?= escape(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="answer_id"
                               value="<?= (int) $answer['id'] ?>">
                        <button class="delete" type="submit">
                            Delete answer
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>

    <?php if ($user['role'] === 'student'): ?>
        <section class="card" id="answer-form">
            <h2><?= $formId ? 'Edit your answer' : 'Write an answer' ?></h2>

            <form method="post" action="question.php?id=<?= $questionId ?>">
                <input type="hidden" name="csrf_token"
                       value="<?= escape(csrf_token()) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="answer_id" value="<?= $formId ?>">

                <label for="body">Your answer</label>
                <textarea id="body" name="body" maxlength="5000"
                          placeholder="Explain your answer clearly and helpfully."
                          required><?= escape($body) ?></textarea>

                <div class="actions">
                    <button type="submit">
                        <?= $formId ? 'Save changes' : 'Post answer' ?>
                    </button>

                    <?php if ($formId): ?>
                        <a href="question.php?id=<?= $questionId ?>">
                            Cancel editing
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </section>
    <?php endif; ?>
</main>
</body>
</html>