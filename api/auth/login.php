<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$email = strtolower(trim($data['email'] ?? ''));
$password = $data['password'] ?? '';

if ($email === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Email and password required']);
    exit;
}

try {
    $user = crud()->raw(
        "SELECT u.*, r.slug AS role_slug, r.name AS role_name
         FROM users u
         JOIN roles r ON r.id = u.role_id
         WHERE u.email = :email AND u.deleted_at IS NULL",
        ['email' => $email]
    )->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
        exit;
    }

    if ((int)$user['is_active'] !== 1) {
        http_response_code(403);
        echo json_encode(['error' => 'Account inactive']);
        exit;
    }

    do_login(db(), (int)$user['id']);

    echo json_encode([
        'id' => (int)$user['id'],
        'email' => $user['email'],
        'username' => $user['username'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'role_id' => (int)$user['role_id'],
        'role_slug' => $user['role_slug'],
        'role_name' => $user['role_name'],
        'permissions' => $user['permissions'] ?? [],
        'page_access' => $user['page_access'] ?? null
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}