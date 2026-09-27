<?php
require_once __DIR__ . '/config/auth.php';
$user = require_login();

$dashboard = $user['role'] === 'admin'
    ? 'admin_dashboard.php'
    : 'dashboard.php';

$search = isset($_GET['q']) && is_string($_GET['q'])
    ? trim($_GET['q'])
    : '';

$search = mb_substr($search, 0, 150);

$subjectId = filter_var(
    $_GET['subject_id'] ?? '0',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 0]]
) ?: 0;

$categoryId = filter_var(
    $_GET['category_id'] ?? '0',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 0]]
) ?: 0;

$subjects = $pdo->query(
    'SELECT id, subject_code, subject_name
     FROM subjects ORDER BY subject_code'
)->fetchAll();

$categories = $pdo->query(
    'SELECT id, name FROM categories ORDER BY name'
)->fetchAll();

$sql = "
    SELECT m.id, m.title, m.description, m.file_size,
           m.created_at,
           u.full_name AS uploader_name,
           s.subject_code, s.subject_name,
           c.name AS category_name
    FROM materials m
    JOIN users u ON u.id = m.uploaded_by
    JOIN subjects s ON s.id = m.subject_id
    JOIN categories c ON c.id = m.category_id
    WHERE m.status = 'approved'
";

$params = [];

if ($search !== '') {
    // Treat %, _ and ! as ordinary search characters.
    $term = '%' . str_replace(
        ['!', '%', '_'],
        ['!!', '!%', '!_'],
        $search
    ) . '%';

    $sql .= "
        AND (
            m.title LIKE ? ESCAPE '!'
            OR m.description LIKE ? ESCAPE '!'
            OR s.subject_name LIKE ? ESCAPE '!'
            OR s.subject_code LIKE ? ESCAPE '!'
        )
    ";

    $params = [$term, $term, $term, $term];
}

if ($subjectId > 0) {
    $sql .= ' AND m.subject_id = ?';
    $params[] = $subjectId;
}

if ($categoryId > 0) {
    $sql .= ' AND m.category_id = ?';
    $params[] = $categoryId;
}

$sql .= ' ORDER BY m.created_at DESC, m.id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$materials = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Materials | NSBM StudyCircle</title>
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
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
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

        .hero {
            padding: 32px;
            background: #146b3d;
            color: white;
            border-radius: 20px;
        }

        .hero h1 { margin-top: 0; }

        p { line-height: 1.6; }

        .filters {
            margin-top: 24px;
            padding: 24px;
            background: white;
            border: 1px solid #dce9df;
            border-radius: 16px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 16px;
        }

        .filter-grid > div { min-width: 0; }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        input, select {
            width: 100%;
            padding: 12px;
            background: white;
            color: #173e2c;
            border: 1px solid #b6cabb;
            border-radius: 8px;
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
            gap: 18px;
            margin-top: 20px;
        }

        button, .download {
            padding: 12px 18px;
            border: 0;
            border-radius: 8px;
            background: #16834a;
            color: white;
            font: inherit;
            font-weight: bold;
            text-decoration: none;
            cursor: pointer;
        }

        button:hover, .download:hover {
            background: #106337;
        }

        .results {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .card {
            padding: 24px;
            background: white;
            border: 1px solid #dce9df;
            border-radius: 16px;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            overflow-wrap: anywhere;
            min-width: 0;
        }

        .card h3 { margin-bottom: 8px; }

        .badge {
            padding: 6px 10px;
            border-radius: 20px;
            background: #e7f7ec;
            color: #146b3d;
            font-size: 12px;
            font-weight: bold;
        }

        .details {
            color: #52685b;
            font-size: 14px;
        }

        details {
            width: 100%;
            margin-bottom: 20px;
        }

        summary {
            color: #146b3d;
            cursor: pointer;
        }

        .download {
            display: inline-block;
            margin-top: auto;
        }

        .empty {
            padding: 32px;
            background: white;
            border-radius: 16px;
            text-align: center;
        }

        @media (max-width: 760px) {
            .filter-grid, .results {
                grid-template-columns: 1fr;
            }
            .hero { padding: 24px; }
        }
    </style>
</head>
<body>
<header>
    <a class="logo" href="<?= escape($dashboard) ?>">
        NSBM StudyCircle
    </a>
    <a href="<?= escape($dashboard) ?>">Back to dashboard</a>
</header>

<main>
    <section class="hero">
        <h1>Learn something together</h1>
        <p>Explore approved notes, tutorials, and revision resources
           shared by the NSBM community.</p>
    </section>

    <form class="filters" method="get" action="materials.php">
        <div class="filter-grid">
            <div>
                <label for="q">Search materials</label>
                <input type="search" id="q" name="q"
                       value="<?= escape($search) ?>"
                       maxlength="150"
                       placeholder="Search by title, topic, or subject">
            </div>

            <div>
                <label for="subject_id">Subject</label>
                <select id="subject_id" name="subject_id">
                    <option value="0">All subjects</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?= (int) $subject['id'] ?>"
                            <?= $subjectId === (int) $subject['id'] ? 'selected' : '' ?>>
                            <?= escape($subject['subject_code'] . ' — ' . $subject['subject_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="category_id">Category</label>
                <select id="category_id" name="category_id">
                    <option value="0">All categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>"
                            <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>>
                            <?= escape($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="actions">
            <button type="submit">Search materials</button>
            <a href="materials.php">Clear filters</a>

            <?php if ($user['role'] === 'student'): ?>
                <a href="upload_material.php">Share your notes</a>
            <?php endif; ?>
        </div>
    </form>

    <h2><?= count($materials) ?> material(s) found</h2>

    <?php if (!$materials): ?>
        <section class="empty">
            <h3>No matching materials</h3>
            <p>Try another search or clear your filters.
               Only approved submissions appear here.</p>
        </section>
    <?php else: ?>
        <section class="results" aria-label="Study materials">
            <?php foreach ($materials as $material): ?>
                <article class="card">
                    <span class="badge">
                        <?= escape($material['category_name']) ?>
                    </span>

                    <h3><?= escape($material['title']) ?></h3>

                    <p>
                        <a href="material_details.php?id=<?= (int) $material['id'] ?>">
                            View details, ratings &amp; comments
                        </a>
                    </p>

                    <p>
                        <?= escape($material['subject_code']) ?>
                        — <?= escape($material['subject_name']) ?>
                    </p>

                    <p class="details">
                        Shared by <?= escape($material['uploader_name']) ?><br>
                        Uploaded:
                        <?= escape(substr($material['created_at'], 0, 10)) ?><br>
                        PDF ·
                        <?= number_format($material['file_size'] / 1024, 1) ?> KB
                    </p>

                    <?php if (!empty($material['description'])): ?>
                        <details>
                            <summary>Read description</summary>
                            <p><?= nl2br(escape($material['description'])) ?></p>
                        </details>
                    <?php endif; ?>

                    <a class="download"
                       href="download_material.php?id=<?= (int) $material['id'] ?>">
                        Download PDF
                    </a>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</main>
</body>
</html>