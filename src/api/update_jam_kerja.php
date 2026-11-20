<?php

require_once __DIR__ . '/../common/DBConnection.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->jam_masuk) && !empty($data->jam_keluar)) {
    try {
        $pdo = DBConnection::pdo();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $pdo->prepare("UPDATE pengaturan_jam SET jam_masuk = ?, jam_keluar = ? WHERE id = 1");
        $stmt->execute([$data->jam_masuk, $data->jam_keluar]);
        
        echo json_encode(["status" => "success", "message" => "Jam kerja berhasil diubah"]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Data tidak lengkap"]);
}
?>