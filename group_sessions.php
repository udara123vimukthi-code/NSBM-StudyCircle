<?php
require_once __DIR__ . '/config/auth.php';
$user = require_login();

$timezone = new DateTimeZone('Asia/Colombo');

$groupId = filter_var(
    $_GET['group_id'] ?? '',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($groupId === false) {
    http_response_code(400);
    exit('Invalid group ID.');
}

$stmt = $pdo->prepare(
    'SELECT id, owner_id, name FROM study_groups WHERE id = ?'
);
$stmt->execute([$groupId]);
$group = $stmt->fetch();

if (!$group) {
    http_response_code(404);
    exit('Group not found.');
}

function session_member($pdo, $groupId, $userId) {
    $stmt = $pdo->prepare(
        'SELECT user_id FROM group_members
         WHERE group_id = ? AND user_id = ?'
    );
    $stmt->execute([$groupId, $userId]);
    return (bool) $stmt->fetch();
}

if (!session_member($pdo, $groupId, $user['id'])) {
    http_response_code(403);
    exit('Join this study group before viewing its sessions.');
}

function session_input($key) {
    return isset($_POST[$key]) && is_string($_POST[$key])
        ? trim($_POST[$key])
        : '';
}

function parse_session_time($value, $timezone) {
    $date = DateTimeImmutable::createFromFormat(
        '!Y-m-d\TH:i',
        $value,
        $timezone
    );

    if (!$date || $date->format('Y-m-d\TH:i') !== $value) {
        return null;
    }

    return $date;
}

$error = '';
$formId = 0;
$title = '';
$location = '';
$startValue = '';
$endValue = '';

$success = $_SESSION['session_success'][$groupId] ?? '';
unset($_SESSION['session_success'][$groupId]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = session_input('action');

    $id = filter_var(
        session_input('id'),
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 0]]
    );

    if (!valid_csrf()) {
        $error = 'Your form expired. Please refresh and try again.';
    } elseif (
        $id === false
        || !in_array($action, ['save', 'delete'], true)
        || ($action === 'delete' && $id === 0)
    ) {
        $error = 'Invalid session request.';
    } else {
        if ($action === 'save') {
            $formId = $id;
            $title = session_input('title');
            $location = session_input('location');
            $startValue = session_input('starts_at');
            $endValue = session_input('ends_at');

            $start = parse_session_time($startValue, $timezone);
            $end = parse_session_time($endValue, $timezone);

            if ($title === '' || mb_strlen($title) > 150) {
                $error = 'Enter a title of up to 150 characters.';
            } elseif ($location === '' || mb_strlen($location) > 255) {
                $error = 'Enter a location or meeting link of up to 255 characters.';
            } elseif (!$start || !$end) {
                $error = 'Enter valid start and end dates and times.';
            } elseif ($start <= new DateTimeImmutable('now', $timezone)) {
                $error = 'Choose a start time in the future.';
            } elseif ($end <= $start) {
                $error = 'The end time must be after the start time.';
            }
        }

        if (!$error) {
            try {
                $pdo->beginTransaction();

                // Group membership changes also lock this group row.
                $stmt = $pdo->prepare(
                    'SELECT owner_id FROM study_groups
                     WHERE id = ? FOR UPDATE'
                );
                $stmt->execute([$groupId]);
                $currentGroup = $stmt->fetch();

                if (
                    !$currentGroup
                    || !session_member($pdo, $groupId, $user['id'])
                ) {
                    throw new RuntimeException(
                        'You must still be a group member to make changes.'
                    );
                }

                if ($id > 0) {
                    $stmt = $pdo->prepare(
                        'SELECT created_by FROM study_sessions
                         WHERE id = ? AND group_id = ? FOR UPDATE'
                    );
                    $stmt->execute([$id, $groupId]);
                    $existing = $stmt->fetch();

                    if (!$existing) {
                        throw new RuntimeException('Session not found.');
                    }

                    if (
                        (int) $existing['created_by'] !== (int) $user['id']
                        && (int) $currentGroup['owner_id'] !== (int) $user['id']
                    ) {
                        throw new RuntimeException(
                            'Only the session creator or group owner can change this session.'
                        );
                    }
                }

                if ($action === 'delete') {
                    $stmt = $pdo->prepare(
                        'DELETE FROM study_sessions WHERE id = ? AND group_id = ?'
                    );
                    $stmt->execute([$id, $groupId]);
                    $message = 'Session deleted.';
                } elseif ($id > 0) {
                    $stmt = $pdo->prepare(
                        'UPDATE study_sessions
                         SET title = ?, location = ?, starts_at = ?, ends_at = ?
                         WHERE id = ? AND group_id = ?'
                    );
                    $stmt->execute([
                        $title,
                        $location,
                        $start->format('Y-m-d H:i:s'),
                        $end->format('Y-m-d H:i:s'),
                        $id,
                        $groupId
                    ]);
                    $message = 'Session updated.';
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO study_sessions
                         (group_id, created_by, title, location, starts_at, ends_at)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([
                        $groupId,
                        $user['id'],
                        $title,
                        $location,
                        $start->format('Y-m-d H:i:s'),
                        $end->format('Y-m-d H:i:s')
                    ]);
                    $message = 'Study session scheduled.';
                }

                $pdo->commit();
                $_SESSION['session_success'][$groupId] = $message;
                header('Location: group_sessions.php?group_id=' . $groupId);
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log($e->getMessage());
                $error = 'Unable to save your changes. Please try again.';
            } catch (RuntimeException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = $e->getMessage();
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
        exit('Invalid session ID.');
    }

    $stmt = $pdo->prepare(
        'SELECT * FROM study_sessions WHERE id = ? AND group_id = ?'
    );
    $stmt->execute([$editId, $groupId]);
    $editing = $stmt->fetch();

    if (
        !$editing
        || (
            (int) $editing['created_by'] !== (int) $user['id']
            && (int) $group['owner_id'] !== (int) $user['id']
        )
    ) {
        http_response_code(403);
        exit('Session unavailable or you cannot edit it.');
    }

    $formId = (int) $editing['id'];
    $title = $editing['title'];
    $location = $editing['location'];
    $startValue = (new DateTimeImmutable(
        $editing['starts_at'], $timezone
    ))->format('Y-m-d\TH:i');
    $endValue = (new DateTimeImmutable(
        $editing['ends_at'], $timezone
    ))->format('Y-m-d\TH:i');
}

$stmt = $pdo->prepare(
    'SELECT s.*, u.full_name AS creator_name
     FROM study_sessions s
     JOIN users u ON u.id = s.created_by
     WHERE s.group_id = ?
     ORDER BY s.starts_at ASC, s.id ASC'
);
$stmt->execute([$groupId]);
$sessions = $stmt->fetchAll();
$now = new DateTimeImmutable('now', $timezone);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Sessions | NSBM StudyCircle</title>
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
        h1 { overflow-wrap: anywhere; }
        p { line-height: 1.7; }
        .card {
            margin-top: 24px;
            padding: 26px;
            background: white;
            border: 1px solid #dce9df;
            border-radius: 16px;
            overflow-wrap: anywhere;
        }
        label {
            display: block;
            margin: 18px 0 8px;
            font-weight: bold;
        }
        input {
            width: 100%;
            min-width: 0;
            padding: 12px;
            border: 1px solid #b6cabb;
            border-radius: 8px;
            font: inherit;
        }
        input:focus {
            outline: 3px solid #d1efdc;
            border-color: #16834a;
        }
        .time-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
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
        .badge {
            padding: 6px 10px;
            border-radius: 20px;
            background: #e7f7ec;
            color: #146b3d;
            font-size: 13px;
        }
        .message { padding: 15px; border-radius: 10px; }
        .success { background: #dff3e6; color: #145c33; }
        .error { background: #fff0f0; color: #952525; }
        @media (max-width: 600px) {
            .time-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<header>
    <a class="logo" href="groups.php">NSBM StudyCircle</a>
</header>

<main>
    <a href="groups.php">&larr; Back to study groups</a>
    <h1><?= escape($group['name']) ?></h1>
    <p>Study sessions · All times are in Sri Lanka time (UTC+05:30).</p>

    <?php if ($success): ?>
        <p class="message success" role="status"><?= escape($success) ?></p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p class="message error" role="alert"><?= escape($error) ?></p>
    <?php endif; ?>

    <section class="card" id="session-form">
        <h2><?= $formId ? 'Edit session' : 'Schedule a session' ?></h2>

        <form method="post" action="group_sessions.php?group_id=<?= $groupId ?>">
            <input type="hidden" name="csrf_token"
                   value="<?= escape(csrf_token()) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $formId ?>">

            <label for="title">Session title</label>
            <input id="title" name="title"
                   value="<?= escape($title) ?>"
                   maxlength="150"
                   placeholder="Example: Database revision"
                   required>

            <label for="location">Meeting location or online link</label>
            <input id="location" name="location"
                   value="<?= escape($location) ?>"
                   maxlength="255"
                   placeholder="Example: Library study area"
                   required>

            <div class="time-grid">
                <div>
                    <label for="starts_at">Start date and time</label>
                    <input type="datetime-local" id="starts_at"
                           name="starts_at"
                           value="<?= escape($startValue) ?>" required>
                </div>
                <div>
                    <label for="ends_at">End date and time</label>
                    <input type="datetime-local" id="ends_at"
                           name="ends_at"
                           value="<?= escape($endValue) ?>" required>
                </div>
            </div>

            <div class="actions">
                <button type="submit">
                    <?= $formId ? 'Save changes' : 'Schedule session' ?>
                </button>
                <?php if ($formId): ?>
                    <a href="group_sessions.php?group_id=<?= $groupId ?>">
                        Cancel editing
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <h2>Group sessions (<?= count($sessions) ?>)</h2>

    <?php if (!$sessions): ?>
        <section class="card">
            <p>No sessions scheduled yet.</p>
        </section>
    <?php endif; ?>

    <?php foreach ($sessions as $session): ?>
        <?php
        $startTime = new DateTimeImmutable($session['starts_at'], $timezone);
        $endTime = new DateTimeImmutable($session['ends_at'], $timezone);
        $status = $endTime <= $now
            ? 'Past'
            : ($startTime <= $now ? 'In progress' : 'Upcoming');

        $canManage =
            (int) $session['created_by'] === (int) $user['id']
            || (int) $group['owner_id'] === (int) $user['id'];
        ?>
        <article class="card">
            <span class="badge"><?= escape($status) ?></span>
            <h3><?= escape($session['title']) ?></h3>

            <p>
                <strong>Starts:</strong>
                <?= escape($startTime->format('d M Y, g:i A')) ?><br>
                <strong>Ends:</strong>
                <?= escape($endTime->format('d M Y, g:i A')) ?><br>
                <strong>Location/link:</strong>
                <?= escape($session['location']) ?><br>
                <strong>Scheduled by:</strong>
                <?= escape($session['creator_name']) ?>
            </p>

            <?php if ($canManage): ?>
                <div class="actions">
                    <a href="group_sessions.php?group_id=<?= $groupId ?>&amp;edit=<?= (int) $session['id'] ?>#session-form">
                        Edit or reschedule
                    </a>

                    <form method="post"
                          action="group_sessions.php?group_id=<?= $groupId ?>"
                          onsubmit="return confirm('Delete this study session?');">
                        <input type="hidden" name="csrf_token"
                               value="<?= escape(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id"
                               value="<?= (int) $session['id'] ?>">
                        <button class="delete" type="submit">
                            Delete session
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</main>
</body>
</html>