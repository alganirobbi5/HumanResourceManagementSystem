<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

require_once __DIR__ . '/../common/DBConnection.php';

// Ambil employee_id dari URL (contoh: riwayat_cuti.php?employee_id=2)
$employee_id = isset($_GET['employee_id']) ? $_GET['employee_id'] : null;

if ($employee_id) {
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=hrm;charset=utf8mb4", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Ambil data cuti berdasarkan employee_id
        $stmt = $pdo->prepare("SELECT id, type, leav_from, leav_to, staus FROM leaves WHERE employee_id = ? ORDER BY id DESC");
        $stmt->execute([$employee_id]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "data" => $data
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Gagal: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "employee_id tidak ditemukan"]);
}
?>