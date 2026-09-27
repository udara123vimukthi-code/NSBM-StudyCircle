<?php
require_once __DIR__ . '/config/auth.php';
$admin = require_admin();

$summary = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM users
         WHERE role = 'student') AS students,

        (SELECT COUNT(*) FROM users
         WHERE role = 'student' AND status = 'active') AS active_students,

        (SELECT COUNT(*) FROM users
         WHERE role = 'student' AND status = 'blocked') AS blocked_students,

        (SELECT COUNT(*) FROM materials) AS materials,

        (SELECT COUNT(*) FROM materials
         WHERE status = 'approved') AS approved,

        (SELECT COUNT(*) FROM materials
         WHERE status = 'pending') AS pending,

        (SELECT COUNT(*) FROM materials
         WHERE status = 'rejected') AS rejected,

        (SELECT COUNT(*) FROM material_downloads) AS downloads,

        (SELECT COUNT(DISTINCT user_id)
         FROM material_downloads) AS downloaders,

        (SELECT COUNT(*) FROM material_ratings) AS ratings,

        (SELECT AVG(rating)
         FROM material_ratings) AS average_rating,

        (SELECT COUNT(*) FROM material_comments) AS comments,

        (SELECT COUNT(*) FROM forum_questions) AS questions,

        (SELECT COUNT(*) FROM forum_answers) AS answers,

        (SELECT COUNT(*) FROM study_groups) AS study_groups,

        (SELECT COUNT(*) FROM group_members) AS memberships,

        (SELECT COUNT(DISTINCT user_id)
         FROM group_members) AS group_students,

        (SELECT COUNT(*) FROM study_sessions) AS sessions"
)->fetch();

$resources = $pdo->query(
    "SELECT m.id, m.title, m.status, s.subject_code,
            COALESCE(d.downloads, 0) AS downloads,
            COALESCE(d.downloaders, 0) AS downloaders,
            COALESCE(r.ratings, 0) AS ratings,
            r.average_rating,
            COALESCE(c.comments, 0) AS comments
     FROM materials m
     JOIN subjects s ON s.id = m.subject_id

     LEFT JOIN (
         SELECT material_id, COUNT(*) AS downloads,
                COUNT(DISTINCT user_id) AS downloaders
         FROM material_downloads
         GROUP BY material_id
     ) d ON d.material_id = m.id

     LEFT JOIN (
         SELECT material_id, COUNT(*) AS ratings,
                AVG(rating) AS average_rating
         FROM material_ratings
         GROUP BY material_id
     ) r ON r.material_id = m.id

     LEFT JOIN (
         SELECT material_id, COUNT(*) AS comments
         FROM material_comments
         GROUP BY material_id
     ) c ON c.material_id = m.id

     ORDER BY downloads DESC, comments DESC, m.id DESC
     LIMIT 10"
)->fetchAll();

$subjectUsage = $pdo->query(
    "SELECT s.subject_code, s.subject_name,
            COALESCE(m.total, 0) AS total,
            COALESCE(m.approved, 0) AS approved,
            COALESCE(d.downloads, 0) AS downloads
     FROM subjects s

     LEFT JOIN (
         SELECT subject_id, COUNT(*) AS total,
                SUM(status = 'approved') AS approved
         FROM materials
         GROUP BY subject_id
     ) m ON m.subject_id = s.id

     LEFT JOIN (
         SELECT m.subject_id, COUNT(*) AS downloads
         FROM material_downloads d
         JOIN materials m ON m.id = d.material_id
         GROUP BY m.subject_id
     ) d ON d.subject_id = s.id

     ORDER BY downloads DESC, s.subject_code"
)->fetchAll();

