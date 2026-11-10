<?php

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../common/Config.php';

$user = Auth::requireApi();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    Json::error('Absensi tidak valid', 422);
}

$db = new DBConnection();
$row = $db->selectOne('SELECT id, employee_id, selfie_path, selfie_mime_type FROM attendances WHERE id = ?', array($id));
if (!$row || !$row['selfie_path']) {
    Json::error('Foto absensi tidak ditemukan', 404);
}

// Pemilik atau HR yang berwenang saja
$isOwner = $row['employee_id'] == $user['employee_id'];
if (!$isOwner && $user['role'] !== 'hr') {
    Json::error('Anda tidak berwenang membuka foto ini', 403);
}

$base = realpath(Config::SELFIE_STORAGE_DIR);
$path = realpath(Config::SELFIE_STORAGE_DIR . '/' . str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $row['selfie_path']));
if ($base === false || $path === false || strpos($path, $base) !== 0 || !is_file($path)) {
    Json::error('File foto tidak ditemukan', 404);
}

header('Content-Type: ' . $row['selfie_mime_type']);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=300');
readfile($path);
exit;
