<?php
require_once __DIR__ . '/config/auth.php';
$user = require_admin();

$stats = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM users
         WHERE role = 'student') AS students,
        (SELECT COUNT(*) FROM materials
         WHERE status = 'pending') AS pending,
        (SELECT COUNT(*) FROM materials
         WHERE status = 'approved') AS approved"
)->fetch();

$links = [
    [
        'admin_users.php',
        'Student accounts',
        'Search students and block or restore account access.',
        'Manage students'
    ],
    [
        'admin_materials.php',
        'Material reviews',
        'Download submissions for review, then approve or reject them.',
        'Review materials'
    ],
    [
        'admin_subjects.php',
        'Subjects',
        'Create, edit, and organise university subjects.',
        'Manage subjects'
    ],
    [
        'admin_categories.php',
        'Resource categories',
        'Manage material types such as lecture notes and past papers.',
        'Manage categories'
    ],
    [
        'forum.php',
        'Discussion moderation',
        'Review questions and open discussions to moderate answers.',
        'Moderate discussions'
    ],
    [
        'materials.php',
        'Resource comments',
        'Open an approved resource’s details to review or remove comments.',
        'Browse resource discussions'
    ],
    [
        'admin_reports.php',
        'Usage and engagement',
        'Review download activity, ratings, and community participation.',
        'View reports'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | NSBM StudyCircle</title>
    <link rel="stylesheet" href="site.css">
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="admin_dashboard.php">
            <span class="brand-mark" aria-hidden="true">SC</span>
            <span>
                <strong>StudyCircle</strong>
                <small>ADMINISTRATION</small>
            </span>
        </a>

        <nav aria-label="Admin navigation">
            <a href="index.php">Home</a>
            <a href="admin_reports.php">Reports</a>

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
        <p class="eyebrow">NSBM Green University · Administration</p>
        <h1>Hello, <?= escape($user['full_name']) ?>!</h1>
        <p>Keep the community organised and help useful resources reach students.</p>
    </section>

    <section class="stats" aria-label="Community overview">
        <article class="card">
            <h2 class="stat-label">Student accounts</h2>
            <p class="stat-number"><?= (int) $stats['students'] ?></p>
        </article>
        <article class="card">
            <h2 class="stat-label">Awaiting review</h2>
            <p class="stat-number"><?= (int) $stats['pending'] ?></p>
        </article>
        <article class="card">
            <h2 class="stat-label">Approved resources</h2>
            <p class="stat-number"><?= (int) $stats['approved'] ?></p>
        </article>
    </section>

    <div class="section-heading">
        <h2>Administration tools</h2>
        <p>Manage content, student access, and community activity.</p>
    </div>

    <section class="grid" aria-label="Admin tools">
        <?php foreach ($links as [$url, $title, $description, $label]): ?>
            <a class="card card-link" href="<?= escape($url) ?>">
                <h3><?= escape($title) ?></h3>
                <p><?= escape($description) ?></p>
                <span class="link-label"><?= escape($label) ?> &rarr;</span>
            </a>
        <?php endforeach; ?>
    </section>
</main>

<footer class="site-footer">
    <div class="container">
        NSBM StudyCircle · Administration
    </div>
</footer>
</body>
</html>