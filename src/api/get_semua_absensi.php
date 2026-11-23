<?php

require_once __DIR__ . '/../common/DBConnection.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';

$user = Auth::requireApiHr();  // Hanya role HR yang boleh

$pdo = DBConnection::pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Mengambil semua data absensi, digabung dengan nama karyawan
$query = "SELECT a.id, a.date, a.clock_in, a.clock_out, a.status, 
                 a.latitude, a.longitude, 
                 e.first_name, e.last_name 
          FROM attendances a 
          LEFT JOIN employees e ON a.employee_id = e.id 
          ORDER BY a.date DESC, a.clock_in DESC";

$stmt = $pdo->query($query);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

http_response_code(200);
echo json_encode([
    "success" => true,
    "message" => "Daftar absensi seluruh karyawan",
    "data" => $data
]);
exit;
?>