<?php

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../service/NotificationService.php';

$user = Auth::requireApi();
$service = new NotificationService();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 50;
    $rows = $service->forUser((int)$user['id'], $limit);
    if (isset($user['role']) && $user['role'] === 'super_admin') {
        $rows = array_values(array_filter($rows, function (array $row) {
            return isset($row['type']) && $row['type'] === 'announcement';
        }));
    }
    Json::ok('OK', array(
        'data' => $rows,
        'unread' => $service->unreadCount((int)$user['id'])
    ));
}

if ($method === 'POST') {
    $body = Json::readBody();
    if (empty($body['action']) || $body['action'] !== 'mark_read') {
        $body = $_POST;
    }
    $id = isset($body['id']) ? (int)$body['id'] : null;
    $service->markRead((int)$user['id'], $id);
    Json::ok('Notifikasi ditandai sudah dibaca', array('unread' => $service->unreadCount((int)$user['id'])));
}

Json::error('Method tidak diizinkan', 405);
