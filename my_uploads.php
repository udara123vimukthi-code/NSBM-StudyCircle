<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/storage.php';

$user = require_login();

if ($user['role'] !== 'student') {
    http_response_code(403);
    exit('This page is for student accounts.');
}

function my_upload_input($key) {
    return isset($_POST[$key]) && is_string($_POST[$key])
        ? trim($_POST[$key])
        : '';
}

$subjects = $pdo->query(
    'SELECT id, subject_code, subject_name
     FROM subjects ORDER BY subject_code'
)->fetchAll();

$categories = $pdo->query(
    'SELECT id, name FROM categories ORDER BY name'
)->fetchAll();

$error = '';
$editing = null;

$success = $_SESSION['my_upload_success'] ?? '';
unset($_SESSION['my_upload_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_var(
        my_upload_input('id'),
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    $action = my_upload_input('action');

    if (!valid_csrf()) {
        $error = 'Your form expired. Please refresh and try again.';
    } elseif (
        $id === false
        || !in_array($action, ['save', 'delete'], true)
    ) {
        $error = 'Invalid request.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT * FROM materials
             WHERE id = ? AND uploaded_by = ?'
        );
        $stmt->execute([$id, $user['id']]);
        $ownedMaterial = $stmt->fetch();

        if (!$ownedMaterial) {
            $error = 'Material not found in your uploads.';
        } elseif ($action === 'save') {
            $title = my_upload_input('title');
            $description = my_upload_input('description');

            $subjectId = filter_var(
                my_upload_input('subject_id'),
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            ) ?: 0;

            $categoryId = filter_var(
                my_upload_input('category_id'),
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            ) ?: 0;

            $editing = [
                'id' => $id,
                'title' => $title,
                'description' => $description,
                'subject_id' => $subjectId,
                'category_id' => $categoryId
            ];

            $subjectIds = array_map(
                'intval',
                array_column($subjects, 'id')
            );

            $categoryIds = array_map(
                'intval',
                array_column($categories, 'id')
            );

            if ($title === '' || mb_strlen($title) > 150) {
                $error = 'Enter a title of up to 150 characters.';
            } elseif (mb_strlen($description) > 5000) {
                $error = 'Keep the description within 5,000 characters.';
            } elseif (!in_array($subjectId, $subjectIds, true)) {
                $error = 'Select a valid subject.';
            } elseif (!in_array($categoryId, $categoryIds, true)) {
                $error = 'Select a valid category.';
            } else {
                try {
                    $stmt = $pdo->prepare(
                        "UPDATE materials
                         SET title = ?, description = ?,
                             subject_id = ?, category_id = ?,
                             status = 'pending'
                         WHERE id = ? AND uploaded_by = ?"
                    );

                    $stmt->execute([
                        $title,
                        $description,
                        $subjectId,
                        $categoryId,
                        $id,
                        $user['id']
                    ]);

                    $_SESSION['my_upload_success'] =
                        'Changes saved. Your material is pending admin review.';

                    header('Location: my_uploads.php');
                    exit;
                } catch (PDOException $e) {
                    error_log($e->getMessage());
                    $error = 'Unable to save changes. Please try again.';
                }
            }
        } elseif ($action === 'delete') {
            $deleted = false;

            try {
                $stmt = $pdo->prepare(
                    'DELETE FROM materials
                     WHERE id = ? AND uploaded_by = ?'
                );
                $stmt->execute([$id, $user['id']]);
                $deleted = $stmt->rowCount() > 0;
            } catch (PDOException $e) {
                error_log($e->getMessage());
                $error = 'Unable to delete this material. Please try again.';
            }

            if (!$error) {
                if (
                    $deleted
                    && preg_match(
                        '/\A[a-f0-9]{48}\.pdf\z/',
                        $ownedMaterial['stored_name']
                    )
                ) {
                    $path = rtrim($storageDirectory, '/\\')
                        . '/' . $ownedMaterial['stored_name'];

                    if (is_file($path) && !@unlink($path)) {
                        error_log(
                            'StudyCircle: unable to remove stored file ' . $path
                        );
                    }
                }

                $_SESSION['my_upload_success'] = $deleted
                    ? 'Material deleted successfully.'
                    : 'This material has already been removed.';

                header('Location: my_uploads.php');
                exit;
            }
        }
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
        exit('Invalid material ID.');
    }

    $stmt = $pdo->prepare(
        'SELECT id, title, description, subject_id, category_id
         FROM materials WHERE id = ? AND uploaded_by = ?'
    );
    $stmt->execute([$editId, $user['id']]);
    $editing = $stmt->fetch();

    if (!$editing) {
        http_response_code(404);
        exit('Material not found in your uploads.');
    }
}

