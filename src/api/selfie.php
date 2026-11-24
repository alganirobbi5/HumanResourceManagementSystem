<?php

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../common/Config.php';

$user = Auth::requireApi();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    Json::error('Absensi tidak valid', 422);
}

$type = isset($_GET['type']) ? strtolower(trim((string)$_GET['type'])) : 'in';
if ($type !== 'in' && $type !== 'out') {
    $type = 'in';
}
$pathColumn = $type === 'out' ? 'selfie_out_path' : 'selfie_path';
$mimeColumn = $type === 'out' ? 'selfie_out_mime_type' : 'selfie_mime_type';

$db = new DBConnection();
if (!$db->selectOne("SHOW COLUMNS FROM attendances LIKE '" . $pathColumn . "'")) {
    Json::error('Foto absensi tidak ditemukan', 404);
}
$mimeSelect = '';
if ($db->selectOne("SHOW COLUMNS FROM attendances LIKE '" . $mimeColumn . "'")) {
    $mimeSelect = ', ' . $mimeColumn . ' AS selfie_mime_type';
}
$row = $db->selectOne(
    'SELECT id, employee_id, ' . $pathColumn . ' AS selfie_path' . $mimeSelect . '
     FROM attendances WHERE id = ?',
    array($id)
);
if (!$row || empty($row['selfie_path'])) {
    Json::error('Foto absensi tidak ditemukan', 404);
}

// Pemilik atau HR yang berwenang saja
$isOwner = $row['employee_id'] == $user['employee_id'];
if (!$isOwner && $user['role'] !== 'hr') {
    Json::error('Anda tidak berwenang membuka foto ini', 403);
}

$base = realpath(Config::SELFIE_STORAGE_DIR);
$path = realpath(Config::SELFIE_STORAGE_DIR . '/' . str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $row['selfie_path']));
if ($base === false || $path === false || strpos($path, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) {
    Json::error('File foto tidak ditemukan', 404);
}

$mimeType = !empty($row['selfie_mime_type']) ? $row['selfie_mime_type'] : 'image/jpeg';
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=300');
readfile($path);
exit;
