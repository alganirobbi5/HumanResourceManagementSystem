<?php

require_once __DIR__ . '/../common/DBConnection.php';
require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../service/CompanyMasterService.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

Auth::requireApiHr();

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->jam_masuk) && !empty($data->jam_keluar)) {
    try {
        $pdo = DBConnection::pdo();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $master = new CompanyMasterService($pdo);
        $master->saveJam(Auth::companyId(), $data->jam_masuk, $data->jam_keluar);

        echo json_encode(["status" => "success", "message" => "Jam kerja berhasil diubah"]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Gagal menyimpan jam kerja"]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Data tidak lengkap"]);
}
