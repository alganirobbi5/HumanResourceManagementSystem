<?php

require_once __DIR__ . '/../common/DBConnection.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->nama_lokasi) && !empty($data->latitude) && !empty($data->longitude) && !empty($data->radius_meter)) {
    try {
        $pdo = DBConnection::pdo();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $query = "INSERT INTO office_locations (nama_lokasi, latitude, longitude, radius_meter) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $data->nama_lokasi,
            $data->latitude,
            $data->longitude,
            $data->radius_meter
        ]);

        http_response_code(201);
        echo json_encode(["status" => "success", "message" => "Lokasi berhasil ditambahkan"]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "MYSQL ERROR: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Data tidak lengkap"]);
}
?>