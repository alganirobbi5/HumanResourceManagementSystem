<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';

$user = Auth::requireApi();
$employee_id = $user['employee_id'];

if (!$employee_id) {
    Json::error('Akun ini tidak terhubung ke data karyawan', 400);
}

$pdo = new PDO("mysql:host=localhost;dbname=hris;charset=utf8mb4", "HRIS", "Admin@2026");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// HANYA RIWAYAT CUTI MILIK USER YANG TERLOGIN
// Lewatkan parameter employee_id dari GET untuk keamanan
$stmt = $pdo->prepare("SELECT id, type, leave_from, leave_to, status FROM leaves WHERE employee_id = ? ORDER BY id DESC");
$stmt->execute([$employee_id]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

http_response_code(200);
echo json_encode([
    "success" => true,
    "message" => "Riwayat cuti berhasil diambil",
    "data" => $data
]);
exit;
?>