<?php
require_once __DIR__ . '/config/auth.php';
$user = require_admin();

$error = '';
$success = $_SESSION['review_success'] ?? '';
unset($_SESSION['review_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_var(
        $_POST['id'] ?? '',
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
        || !in_array($status, ['approved', 'rejected', 'pending'], true)
    ) {
        $error = 'Invalid review request.';
    } else {
        try {
            $check = $pdo->prepare(
                'SELECT id FROM materials WHERE id = ?'
            );
            $check->execute([$id]);

            if (!$check->fetch()) {
                $error = 'This material no longer exists.';
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE materials SET status = ? WHERE id = ?'
                );
                $stmt->execute([$status, $id]);

                $_SESSION['review_success'] =
                    'Material status saved as ' . $status . '.';

                header('Location: admin_materials.php');
                exit;
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = 'Unable to save your decision. Please try again.';
        }
    }
}

$materials = $pdo->query(
    "SELECT m.id, m.title, m.description, m.original_name,
            m.file_size, m.status, m.created_at,
            u.full_name AS uploader_name,
            s.subject_code, s.subject_name,
            c.name AS category_name
     FROM materials m
     JOIN users u ON u.id = m.uploaded_by
     JOIN subjects s ON s.id = m.subject_id
     JOIN categories c ON c.id = m.category_id
     ORDER BY
        CASE WHEN m.status = 'pending' THEN 0 ELSE 1 END,
        m.created_at DESC,
        m.id DESC"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Materials | NSBM StudyCircle</title>
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
            max-width: 1000px;
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
        .card-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .card-top h2 { margin: 0; }
        .badge {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
        }
        .pending { background: #fff3cd; color: #755400; }
        .approved { background: #dff3e6; color: #145c33; }
        .rejected { background: #fff0f0; color: #952525; }
        .details { color: #52685b; }
        .download {
            display: inline-block;
            margin: 8px 0 20px;
            font-weight: bold;
        }
        .review {
            padding-top: 18px;
            border-top: 1px solid #e2ece5;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }
        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        select, button {
            padding: 12px;
            border-radius: 8px;
            font: inherit;
        }
        select {
            border: 1px solid #b6cabb;
            background: white;
            color: #173e2c;
            max-width: 100%;
        }
        button {
            border: 0;
            background: #16834a;
            color: white;
            cursor: pointer;
        }
        button:hover { background: #106337; }
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
    <a class="logo" href="admin_dashboard.php">StudyCircle Admin</a>
</header>

<main>
    <a href="admin_dashboard.php">&larr; Back to dashboard</a>
    <h1>Review study materials</h1>
    <p>Download each submission to review it before approving.
       Pending submissions appear first.</p>

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

    <?php if (!$materials): ?>
        <section class="card">
            <h2>No submissions yet</h2>
            <p>Student uploads will appear here.</p>
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
                <strong>Uploaded by:</strong>
                <?= escape($material['uploader_name']) ?><br>

                <strong>Subject:</strong>
                <?= escape($material['subject_code'] . ' — ' . $material['subject_name']) ?><br>

                <strong>Category:</strong>
                <?= escape($material['category_name']) ?><br>

                <strong>File:</strong>
                <?= escape($material['original_name']) ?>
                (<?= number_format($material['file_size'] / 1024, 1) ?> KB)<br>

                <strong>Submitted:</strong>
                <?= escape($material['created_at']) ?>
            </p>

            <?php if ($material['description'] !== ''): ?>
                <p><?= nl2br(escape($material['description'])) ?></p>
            <?php endif; ?>

            <a class="download"
               href="download_material.php?id=<?= (int) $material['id'] ?>">
                Download PDF for review
            </a>

            <form class="review" method="post" action="admin_materials.php">
                <input type="hidden" name="csrf_token"
                       value="<?= escape(csrf_token()) ?>">
                <input type="hidden" name="id"
                       value="<?= (int) $material['id'] ?>">

                <label for="status-<?= (int) $material['id'] ?>">
                    Review decision
                </label>

                <div class="actions">
                    <select name="status"
                            id="status-<?= (int) $material['id'] ?>">
                        <option value="pending"
                            <?= $material['status'] === 'pending' ? 'selected' : '' ?>>
                            Pending
                        </option>
                        <option value="approved"
                            <?= $material['status'] === 'approved' ? 'selected' : '' ?>>
                            Approved
                        </option>
                        <option value="rejected"
                            <?= $material['status'] === 'rejected' ? 'selected' : '' ?>>
                            Rejected
                        </option>
                    </select>

                    <button type="submit">Save decision</button>
                </div>
            </form>
        </article>
    <?php endforeach; ?>
</main>
</body>
</html>