<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../common/DBConnection.php';

$user = Auth::requireApi();
$db = new DBConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    Auth::requireApiHr();
    $rows = $db->selectAll(
        'SELECT u.id, u.username, (u.fcm_token IS NOT NULL) AS has_token, u.fcm_token_updated_at
           FROM users u WHERE u.is_active = 1 ORDER BY u.id'
    );
    Json::ok('OK', array('data' => $rows));
}

if ($method === 'POST') {
    $body = Json::readRequest();
    $token = isset($body['fcm_token']) ? trim((string)$body['fcm_token']) : '';
    if ($token === '' || strlen($token) > 255) {
        Json::error('fcm_token wajib diisi (maks 255 karakter)', 400);
    }
    $db->run(
        'UPDATE users SET fcm_token = ?, fcm_token_updated_at = NOW() WHERE id = ?',
        array($token, (int)$user['id'])
    );
    Json::ok('FCM token tersimpan');
}

if ($method === 'DELETE') {
    $db->run(
        'UPDATE users SET fcm_token = NULL, fcm_token_updated_at = NULL WHERE id = ?',
        array((int)$user['id'])
    );
    Json::ok('FCM token dihapus');
}

Json::error('Method tidak diizinkan', 405);
