<?php
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/storage.php';

$user = require_login();

if ($user['role'] !== 'student') {
    http_response_code(403);
    exit('Please use a student account to upload study materials.');
}

function upload_input($key) {
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

$errors = [];
$title = '';
$description = '';
$subjectId = 0;
$categoryId = 0;

$success = $_SESSION['upload_success'] ?? '';
unset($_SESSION['upload_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = upload_input('title');
    $description = upload_input('description');

    $subjectId = filter_var(
        upload_input('subject_id'),
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    ) ?: 0;

    $categoryId = filter_var(
        upload_input('category_id'),
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    ) ?: 0;

    if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        $errors[] = 'The upload exceeded the server limit. Choose a PDF under 1 MB.';
    } elseif (!valid_csrf()) {
        $errors[] = 'Your form expired. Please refresh the page and try again.';
    }

    if ($title === '' || mb_strlen($title) > 150) {
        $errors[] = 'Enter a title of up to 150 characters.';
    }

    if (mb_strlen($description) > 5000) {
        $errors[] = 'Keep the description within 5,000 characters.';
    }

    $subjectIds = array_map('intval', array_column($subjects, 'id'));
    $categoryIds = array_map('intval', array_column($categories, 'id'));

    if (!in_array($subjectId, $subjectIds, true)) {
        $errors[] = 'Select a valid subject.';
    }

    if (!in_array($categoryId, $categoryIds, true)) {
        $errors[] = 'Select a valid category.';
    }

    $file = $_FILES['material'] ?? null;
    $fileSize = 0;
    $originalName = '';

    if (
        !is_array($file)
        || !isset($file['error'])
        || is_array($file['error'])
    ) {
        $errors[] = 'Choose a PDF file.';
    } elseif ((int) $file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'The file could not be uploaded. Choose a PDF under 1 MB and try again.';
    } elseif (
        !isset($file['tmp_name'], $file['name'])
        || !is_string($file['tmp_name'])
        || !is_string($file['name'])
        || !is_uploaded_file($file['tmp_name'])
    ) {
        $errors[] = 'Invalid file upload.';
    } else {
        $fileSize = filesize($file['tmp_name']);
        $originalName = basename(str_replace('\\', '/', $file['name']));

        if ($fileSize === false || $fileSize < 1 || $fileSize > 1048576) {
            $errors[] = 'The PDF must be between 1 byte and 1 MB.';
        }

        if (
            !mb_check_encoding($originalName, 'UTF-8')
            || mb_strlen($originalName) > 255
            || preg_match('/[\x00-\x1F\x7F]/', $originalName)
        ) {
            $errors[] = 'Rename the file using a shorter, simple filename.';
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $signature = file_get_contents(
            $file['tmp_name'],
            false,
            null,
            0,
            5
        );

        if (
            $extension !== 'pdf'
            || $mime !== 'application/pdf'
            || $signature !== '%PDF-'
        ) {
            $errors[] = 'Only valid PDF files are accepted.';
        }
    }

    if (!$errors) {
        if (!is_dir($storageDirectory) || !is_writable($storageDirectory)) {
            $errors[] = 'The storage folder is missing or not writable. Check config/storage.php.';
        } else {
            $storedName = bin2hex(random_bytes(24)) . '.pdf';
            $destination = rtrim($storageDirectory, '/\\') . '/' . $storedName;

            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                $errors[] = 'Unable to save the PDF. Please try again.';
            } else {
                try {
                    $stmt = $pdo->prepare(
                        "INSERT INTO materials
                         (uploaded_by, subject_id, category_id, title,
                          description, original_name, stored_name,
                          file_size, status)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
                    );

                    $stmt->execute([
                        $user['id'],
                        $subjectId,
                        $categoryId,
                        $title,
                        $description,
                        $originalName,
                        $storedName,
                        $fileSize
                    ]);

                    $_SESSION['upload_success'] =
                        'Material uploaded successfully! It is pending admin approval.';

                    header('Location: upload_material.php');
                    exit;
                } catch (PDOException $e) {
                    if (is_file($destination)) {
                        unlink($destination);
                    }

                    error_log($e->getMessage());
                    $errors[] = 'Unable to save the material details. Please try again.';
                }
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
    <title>Upload Material | NSBM StudyCircle</title>
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
            max-width: 760px;
            margin: auto;
            padding: 32px 20px;
        }
        .card {
            margin-top: 24px;
            padding: 28px;
            background: white;
            border: 1px solid #dce9df;
            border-radius: 18px;
        }
        p { line-height: 1.6; }
        label {
            display: block;
            margin: 20px 0 8px;
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
        textarea { min-height: 130px; resize: vertical; }
        input:focus, select:focus, textarea:focus {
            outline: 3px solid #d1efdc;
            border-color: #16834a;
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
        .hint { font-size: 13px; color: #52685b; }
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
    <a class="logo" href="dashboard.php">NSBM StudyCircle</a>
</header>

<main>
    <a href="dashboard.php">&larr; Back to dashboard</a>
    <h1>Share study materials</h1>
    <p>Help other students learn by sharing your notes and resources.</p>

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

    <?php if (!$subjects || !$categories): ?>
        <p class="message error">
            An admin must add at least one subject and category before uploading.
        </p>
    <?php else: ?>
        <section class="card">
            <h2>Material details</h2>

            <form method="post" action="upload_material.php"
                  enctype="multipart/form-data">

                <input type="hidden" name="csrf_token"
                       value="<?= escape(csrf_token()) ?>">

                <label for="title">Title</label>
                <input id="title" name="title"
                       value="<?= escape($title) ?>"
                       maxlength="150"
                       placeholder="Example: Database Normalisation Notes"
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

                <label for="category_id">Category</label>
                <select id="category_id" name="category_id" required>
                    <option value="">Select a category</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>"
                            <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>>
                            <?= escape($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="description">Description (optional)</label>
                <textarea id="description" name="description"
                          maxlength="5000"
                          placeholder="What does this material cover?"><?= escape($description) ?></textarea>

                <label for="material">PDF file</label>
                <input type="file" id="material" name="material"
                       accept=".pdf,application/pdf"
                       aria-describedby="file_hint" required>

                <p class="hint" id="file_hint">
                    PDF only, maximum 1 MB. Upload materials you have permission
                    to share. An admin will review your submission.
                </p>

                <button type="submit">Submit for approval</button>
            </form>
        </section>
    <?php endif; ?>
</main>
</body>
</html>