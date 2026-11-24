<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../common/DBConnection.php';

try {
    $pdo = DBConnection::pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->query("SELECT nama_perusahaan, kode_perusahaan FROM company_profile LIMIT 1");
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($data)) {
        $data = array(
            'nama_perusahaan' => 'PT Contoh Maju Sejahtera',
            'kode_perusahaan' => 'CMJ-2026',
        );
    }
    echo json_encode(array("status" => "success", "data" => $data));
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(array("status" => "error", "message" => $e->getMessage()));
}
