<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once __DIR__ . '/../common/DBConnection.php';

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->nama_perusahaan) && !empty($data->kode_perusahaan)) {
    try {
        $pdo = DBConnection::pdo();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->prepare("UPDATE company_profile SET nama_perusahaan = ?, kode_perusahaan = ? WHERE id = 1");
        $stmt->execute([$data->nama_perusahaan, $data->kode_perusahaan]);
        echo json_encode(["status" => "success", "message" => "Profil perusahaan berhasil diubah"]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Data tidak lengkap"]);
}
?>