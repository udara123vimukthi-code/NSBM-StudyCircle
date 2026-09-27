<?php
require_once __DIR__ . '/config/auth.php';
$user = require_admin();

function category_input($key) {
    return isset($_POST[$key]) && is_string($_POST[$key])
        ? trim($_POST[$key])
        : '';
}

$error = '';
$id = 0;
$name = '';

$success = $_SESSION['category_success'] ?? '';
unset($_SESSION['category_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = category_input('action');

    $submittedId = filter_var(
        category_input('id'),
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 0]]
    );

    if (!valid_csrf()) {
        $error = 'Your form expired. Please try again.';
    } elseif ($submittedId === false) {
        $error = 'Invalid category ID.';
    } elseif (!in_array($action, ['save', 'delete'], true)) {
        $error = 'Invalid action.';
    } else {
        if ($action === 'save') {
            $id = $submittedId;
            $name = category_input('name');

            if ($name === '' || mb_strlen($name) > 100) {
                $error = 'Enter a category name of up to 100 characters.';
            }
        } elseif ($submittedId < 1) {
            $error = 'Choose a valid category to delete.';
        }

        if (!$error) {
            try {
                if ($submittedId > 0) {
                    $check = $pdo->prepare(
                        'SELECT id FROM categories WHERE id = ?'
                    );
                    $check->execute([$submittedId]);

                    if (!$check->fetch()) {
                        $error = 'This category no longer exists.';
                    }
                }

                if (!$error) {
                    if ($action === 'delete') {
                        $stmt = $pdo->prepare(
                            'DELETE FROM categories WHERE id = ?'
                        );
                        $stmt->execute([$submittedId]);
                        $message = 'Category deleted successfully.';
                    } elseif ($id > 0) {
                        $stmt = $pdo->prepare(
                            'UPDATE categories SET name = ? WHERE id = ?'
                        );
                        $stmt->execute([$name, $id]);
                        $message = 'Category updated successfully.';
                    } else {
                        $stmt = $pdo->prepare(
                            'INSERT INTO categories (name) VALUES (?)'
                        );
                        $stmt->execute([$name]);
                        $message = 'Category added successfully.';
                    }

                    $_SESSION['category_success'] = $message;
                    header('Location: admin_categories.php');
                    exit;
                }
            } catch (PDOException $e) {
                $databaseError = (int) ($e->errorInfo[1] ?? 0);

                if ($databaseError === 1062) {
                    $error = 'That category already exists.';
                } elseif ($databaseError === 1451) {
                    $error = 'This category is in use and cannot be deleted.';
                } else {
                    error_log($e->getMessage());
                    $error = 'Unable to save your changes. Please try again.';
                }
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
        exit('Invalid category ID.');
    }

    $stmt = $pdo->prepare(
        'SELECT id, name FROM categories WHERE id = ?'
    );
    $stmt->execute([$editId]);
    $category = $stmt->fetch();

    if (!$category) {
        http_response_code(404);
        exit('Category not found.');
    }

    $id = (int) $category['id'];
    $name = $category['name'];
}

$categories = $pdo->query(
    'SELECT id, name FROM categories ORDER BY name'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories | NSBM StudyCircle</title>
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
            max-width: 900px;
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
        }
        label {
            display: block;
            margin: 18px 0 8px;
            font-weight: bold;
        }
        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #b6cabb;
            border-radius: 8px;
            font: inherit;
        }
        input:focus {
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
        .actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
        }
        .form-actions { margin-top: 20px; }
        .delete { background: #fff0f0; color: #952525; }
        .delete:hover { background: #fbdede; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td {
            padding: 15px 12px;
            text-align: left;
            border-bottom: 1px solid #e2ece5;
        }
        th { background: #f0f8f3; }
        td { overflow-wrap: anywhere; }
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
    <h1>Resource categories</h1>
    <p>Organise study materials by their type.</p>

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

    <section class="card" id="category-form">
        <h2><?= $id > 0 ? 'Edit category' : 'Add a category' ?></h2>

        <form method="post" action="admin_categories.php">
            <input type="hidden" name="csrf_token"
                   value="<?= escape(csrf_token()) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $id ?>">

            <label for="name">Category name</label>
            <input id="name" name="name"
                   value="<?= escape($name) ?>"
                   maxlength="100"
                   placeholder="Example: Lecture Notes" required>

            <div class="actions form-actions">
                <button type="submit">
                    <?= $id > 0 ? 'Save changes' : 'Add category' ?>
                </button>

                <?php if ($id > 0): ?>
                    <a href="admin_categories.php">Cancel editing</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="card">
        <h2>Categories (<?= count($categories) ?>)</h2>

        <?php if (!$categories): ?>
            <p>No categories yet. Add your first category above.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th scope="col">Category name</th>
                        <th scope="col">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><?= escape($category['name']) ?></td>
                            <td>
                                <div class="actions">
                                    <a href="admin_categories.php?edit=<?= (int) $category['id'] ?>#category-form">
                                        Edit
                                    </a>

                                    <form method="post"
                                          action="admin_categories.php"
                                          onsubmit="return confirm('Delete this category? This cannot be undone.');">
                                        <input type="hidden" name="csrf_token"
                                               value="<?= escape(csrf_token()) ?>">
                                        <input type="hidden" name="action"
                                               value="delete">
                                        <input type="hidden" name="id"
                                               value="<?= (int) $category['id'] ?>">
                                        <button class="delete" type="submit">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>