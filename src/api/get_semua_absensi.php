<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

try {
    $pdo = new PDO("mysql:host=localhost;dbname=hris;charset=utf8mb4", "HRIS", "Admin@2026");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Mengambil semua data absensi, digabung dengan nama karyawan
    $query = "SELECT a.id, a.date, a.clock_in, a.clock_out, a.status, 
                     e.first_name, e.last_name 
              FROM attendances a 
              LEFT JOIN employees e ON a.employee_id = e.id 
              ORDER BY a.date DESC, a.clock_in DESC";
              
    $stmt = $pdo->query($query);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["status" => "success", "data" => $data]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>