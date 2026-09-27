<?php
require_once __DIR__ . '/config/auth.php';
$user = require_login();

$dashboard = $user['role'] === 'admin'
    ? 'admin_dashboard.php'
    : 'dashboard.php';

function group_input($key) {
    return isset($_POST[$key]) && is_string($_POST[$key])
        ? trim($_POST[$key])
        : '';
}

$subjects = $pdo->query(
    'SELECT id, subject_code, subject_name
     FROM subjects ORDER BY subject_code'
)->fetchAll();

$error = '';
$formId = 0;
$name = '';
$description = '';
$subjectId = 0;

$success = $_SESSION['group_success'] ?? '';
unset($_SESSION['group_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = group_input('action');

    $id = filter_var(
        group_input('id'),
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 0]]
    );

    if (!valid_csrf()) {
        $error = 'Your form expired. Please refresh and try again.';
    } elseif ($user['role'] !== 'student') {
        $error = 'Group membership and management are for students.';
    } elseif (
        $id === false
        || !in_array($action, ['save', 'join', 'leave', 'delete'], true)
        || ($action !== 'save' && $id === 0)
    ) {
        $error = 'Invalid group request.';
    } else {
        if ($action === 'save') {
            $formId = $id;
            $name = group_input('name');
            $description = group_input('description');

            $subjectId = filter_var(
                group_input('subject_id'),
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            ) ?: 0;

            $subjectIds = array_map(
                'intval',
                array_column($subjects, 'id')
            );

            if ($name === '' || mb_strlen($name) > 100) {
                $error = 'Enter a group name of up to 100 characters.';
            } elseif (
                $description === ''
                || mb_strlen($description) > 2000
            ) {
                $error = 'Enter a description of 1–2,000 characters.';
            } elseif (!in_array($subjectId, $subjectIds, true)) {
                $error = 'Select a valid subject.';
            }
        }

        if (!$error) {
            try {
                $pdo->beginTransaction();

                // Lock the group while changing its membership or details.
                if ($id > 0) {
                    $stmt = $pdo->prepare(
                        'SELECT id, owner_id FROM study_groups
                         WHERE id = ? FOR UPDATE'
                    );
                    $stmt->execute([$id]);
                    $group = $stmt->fetch();

                    if (!$group) {
                        throw new RuntimeException('This group no longer exists.');
                    }
                }

                if ($action === 'save') {
                    if ($id === 0) {
                        $stmt = $pdo->prepare(
                            'INSERT INTO study_groups
                             (owner_id, subject_id, name, description)
                             VALUES (?, ?, ?, ?)'
                        );
                        $stmt->execute([
                            $user['id'], $subjectId, $name, $description
                        ]);

                        $newId = (int) $pdo->lastInsertId();

                        $stmt = $pdo->prepare(
                            'INSERT INTO group_members (group_id, user_id)
                             VALUES (?, ?)'
                        );
                        $stmt->execute([$newId, $user['id']]);
                        $message = 'Group created. You are its first member.';
                    } else {
                        if ((int) $group['owner_id'] !== (int) $user['id']) {
                            throw new RuntimeException(
                                'Only the creator can edit this group.'
                            );
                        }

                        $stmt = $pdo->prepare(
                            'UPDATE study_groups
                             SET subject_id = ?, name = ?, description = ?
                             WHERE id = ? AND owner_id = ?'
                        );
                        $stmt->execute([
                            $subjectId, $name, $description, $id, $user['id']
                        ]);
                        $message = 'Group updated.';
                    }
                } elseif ($action === 'join') {
                    $check = $pdo->prepare(
                        'SELECT user_id FROM group_members
                         WHERE group_id = ? AND user_id = ?'
                    );
                    $check->execute([$id, $user['id']]);

                    if ($check->fetch()) {
                        $message = 'You are already a member.';
                    } else {
                        $stmt = $pdo->prepare(
                            'INSERT INTO group_members (group_id, user_id)
                             VALUES (?, ?)'
                        );
                        $stmt->execute([$id, $user['id']]);
                        $message = 'You joined the group.';
                    }
                } elseif ($action === 'leave') {
                    if ((int) $group['owner_id'] === (int) $user['id']) {
                        throw new RuntimeException(
                            'The creator must remain a member. You can delete your group instead.'
                        );
                    }

                    $stmt = $pdo->prepare(
                        'DELETE FROM group_members
                         WHERE group_id = ? AND user_id = ?'
                    );
                    $stmt->execute([$id, $user['id']]);
                    $message = 'You are no longer a member of this group.';
                } else {
                    if ((int) $group['owner_id'] !== (int) $user['id']) {
                        throw new RuntimeException(
                            'Only the creator can delete this group.'
                        );
                    }

                    $stmt = $pdo->prepare(
                        'DELETE FROM study_groups WHERE id = ? AND owner_id = ?'
                    );
                    $stmt->execute([$id, $user['id']]);
                    $message = 'Group deleted.';
                }

                $pdo->commit();

                $_SESSION['group_success'] = $message;
                header('Location: groups.php');
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
    if ($user['role'] !== 'student') {
        http_response_code(403);
        exit('Only student creators can edit groups.');
    }

    $editId = filter_var(
        $_GET['edit'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($editId === false) {
        http_response_code(400);
        exit('Invalid group ID.');
    }

    $stmt = $pdo->prepare(
        'SELECT id, name, description, subject_id
         FROM study_groups WHERE id = ? AND owner_id = ?'
    );
    $stmt->execute([$editId, $user['id']]);
    $editing = $stmt->fetch();

    if (!$editing) {
        http_response_code(404);
        exit('Group not found among your created groups.');
    }

    $formId = (int) $editing['id'];
    $name = $editing['name'];
    $description = $editing['description'];
    $subjectId = (int) $editing['subject_id'];
}

$stmt = $pdo->prepare(
    'SELECT g.id, g.owner_id, g.name, g.description,
            u.full_name AS owner_name,
            s.subject_code, s.subject_name,
            (SELECT COUNT(*) FROM group_members gm
             WHERE gm.group_id = g.id) AS member_count,
            EXISTS(
                SELECT 1 FROM group_members mine
                WHERE mine.group_id = g.id AND mine.user_id = ?
            ) AS is_member
     FROM study_groups g
     JOIN users u ON u.id = g.owner_id
     JOIN subjects s ON s.id = g.subject_id
     ORDER BY g.created_at DESC, g.id DESC'
);
$stmt->execute([$user['id']]);
$groups = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Study Groups | NSBM StudyCircle</title>
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
            max-width: 1000px;
            margin: auto;
            padding: 32px 20px;
        }
        .hero {
            padding: 30px;
            margin-top: 24px;
            background: #146b3d;
            color: white;
            border-radius: 18px;
        }
        .hero h1 { margin-top: 0; }
        p { line-height: 1.7; }
        .card {
            margin-top: 24px;
            padding: 26px;
            background: white;
            border: 1px solid #dce9df;
            border-radius: 16px;
            overflow-wrap: anywhere;
        }
        .muted { color: #52685b; }
        .badge {
            display: inline-block;
            margin: 0 8px 8px 0;
            padding: 6px 10px;
            border-radius: 20px;
            background: #e7f7ec;
            color: #146b3d;
            font-size: 13px;
        }
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
        textarea { min-height: 120px; resize: vertical; }
        input:focus, select:focus, textarea:focus {
            outline: 3px solid #d1efdc;
            border-color: #16834a;
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
            border: 0;
            border-radius: 8px;
            background: #16834a;
            color: white;
            font: inherit;
            cursor: pointer;
        }
        button:hover { background: #106337; }
        .delete { background: #fff0f0; color: #952525; }
        .delete:hover { background: #fbdede; }
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
    <a class="logo" href="<?= escape($dashboard) ?>">
        NSBM StudyCircle
    </a>
</header>

<main>
    <a href="<?= escape($dashboard) ?>">&larr; Back to dashboard</a>

    <section class="hero">
        <h1>Find your study group</h1>
        <p>Connect with classmates who are studying the same subjects.</p>
    </section>

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

    <?php if ($user['role'] === 'student'): ?>
        <section class="card" id="group-form">
            <h2><?= $formId ? 'Edit your group' : 'Create a study group' ?></h2>

            <?php if (!$subjects): ?>
                <p>An admin must add a subject before you create a group.</p>
            <?php else: ?>
                <form method="post" action="groups.php">
                    <input type="hidden" name="csrf_token"
                           value="<?= escape(csrf_token()) ?>">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= $formId ?>">

                    <label for="name">Group name</label>
                    <input id="name" name="name"
                           value="<?= escape($name) ?>"
                           maxlength="100"
                           placeholder="Example: Database Revision Group"
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

                    <label for="description">Group description</label>
                    <textarea id="description" name="description"
                              maxlength="2000"
                              placeholder="Describe what your group will study."
                              required><?= escape($description) ?></textarea>

                    <div class="actions">
                        <button type="submit">
                            <?= $formId ? 'Save changes' : 'Create group' ?>
                        </button>
                        <?php if ($formId): ?>
                            <a href="groups.php">Cancel editing</a>
                        <?php endif; ?>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <h2>Study groups (<?= count($groups) ?>)</h2>

    <?php if (!$groups): ?>
        <section class="card">
            <p>No groups yet. Create the first one above.</p>
        </section>
    <?php endif; ?>

    <?php foreach ($groups as $group): ?>
        <?php
        $isOwner = (int) $group['owner_id'] === (int) $user['id'];
        $isMember = (bool) $group['is_member'];
        ?>
        <article class="card">
            <span class="badge">
                <?= escape($group['subject_code']) ?>
            </span>

            <?php if ($isOwner): ?>
                <span class="badge">Your group</span>
            <?php elseif ($isMember): ?>
                <span class="badge">Member</span>
            <?php endif; ?>

            <h3><?= escape($group['name']) ?></h3>

            <?php if ($isMember): ?>
                <p>
                     <a href="group_sessions.php?group_id=<?= (int) $group['id'] ?>">
                        View &amp; schedule study sessions &rarr;
                    </a>
                </p>
            <?php endif; ?>

            
            <p><?= nl2br(escape($group['description'])) ?></p>

            <p class="muted">
                <?= escape($group['subject_name']) ?><br>
                Created by <?= escape($group['owner_name']) ?><br>
                <?= (int) $group['member_count'] ?> member(s)
            </p>

            <?php if ($user['role'] === 'student'): ?>
                <div class="actions">
                    <?php if ($isOwner): ?>
                        <a href="groups.php?edit=<?= (int) $group['id'] ?>#group-form">
                            Edit group
                        </a>

                        <form method="post" action="groups.php"
                              onsubmit="return confirm('Delete this group, its memberships, and any scheduled sessions?');">
                            <input type="hidden" name="csrf_token"
                                   value="<?= escape(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id"
                                   value="<?= (int) $group['id'] ?>">
                            <button class="delete" type="submit">
                                Delete group
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="post" action="groups.php">
                            <input type="hidden" name="csrf_token"
                                   value="<?= escape(csrf_token()) ?>">
                            <input type="hidden" name="id"
                                   value="<?= (int) $group['id'] ?>">
                            <input type="hidden" name="action"
                                   value="<?= $isMember ? 'leave' : 'join' ?>">
                            <button type="submit">
                                <?= $isMember ? 'Leave group' : 'Join group' ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</main>
</body>
</html>