<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

function escape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function valid_csrf() {
    $token = $_POST['csrf_token'] ?? '';

    return is_string($token)
        && hash_equals(csrf_token(), $token);
}

function require_login() {
    global $pdo;

    $userId = $_SESSION['user_id'] ?? null;

    if (!$userId) {
        header('Location: login.php');
        exit;
    }

    $stmt = $pdo->prepare(
        'SELECT id, full_name, email, student_id, role, status
         FROM users WHERE id = ?'
    );
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || $user['status'] !== 'active') {
        unset($_SESSION['user_id']);
        session_regenerate_id(true);
        header('Location: login.php');
        exit;
    }

    header('Cache-Control: no-store');

    return $user;
}

function require_admin() {
    $user = require_login();

    if ($user['role'] !== 'admin') {
        http_response_code(403);
        exit('Access denied. This page is for administrators only.');
    }

    return $user;
}