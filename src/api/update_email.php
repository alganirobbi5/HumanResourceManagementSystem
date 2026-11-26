<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../common/DBConnection.php';
require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';

$user = Auth::requireApi();
$data = Json::readRequest();

$email = isset($data['email']) ? trim((string)$data['email']) : '';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    Json::error('Alamat email tidak valid', 422);
}

try {
    $pdo = DBConnection::pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $col = $pdo->query("SHOW COLUMNS FROM employees LIKE 'email'");
    if ($col && $col->fetch() && $user['employee_id']) {
        $stmt = $pdo->prepare('UPDATE employees SET email = ? WHERE id = ?');
        $stmt->execute(array($email, (int)$user['employee_id']));
    } else {
        $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'email'");
        if (!$col || !$col->fetch()) {
            Json::error('Kolom email belum tersedia pada database', 500);
        }
        $stmt = $pdo->prepare('UPDATE users SET email = ? WHERE id = ?');
        $stmt->execute(array($email, (int)$user['id']));
    }

    Json::ok('Email profil berhasil disimpan', array('email' => $email));
} catch (PDOException $e) {
    Json::error('Gagal menyimpan email profil', 500);
}
