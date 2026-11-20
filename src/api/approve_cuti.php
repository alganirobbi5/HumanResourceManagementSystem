<?php

require_once __DIR__ . '/../common/DBConnection.php';
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

try {
    $pdo = DBConnection::pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Cek keberadaan pengajuan cuti
    $stmt = $pdo->prepare("SELECT id, employee_id, status FROM leaves WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$data['leave_id']]);
    $leave = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$leave) {
        Json::error('Pengajuan cuti tidak ditemukan', 404);
    }

    // Komentar opsional dari HR (kolom comment opsional; tetap kompatibel
    // jika skema database belum memiliki kolom tersebut).
    $comment = isset($data['comment']) ? trim((string)$data['comment']) : '';
    $hasComment = false;
    try {
        $col = $pdo->query("SHOW COLUMNS FROM leaves LIKE 'comment'");
        $hasComment = $col && $col->fetch() !== false;
    } catch (PDOException $e) {
        $hasComment = false;
    }

    // Update status cuti
    if ($hasComment) {
        $stmtUpdate = $pdo->prepare("UPDATE leaves SET status = ?, comment = ? WHERE id = ?");
        $result = $stmtUpdate->execute([$data['status'], $comment, (int)$data['leave_id']]);
    } else {
        $stmtUpdate = $pdo->prepare("UPDATE leaves SET status = ? WHERE id = ?");
        $result = $stmtUpdate->execute([$data['status'], (int)$data['leave_id']]);
    }

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
    }

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Gagal memperbarui status cuti"
    ]);
    exit;
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Terjadi kesalahan server"
    ]);
    exit;
}
?>
