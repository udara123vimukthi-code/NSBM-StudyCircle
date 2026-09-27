<?php
require_once __DIR__ . '/config/auth.php';
$user = require_login();

if ($user['role'] === 'admin') {
    header('Location: admin_dashboard.php');
    exit;
}

$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS uploads,
            COALESCE(SUM(status = 'approved'), 0) AS approved
     FROM materials WHERE uploaded_by = ?"
);
$stmt->execute([$user['id']]);
$materialStats = $stmt->fetch();

$stmt = $pdo->prepare(
    'SELECT COUNT(*) FROM group_members WHERE user_id = ?'
);
$stmt->execute([$user['id']]);
$groupCount = (int) $stmt->fetchColumn();

$links = [
    [
        'materials.php',
        'Explore materials',
        'Find approved notes and resources. Read ratings and join the comments.',
        'Browse resources'
    ],
    [
        'upload_material.php',
        'Share your notes',
        'Upload a PDF and submit it for admin approval.',
        'Upload material'
    ],
    [
        'my_uploads.php',
        'My uploads',
        'Check approval status, edit details, and manage your submissions.',
        'Manage uploads'
    ],
    [
        'forum.php',
        'Academic discussions',
        'Ask questions, share answers, and learn from your classmates.',
        'Open forum'
    ],
    [
        'groups.php',
        'Study groups and sessions',
        'Join a group or create your own. Open a joined group to schedule sessions.',
        'Explore groups'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | NSBM StudyCircle</title>
    <link rel="stylesheet" href="site.css">
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="dashboard.php">
            <span class="brand-mark" aria-hidden="true">SC</span>
            <span>
                <strong>StudyCircle</strong>
                <small>STUDENT DASHBOARD</small>
            </span>
        </a>

        <nav aria-label="Student navigation">
            <a href="index.php">Home</a>
            <a href="materials.php">Materials</a>
            <a href="forum.php">Forum</a>

            <form method="post" action="logout.php">
                <input type="hidden" name="csrf_token"
                       value="<?= escape(csrf_token()) ?>">
                <button class="button button-outline" type="submit">
                    Log out
                </button>
            </form>
        </nav>
    </div>
</header>

<main class="container">
    <section class="welcome">
        <p class="eyebrow">NSBM Green University</p>
        <h1>Welcome, <?= escape($user['full_name']) ?>!</h1>
        <p>Pick up a new idea, share what you know, or plan your next study session.</p>
    </section>

    <section class="stats" aria-label="Your activity">
        <article class="card">
            <h2 class="stat-label">My uploads</h2>
            <p class="stat-number"><?= (int) $materialStats['uploads'] ?></p>
        </article>
        <article class="card">
            <h2 class="stat-label">Approved uploads</h2>
            <p class="stat-number"><?= (int) $materialStats['approved'] ?></p>
        </article>
        <article class="card">
            <h2 class="stat-label">My groups</h2>
            <p class="stat-number"><?= $groupCount ?></p>
        </article>
    </section>

    <div class="section-heading">
        <h2>Your learning space</h2>
    </div>

    <section class="grid" aria-label="Student tools">
        <?php foreach ($links as [$url, $title, $description, $label]): ?>
            <a class="card card-link" href="<?= escape($url) ?>">
                <h3><?= escape($title) ?></h3>
                <p><?= escape($description) ?></p>
                <span class="link-label"><?= escape($label) ?> &rarr;</span>
            </a>
        <?php endforeach; ?>
    </section>

    <section class="card account">
        <h2>Your account</h2>
        <p>
            <strong>Student ID:</strong>
            <?= escape($user['student_id']) ?><br>
            <strong>Email:</strong>
            <?= escape($user['email']) ?>
        </p>
    </section>
</main>

<footer class="site-footer">
    <div class="container">
        NSBM StudyCircle · Share knowledge. Learn together.
    </div>
</footer>
</body>
</html>