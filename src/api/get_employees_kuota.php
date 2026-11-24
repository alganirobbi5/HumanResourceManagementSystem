<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

try {
    $pdo = new PDO("mysql:host=localhost;dbname=hris;charset=utf8mb4", "HRIS", "Admin@2026");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ambil semua karyawan, gabungkan dengan kuota cuti (jika ada)
    $query = "SELECT e.id as employee_id, e.first_name, e.last_name, 
                     COALESCE(el.total_leave_yarly, 0) as total_kuota, 
                     COALESCE(el.remaining_leav, 0) as sisa_kuota
              FROM employees e
              LEFT JOIN employee_leaves el ON e.id = el.employee_id
              ORDER BY e.first_name ASC";
              
    $stmt = $pdo->query($query);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["status" => "success", "data" => $data]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>