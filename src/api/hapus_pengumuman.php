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

// Hanya HR yang boleh menghapus pengumuman.
$user = Auth::requireApiHr();
$companyId = Auth::companyId();

$data = json_decode(file_get_contents("php://input"));
$id = isset($data->id) ? (int)$data->id : 0;

if ($id <= 0) {
    Json::error('id pengumuman wajib diisi', 400);
}

try {
    $pdo = DBConnection::pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $deleteSql = 'DELETE FROM notices WHERE id = ?';
    $deleteParams = array($id);
    if ($companyId !== null) {
        $deleteSql .= ' AND (company_id = ? OR company_id IS NULL)';
        $deleteParams[] = (int)$companyId;
    }
    $stmt = $pdo->prepare($deleteSql);
    $stmt->execute($deleteParams);

    if ($stmt->rowCount() === 0) {
        Json::error('Pengumuman tidak ditemukan', 404);
    }

    echo json_encode([
        "success" => true,
        "message" => "Pengumuman berhasil dihapus"
    ]);
    exit;
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Gagal menghapus pengumuman"
    ]);
    exit;
}
?>