$generatedAt = new DateTimeImmutable(
    'now',
    new DateTimeZone('Asia/Colombo')
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usage Reports | NSBM StudyCircle</title>
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
            max-width: 1200px;
            margin: auto;
            padding: 32px 20px;
        }
        p { line-height: 1.6; }
        .muted { color: #52685b; }
        .heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }
        button {
            padding: 12px 18px;
            background: #16834a;
            color: white;
            border: 0;
            border-radius: 8px;
            font: inherit;
            cursor: pointer;
        }
        button:hover { background: #106337; }
        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-top: 24px;
        }
        .stat, .card {
            padding: 24px;
            background: white;
            border: 1px solid #dce9df;
            border-radius: 16px;
        }
        .stat h2 { font-size: 17px; }
        .number {
            margin: 10px 0;
            font-size: 36px;
            font-weight: bold;
            color: #16834a;
        }
        .card { margin-top: 24px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td {
            padding: 12px;
            border-bottom: 1px solid #e2ece5;
            text-align: left;
            vertical-align: top;
        }
        th { background: #f0f8f3; }
        td { overflow-wrap: anywhere; }
        .two-columns {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }
        @media (max-width: 850px) {
            .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .two-columns { grid-template-columns: 1fr; }
        }
        @media (max-width: 480px) {
            .stats { grid-template-columns: 1fr; }
        }
        @media print {
            @page { size: A4 landscape; margin: 12mm; }
            body { background: white; color: black; font-size: 10pt; }
            .no-print, header { display: none; }
            main { max-width: none; padding: 0; }
            .stats { grid-template-columns: repeat(4, 1fr); }
            .two-columns { grid-template-columns: repeat(2, 1fr); }
            .stat, .card { border-radius: 0; padding: 12px; }
            .stat { break-inside: avoid; }
            .table-wrap { overflow: visible; }
            table { table-layout: fixed; }
            thead { display: table-header-group; }
            tr { break-inside: avoid; }
            h2 { break-after: avoid; }
            th, td { padding: 8px; }
            .number { font-size: 26pt; }
        }
    </style>
</head>
<body>
<header>
    <a class="logo" href="admin_dashboard.php">StudyCircle Admin</a>
</header>

<main>
    <a class="no-print" href="admin_dashboard.php">
        &larr; Back to dashboard
    </a>

    <div class="heading">
        <div>
            <h1>NSBM StudyCircle — Usage Report</h1>
            <p class="muted">
                Generated:
                <?= escape($generatedAt->format('d M Y, g:i A')) ?>
                · Sri Lanka time
            </p>
        </div>
        <button class="no-print" type="button" onclick="window.print()">
            Print / Save PDF
        </button>
    </div>

    <section class="stats" aria-label="Main statistics">
        <article class="stat">
            <h2>Students</h2>
            <p class="number"><?= (int) $summary['students'] ?></p>
            <p><?= (int) $summary['active_students'] ?> active ·
               <?= (int) $summary['blocked_students'] ?> blocked</p>
        </article>

        <article class="stat">
            <h2>Study materials</h2>
            <p class="number"><?= (int) $summary['materials'] ?></p>
            <p><?= (int) $summary['approved'] ?> approved</p>
        </article>

        <article class="stat">
            <h2>Download requests</h2>
            <p class="number"><?= (int) $summary['downloads'] ?></p>
            <p>From <?= (int) $summary['downloaders'] ?> distinct students</p>
        </article>

        <article class="stat">
            <h2>Average rating</h2>
            <p class="number">
                <?= $summary['average_rating'] === null
                    ? '—'
                    : number_format((float) $summary['average_rating'], 1) . '/5' ?>
            </p>
            <p><?= (int) $summary['ratings'] ?> resource ratings</p>
        </article>
    </section>

    <div class="two-columns">
        <section class="card">
            <h2>Material approval</h2>
            <table>
                <tbody>
                <tr>
                    <th scope="row">Approved</th>
                    <td><?= (int) $summary['approved'] ?></td>
                </tr>
                <tr>
                    <th scope="row">Pending</th>
                    <td><?= (int) $summary['pending'] ?></td>
                </tr>
                <tr>
                    <th scope="row">Rejected</th>
                    <td><?= (int) $summary['rejected'] ?></td>
                </tr>
                </tbody>
            </table>
        </section>

        <section class="card">
            <h2>Community engagement</h2>
            <table>
                <tbody>
                <tr>
                    <th scope="row">Resource comments</th>
                    <td><?= (int) $summary['comments'] ?></td>
                </tr>
                <tr>
                    <th scope="row">Forum questions / answers</th>
                    <td>
                        <?= (int) $summary['questions'] ?> /
                        <?= (int) $summary['answers'] ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Study groups</th>
                    <td><?= (int) $summary['study_groups'] ?></td>
                </tr>
                <tr>
                    <th scope="row">Group memberships</th>
                    <td><?= (int) $summary['memberships'] ?></td>
                </tr>
                <tr>
                    <th scope="row">Distinct group members</th>
                    <td><?= (int) $summary['group_students'] ?></td>
                </tr>
                <tr>
                    <th scope="row">Scheduled sessions, including past sessions</th>
                    <td><?= (int) $summary['sessions'] ?></td>
                </tr>
                </tbody>
            </table>
        </section>
    </div>

    <section class="card">
        <h2>Top 10 resources by recorded download requests</h2>

        <?php if (!$resources): ?>
            <p>No materials available yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th scope="col">Material</th>
                        <th scope="col">Status</th>
                        <th scope="col">Downloads</th>
                        <th scope="col">Distinct students</th>
                        <th scope="col">Rating</th>
                        <th scope="col">Comments</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($resources as $resource): ?>
                        <tr>
                            <td>
                                <?= escape($resource['title']) ?><br>
                                <small><?= escape($resource['subject_code']) ?></small>
                            </td>
                            <td><?= escape(ucfirst($resource['status'])) ?></td>
                            <td><?= (int) $resource['downloads'] ?></td>
                            <td><?= (int) $resource['downloaders'] ?></td>
                            <td>
                                <?= $resource['average_rating'] === null
                                    ? 'Not rated'
                                    : number_format((float) $resource['average_rating'], 1) . '/5' ?>
                                <br>
                                <small><?= (int) $resource['ratings'] ?> rating(s)</small>
                            </td>
                            <td><?= (int) $resource['comments'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Usage by subject</h2>
        <?php if (!$subjectUsage): ?>
            <p>No subjects available yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th scope="col">Subject</th>
                        <th scope="col">Materials</th>
                        <th scope="col">Approved</th>
                        <th scope="col">Download requests</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($subjectUsage as $subject): ?>
                        <tr>
                            <td>
                                <?= escape($subject['subject_code']) ?> —
                                <?= escape($subject['subject_name']) ?>
                            </td>
                            <td><?= (int) $subject['total'] ?></td>
                            <td><?= (int) $subject['approved'] ?></td>
                            <td><?= (int) $subject['downloads'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>How to read this report</h2>
        <ul>
            <li>Download activity starts when tracking is installed.
                Earlier downloads are not included.</li>
            <li>Repeated student downloads count separately.
                Admin review downloads are excluded.</li>
            <li>A recorded request does not confirm that the browser
                finished downloading the file.</li>
            <li>Totals reflect current stored records. Deleting a material
                also removes its download records, ratings, and comments.</li>
            <li>One student can belong to several groups, so memberships
                can exceed the number of distinct group members.</li>
        </ul>
    </section>
</main>
</body>
</html>