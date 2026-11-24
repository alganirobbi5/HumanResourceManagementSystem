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

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->nama_perusahaan) && !empty($data->kode_perusahaan)) {
    try {
        $pdo = DBConnection::pdo();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->prepare("UPDATE company_profile SET nama_perusahaan = ?, kode_perusahaan = ? WHERE id = 1");
        $stmt->execute(array($data->nama_perusahaan, $data->kode_perusahaan));
        echo json_encode(array("status" => "success", "message" => "Profil perusahaan berhasil diubah"));
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(array("status" => "error", "message" => $e->getMessage()));
    }
} else {
    http_response_code(400);
    echo json_encode(array("status" => "error", "message" => "Data tidak lengkap"));
}
