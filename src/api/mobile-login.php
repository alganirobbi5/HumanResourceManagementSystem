<?php

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Config.php';
require_once __DIR__ . '/../common/Json.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Json::error('Method tidak diizinkan', 405);
}

$body = Json::readBody();
$username = isset($body['username']) ? trim((string)$body['username']) : '';
$password = isset($body['password']) ? (string)$body['password'] : '';

if ($username === '' || $password === '') {
    Json::error('Username dan password wajib diisi', 400);
}

$db = new DBConnection();

$user = $db->selectOne(
    'SELECT u.*, e.first_name, e.last_name
     FROM users u
     LEFT JOIN employees e ON e.id = u.employee_id
     WHERE u.username = ? AND u.is_active = 1
     LIMIT 1',
    array($username)
);

if (!$user || !password_verify($password, $user['password_hash'])) {
    Json::error('Username atau password salah', 401);
}

$user['display_name'] = trim($user['first_name'] . ' ' . $user['last_name']) !== ''
    ? trim($user['first_name'] . ' ' . $user['last_name'])
    : $user['username'];

Auth::login($user);

Json::ok('Login berhasil', array(
    'data' => array(
        'user_id' => (int)$user['id'],
        'username' => $user['username'],
        'role' => $user['role'],
        'nama_lengkap' => $user['display_name'],
        'employee_id' => $user['employee_id'] !== null ? (int)$user['employee_id'] : null,
        'api_token' => $_SESSION['api_token'],
    ),
));
