<?php

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../service/NotificationService.php';

$user = Auth::requireApi();
$service = new NotificationService();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    Json::ok('OK', array(
        'data' => $service->forUser((int)$user['id'], 15),
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
