<?php

require_once __DIR__ . '/../common/DBConnection.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';

// Hanya HR yang boleh menghapus riwayat cuti.
$user = Auth::requireApiHr();

$data = json_decode(file_get_contents("php://input"));
$id = isset($data->id) ? (int)$data->id : 0;
if ($id <= 0 && isset($data->leave_id)) {
    $id = (int)$data->leave_id;
}

if ($id <= 0) {
    Json::error('id cuti wajib diisi', 400);
}

try {
    $pdo = DBConnection::pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SELECT id FROM leaves WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        Json::error('Data cuti tidak ditemukan', 404);
    }

    // Soft-delete bila kolom deleted_at tersedia, hapus permanen jika tidak.
    $col = $pdo->query("SHOW COLUMNS FROM leaves LIKE 'deleted_at'");
    if ($col && $col->fetch()) {
        $stmt = $pdo->prepare("UPDATE leaves SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            Json::error('Data cuti sudah dihapus', 404);
        }
    } else {
        $stmt = $pdo->prepare("DELETE FROM leaves WHERE id = ?");
        $stmt->execute([$id]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Riwayat cuti berhasil dihapus'
    ]);
    exit;
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Gagal menghapus riwayat cuti'
    ]);
    exit;
}
