<?php
require_once __DIR__ . '/config/auth.php';
$user = require_login();

$id = filter_var(
    $_GET['id'] ?? '',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($id === false) {
    http_response_code(400);
    exit('Invalid material ID.');
}

$stmt = $pdo->prepare(
    "SELECT m.id, m.title, m.description,
            s.subject_name, c.name AS category_name,
            u.full_name AS uploader_name
     FROM materials m
     JOIN subjects s ON s.id = m.subject_id
     JOIN categories c ON c.id = m.category_id
     JOIN users u ON u.id = m.uploaded_by
     WHERE m.id = ? AND m.status = 'approved'"
);
$stmt->execute([$id]);
$material = $stmt->fetch();

if (!$material) {
    http_response_code(404);
    exit('This material is unavailable or is awaiting approval.');
}

function detail_input($key) {
    return isset($_POST[$key]) && is_string($_POST[$key])
        ? trim($_POST[$key])
        : '';
}

$error = '';
$commentText = '';
$success = $_SESSION['material_feedback'][$id] ?? '';
unset($_SESSION['material_feedback'][$id]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = detail_input('action');
    $message = '';

    if (!valid_csrf()) {
        $error = 'Your form expired. Please refresh and try again.';
    } else {
        try {
            if ($action === 'rate' && $user['role'] === 'student') {
                $rating = filter_var(
                    detail_input('rating'),
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1, 'max_range' => 5]]
                );

                if ($rating === false) {
                    $error = 'Choose a rating between 1 and 5.';
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO material_ratings
                         (material_id, user_id, rating)
                         VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE
                         rating = ?, updated_at = CURRENT_TIMESTAMP'
                    );
                    $stmt->execute([$id, $user['id'], $rating, $rating]);
                    $message = 'Your rating has been saved.';
                }
            } elseif (
                $action === 'remove_rating'
                && $user['role'] === 'student'
            ) {
                $stmt = $pdo->prepare(
                    'DELETE FROM material_ratings
                     WHERE material_id = ? AND user_id = ?'
                );
                $stmt->execute([$id, $user['id']]);
                $message = 'Your rating has been removed.';
            } elseif (
                $action === 'comment'
                && $user['role'] === 'student'
            ) {
                $commentText = detail_input('body');

                if (
                    $commentText === ''
                    || mb_strlen($commentText) > 2000
                ) {
                    $error = 'Enter a comment of 1–2,000 characters.';
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO material_comments
                         (material_id, user_id, body)
                         VALUES (?, ?, ?)'
                    );
                    $stmt->execute([$id, $user['id'], $commentText]);
                    $message = 'Your comment has been posted.';
                }
            } elseif ($action === 'delete_comment') {
                $commentId = filter_var(
                    detail_input('comment_id'),
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                );

                if ($commentId === false) {
                    $error = 'Invalid comment.';
                } else {
                    $sql = 'DELETE FROM material_comments
                            WHERE id = ? AND material_id = ?';
                    $params = [$commentId, $id];

                    if ($user['role'] !== 'admin') {
                        $sql .= ' AND user_id = ?';
                        $params[] = $user['id'];
                    }

                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);

                    if ($stmt->rowCount() > 0) {
                        $message = 'Comment deleted.';
                    } else {
                        $error = 'Comment not found or you cannot delete it.';
                    }
                }
            } else {
                $error = 'This action is not available for your account.';
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = 'Unable to save your changes. Please try again.';
        }
    }

    if ($message !== '') {
        $_SESSION['material_feedback'][$id] = $message;
        header('Location: material_details.php?id=' . $id);
        exit;
    }
}

$stmt = $pdo->prepare(
    'SELECT COUNT(*) AS total, AVG(rating) AS average_rating
     FROM material_ratings WHERE material_id = ?'
);
$stmt->execute([$id]);
$ratingSummary = $stmt->fetch();

