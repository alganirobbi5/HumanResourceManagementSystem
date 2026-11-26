<?php

require_once __DIR__ . '/../common/DBConnection.php';
require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../service/CompanyMasterService.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

Auth::requireApi();

try {
    $pdo = DBConnection::pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $master = new CompanyMasterService($pdo);
    $data = $master->jamFor(Auth::companyId());

    echo json_encode(["status" => "success", "data" => $data]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Gagal memuat jam kerja"]);
}
