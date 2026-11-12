<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once __DIR__ . '/../common/DBConnection.php';

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->employee_id) && !empty($data->start_date) && !empty($data->end_date) && !empty($data->reason)) {
    try {
        // GUNAKAN PDO MANUAL
        $pdo = new PDO("mysql:host=localhost;dbname=hrm;charset=utf8mb4", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Gunakan tanda tanya (?) untuk created_at, lalu kirim time() di array
        $query = "INSERT INTO leaves (employee_id, type, leave_from, leave_to, total_leave, status, created_at) 
          VALUES (?, ?, ?, ?, 1, 1, ?)";
                  
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $data->employee_id, 
            $data->reason, 
            $data->start_date, 
            $data->end_date,
            time() // <-- FUNGSI time() DIPANGGIL DI SINI
        ]);
        
        http_response_code(201);
        echo json_encode(["status" => "success", "message" => "Pengajuan cuti berhasil dikirim"]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error", 
            "message" => "MYSQL ERROR: " . $e->getMessage()
        ]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Data cuti tidak lengkap"]);
}
?>