$stmt = $pdo->prepare(
    'SELECT rating FROM material_ratings
     WHERE material_id = ? AND user_id = ?'
);
$stmt->execute([$id, $user['id']]);
$myRating = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    'SELECT c.id, c.user_id, c.body, c.created_at, u.full_name
     FROM material_comments c
     JOIN users u ON u.id = c.user_id
     WHERE c.material_id = ?
     ORDER BY c.created_at DESC, c.id DESC'
);
$stmt->execute([$id]);
$comments = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($material['title']) ?> | StudyCircle</title>
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
            max-width: 850px;
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
        p { line-height: 1.7; }
        .muted { color: #52685b; }
        label {
            display: block;
            margin: 16px 0 8px;
            font-weight: bold;
        }
        select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #b6cabb;
            border-radius: 8px;
            background: white;
            color: #173e2c;
            font: inherit;
        }
        textarea { min-height: 120px; resize: vertical; }
        select:focus, textarea:focus {
            outline: 3px solid #d1efdc;
            border-color: #16834a;
        }
        button, .download {
            display: inline-block;
            padding: 11px 16px;
            background: #16834a;
            color: white;
            border: 0;
            border-radius: 8px;
            font: inherit;
            text-decoration: none;
            cursor: pointer;
        }
        button:hover, .download:hover { background: #106337; }
        .spaced { margin-top: 16px; }
        .delete { background: #fff0f0; color: #952525; }
        .delete:hover { background: #fbdede; }
        .comment {
            padding: 20px 0;
            border-top: 1px solid #e2ece5;
            overflow-wrap: anywhere;
        }
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
    <a class="logo" href="materials.php">NSBM StudyCircle</a>
</header>

<main>
    <a href="materials.php">&larr; Back to materials</a>

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

    <section class="card">
        <h1><?= escape($material['title']) ?></h1>
        <p class="muted">
            <?= escape($material['subject_name']) ?> ·
            <?= escape($material['category_name']) ?><br>
            Shared by <?= escape($material['uploader_name']) ?>
        </p>

        <?php if (!empty($material['description'])): ?>
            <p><?= nl2br(escape($material['description'])) ?></p>
        <?php endif; ?>

        <a class="download" href="download_material.php?id=<?= $id ?>">
            Download PDF
        </a>
    </section>

    <section class="card">
        <h2>Resource rating</h2>

        <?php if ((int) $ratingSummary['total'] > 0): ?>
            <p>
                <strong>
                    <?= number_format((float) $ratingSummary['average_rating'], 1) ?>
                    / 5
                </strong>
                from <?= (int) $ratingSummary['total'] ?> rating(s).
            </p>
        <?php else: ?>
            <p>No ratings yet.</p>
        <?php endif; ?>

        <?php if ($user['role'] === 'student'): ?>
            <form method="post" action="material_details.php?id=<?= $id ?>">
                <input type="hidden" name="csrf_token"
                       value="<?= escape(csrf_token()) ?>">
                <input type="hidden" name="action" value="rate">

                <label for="rating">Your rating</label>
                <select id="rating" name="rating" required>
                    <option value="">Choose a rating</option>
                    <?php for ($value = 1; $value <= 5; $value++): ?>
                        <option value="<?= $value ?>"
                            <?= $myRating === $value ? 'selected' : '' ?>>
                            <?= $value ?> / 5
                        </option>
                    <?php endfor; ?>
                </select>

                <button class="spaced" type="submit">
                    <?= $myRating ? 'Update rating' : 'Save rating' ?>
                </button>
            </form>

            <?php if ($myRating): ?>
                <form class="spaced" method="post"
                      action="material_details.php?id=<?= $id ?>">
                    <input type="hidden" name="csrf_token"
                           value="<?= escape(csrf_token()) ?>">
                    <input type="hidden" name="action" value="remove_rating">
                    <button class="delete" type="submit">Remove my rating</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Comments (<?= count($comments) ?>)</h2>

        <?php if ($user['role'] === 'student'): ?>
            <form method="post" action="material_details.php?id=<?= $id ?>">
                <input type="hidden" name="csrf_token"
                       value="<?= escape(csrf_token()) ?>">
                <input type="hidden" name="action" value="comment">

                <label for="body">Add a comment</label>
                <textarea id="body" name="body" maxlength="2000"
                          placeholder="Share helpful feedback or ask about this resource."
                          required><?= escape($commentText) ?></textarea>

                <button class="spaced" type="submit">Post comment</button>
            </form>
        <?php endif; ?>

        <?php if (!$comments): ?>
            <p>No comments yet.</p>
        <?php endif; ?>

        <?php foreach ($comments as $comment): ?>
            <article class="comment spaced">
                <strong><?= escape($comment['full_name']) ?></strong>
                <p class="muted"><?= escape($comment['created_at']) ?></p>
                <p><?= nl2br(escape($comment['body'])) ?></p>

                <?php if (
                    $user['role'] === 'admin'
                    || (int) $comment['user_id'] === (int) $user['id']
                ): ?>
                    <form method="post"
                          action="material_details.php?id=<?= $id ?>"
                          onsubmit="return confirm('Delete this comment?');">
                        <input type="hidden" name="csrf_token"
                               value="<?= escape(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete_comment">
                        <input type="hidden" name="comment_id"
                               value="<?= (int) $comment['id'] ?>">
                        <button class="delete" type="submit">Delete comment</button>
                    </form>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </section>
</main>
</body>
</html>