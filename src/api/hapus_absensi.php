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

// Hanya HR yang boleh menghapus data absensi.
$user = Auth::requireApiHr();

$data = json_decode(file_get_contents("php://input"));
$id = isset($data->id) ? (int)$data->id : 0;
if ($id <= 0) {
    Json::error('id absensi wajib diisi', 400);
}

try {
    $pdo = DBConnection::pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SELECT id FROM attendances WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        Json::error('Data absensi tidak ditemukan', 404);
    }

    $col = $pdo->query("SHOW COLUMNS FROM attendances LIKE 'deleted_at'");
    if ($col && $col->fetch()) {
        $stmt = $pdo->prepare("UPDATE attendances SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            Json::error('Data absensi sudah dihapus', 404);
        }
    } else {
        $stmt = $pdo->prepare("DELETE FROM attendances WHERE id = ?");
        $stmt->execute([$id]);
    }

    Json::ok('Data absensi berhasil dihapus');
} catch (PDOException $e) {
    Json::error('Gagal menghapus data absensi', 500);
}
