<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

require_once __DIR__ . '/../common/DBConnection.php';

try {
    $pdo = new PDO("mysql:host=localhost;dbname=hrm;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Menampilkan seluruh pengajuan cuti dari semua karyawan (tanpa filter employee_id).
    $query = "SELECT
                l.id,
                l.employee_id,
                TRIM(CONCAT(COALESCE(e.first_name, ''), ' ', COALESCE(e.last_name, ''))) AS employee_name,
                l.type AS reason,
                l.leave_from,
                l.leave_to,
                l.status
              FROM leaves l
              LEFT JOIN employees e ON e.id = l.employee_id
              ORDER BY l.id DESC";

    $stmt = $pdo->query($query);
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
?>
