<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

require_once __DIR__ . '/../common/DBConnection.php';

try {
    $pdo = new PDO("mysql:host=localhost;dbname=hrm;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ambil data absensi (Join dengan tabel employees untuk dapat nama)
    $query = "SELECT a.id, a.date, a.clock_in, a.clock_out, a.status, 
                     e.first_name, e.last_name 
              FROM attendances a 
              LEFT JOIN employees e ON a.employee_id = e.id 
              ORDER BY a.date DESC, a.clock_in DESC";
              
    $stmt = $pdo->query($query);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    http_response_code(200);
    echo json_encode(["status" => "success", "data" => $data]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Gagal: " . $e->getMessage()]);
}
?>