$stmt = $pdo->prepare(
    'SELECT m.id, m.title, m.status, m.original_name,
            m.created_at, s.subject_code,
            c.name AS category_name
     FROM materials m
     JOIN subjects s ON s.id = m.subject_id
     JOIN categories c ON c.id = m.category_id
     WHERE m.uploaded_by = ?
     ORDER BY m.created_at DESC, m.id DESC'
);
$stmt->execute([$user['id']]);
$materials = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Uploads | NSBM StudyCircle</title>
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
        p { line-height: 1.6; }
        .card {
            margin-top: 24px;
            padding: 26px;
            background: white;
            border: 1px solid #dce9df;
            border-radius: 16px;
            overflow-wrap: anywhere;
        }
        .card-top, .actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        .card-top { justify-content: space-between; }
        .card-top h2 { margin: 0; }
        .badge {
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
        }
        .pending { background: #fff3cd; color: #755400; }
        .approved { background: #dff3e6; color: #145c33; }
        .rejected { background: #fff0f0; color: #952525; }
        .details { color: #52685b; }
        label {
            display: block;
            margin: 18px 0 8px;
            font-weight: bold;
        }
        input, select, textarea {
            width: 100%;
            padding: 12px;
            background: white;
            color: #173e2c;
            border: 1px solid #b6cabb;
            border-radius: 8px;
            font: inherit;
        }
        textarea { min-height: 130px; resize: vertical; }
        input:focus, select:focus, textarea:focus {
            outline: 3px solid #d1efdc;
            border-color: #16834a;
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
        .delete { background: #fff0f0; color: #952525; }
        .delete:hover { background: #fbdede; }
        .form-actions { margin-top: 22px; }
        .message {
            padding: 15px;
            border-radius: 10px;
            line-height: 1.6;
        }
        .success { background: #dff3e6; color: #145c33; }
        .error { background: #fff0f0; color: #952525; }
    </style>
</head>
<body>
<header>
    <a class="logo" href="dashboard.php">NSBM StudyCircle</a>
</header>

<main>
    <a href="dashboard.php">&larr; Back to dashboard</a>
    <h1>My uploads</h1>
    <p>Track your submissions and keep your materials up to date.</p>
    <a href="upload_material.php">Upload a new material &rarr;</a>

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

    <?php if ($editing): ?>
        <section class="card" id="edit-form">
            <h2>Edit material details</h2>
            <p>Saving changes returns this material to Pending.
               The PDF file stays the same.</p>

            <form method="post" action="my_uploads.php">
                <input type="hidden" name="csrf_token"
                       value="<?= escape(csrf_token()) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id"
                       value="<?= (int) $editing['id'] ?>">

                <label for="title">Title</label>
                <input id="title" name="title"
                       value="<?= escape($editing['title']) ?>"
                       maxlength="150" required>

                <label for="subject_id">Subject</label>
                <select id="subject_id" name="subject_id" required>
                    <option value="">Select a subject</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?= (int) $subject['id'] ?>"
                            <?= (int) $editing['subject_id'] === (int) $subject['id'] ? 'selected' : '' ?>>
                            <?= escape($subject['subject_code'] . ' — ' . $subject['subject_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="category_id">Category</label>
                <select id="category_id" name="category_id" required>
                    <option value="">Select a category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>"
                            <?= (int) $editing['category_id'] === (int) $category['id'] ? 'selected' : '' ?>>
                            <?= escape($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="description">Description</label>
                <textarea id="description" name="description"
                          maxlength="5000"><?= escape($editing['description']) ?></textarea>

                <div class="actions form-actions">
                    <button type="submit">Save and submit for review</button>
                    <a href="my_uploads.php">Cancel editing</a>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <?php if (!$materials): ?>
        <section class="card">
            <h2>No uploads yet</h2>
            <p>Your submissions will appear here after uploading.</p>
        </section>
    <?php endif; ?>

    <?php foreach ($materials as $material): ?>
        <article class="card">
            <div class="card-top">
                <h2><?= escape($material['title']) ?></h2>
                <span class="badge <?= escape($material['status']) ?>">
                    <?= escape(ucfirst($material['status'])) ?>
                </span>
            </div>

            <p class="details">
                <strong>Subject:</strong>
                <?= escape($material['subject_code']) ?><br>
                <strong>Category:</strong>
                <?= escape($material['category_name']) ?><br>
                <strong>File:</strong>
                <?= escape($material['original_name']) ?><br>
                <strong>Uploaded:</strong>
                <?= escape($material['created_at']) ?>
            </p>

            <div class="actions">
                <a href="my_uploads.php?edit=<?= (int) $material['id'] ?>#edit-form">
                    Edit details
                </a>

                <?php if ($material['status'] === 'approved'): ?>
                    <a href="download_material.php?id=<?= (int) $material['id'] ?>">
                        Download PDF
                    </a>
                <?php endif; ?>

                <form method="post" action="my_uploads.php"
                      onsubmit="return confirm('Permanently delete this material and its PDF?');">
                    <input type="hidden" name="csrf_token"
                           value="<?= escape(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id"
                           value="<?= (int) $material['id'] ?>">
                    <button class="delete" type="submit">Delete</button>
                </form>
            </div>
        </article>
    <?php endforeach; ?>
</main>
</body>
</html>