<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../common/Config.php';

$user = Auth::requireApiHr();

$data = Json::readBody();

if (empty($data['leave_id']) || empty($data['status'])) {
    Json::error('leave_id dan status wajib diisi', 400);
}

// Validasi status: 1 = Pending, 2 = Disetujui, 3 = Ditolak
if (!in_array($data['status'], [1, 2, 3])) {
    Json::error('Status tidak valid (harus 1=Pending, 2=Disetujui, 3=Ditolak)', 422);
}

// Cek keberadaan pengajuan cuti
$pdo = new PDO("mysql:host=localhost;dbname=hris;charset=utf8mb4", "HRIS", "Admin@2026");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $pdo->prepare("SELECT id, employee_id, status FROM leaves WHERE id = LIMIT 1");
$stmt->execute([(int)$data['leave_id']]);
$leave = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$leave) {
    Json::error('Pengajuan cuti tidak ditemukan', 404);
}

// Opsional: bisa tambahan validasi:
// - Apakah cuti ini milik karyawan yang sedang login? (bukan untuk approve/reject)
// - Status saat ini harus masih Pending (1) sebelum bisa di-approve/reject

// Update status cuti
$query = "UPDATE leaves SET status = ? WHERE id = ?";
$stmtUpdate = $pdo->prepare($query);
$result = $stmtUpdate->execute([$data['status'], (int)$data['leave_id']]);

if ($result) {
    http_response_code(200);
    echo json_encode([
        "success" => true,
        "message" => "Status cuti berhasil diperbarui",
        "data" => [
            "leave_id" => (int)$data['leave_id'],
            "status_baru" => $data['status']
        ]
    ]);
    exit;
} else {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Gagal memperbarui status cuti"
    ]);
    exit;
}
?>