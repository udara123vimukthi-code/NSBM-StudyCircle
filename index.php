<?php
require_once __DIR__ . '/config/auth.php';

$currentUser = null;
$dashboard = 'dashboard.php';

if (!empty($_SESSION['user_id'])) {
    $currentUser = require_login();
    $dashboard = $currentUser['role'] === 'admin'
        ? 'admin_dashboard.php'
        : 'dashboard.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NSBM StudyCircle | Learn Together</title>
    <link rel="stylesheet" href="site.css">
    <link rel="stylesheet" href="theme.css?v=1">
    <script src="theme.js?v=1"></script>
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="index.php">
            <span class="brand-mark" aria-hidden="true">SC</span>
            <span>
                <strong>StudyCircle</strong>
                <small>NSBM GREEN UNIVERSITY</small>
            </span>
        </a>

        <nav aria-label="Main navigation">
            <button type="button" class="theme-toggle" aria-label="Switch to dark theme" aria-pressed="false" hidden>Dark mode</button>
            <?php if ($currentUser): ?>
                <a class="button" href="<?= escape($dashboard) ?>">
                    My dashboard
                </a>
            <?php else: ?>
                <a href="login.php">Sign in</a>
                <a class="button" href="register.php">Join StudyCircle</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="container home-page">
    <section class="hero">
        <div>
            <p class="eyebrow">A community for curious minds</p>
            <h1>Share knowledge.<br>Grow together.</h1>
            <p>
                Find useful notes, ask academic questions, and connect
                with classmates at NSBM Green University.
            </p>

            <div class="actions">
                <?php if ($currentUser): ?>
                    <a class="button" href="<?= escape($dashboard) ?>">
                        Open dashboard
                    </a>
                <?php else: ?>
                    <a class="button" href="register.php">Create an account</a>
                    <a class="button button-outline" href="login.php">Sign in</a>
                <?php endif; ?>
            </div>
        </div>

        <aside class="hero-panel">
            <img class="study-art" src="assets/study-space.svg" alt="An open notebook, books and a leafy plant on a study desk" width="480" height="300">
            <h2>Your next study session starts here</h2>
            <ul>
                <li>Discover approved study materials</li>
                <li>Get help with difficult topics</li>
                <li>Meet students studying your subjects</li>
                <li>Plan time to learn together</li>
            </ul>
        </aside>
    </section>

    <div class="section-heading">
        <h2>Everything you need to learn together</h2>
        <p>Built around sharing, discussion, and student collaboration.</p>
    </div>

    <section class="grid" aria-label="StudyCircle features">
        <article class="card">
            <span class="feature-icon" aria-hidden="true">01</span><h3>Study materials</h3>
            <p>Browse notes, tutorials, and revision resources by subject
               and category.</p>
        </article>

        <article class="card">
            <span class="feature-icon" aria-hidden="true">02</span><h3>Questions and answers</h3>
            <p>Ask about challenging topics and share explanations with
               other students.</p>
        </article>

        <article class="card">
            <span class="feature-icon" aria-hidden="true">03</span><h3>Study groups</h3>
            <p>Find classmates, create a group, and schedule your next
               study session.</p>
        </article>
    </section>
</main>

<footer class="site-footer">
    <div class="container">
        NSBM StudyCircle · Student coursework project for NSBM Green University.
    </div>
</footer>
</body>
</html>