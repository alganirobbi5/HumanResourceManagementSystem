<?php

require_once __DIR__ . '/../common/DBConnection.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';

$user = Auth::requireApi();
$employee_id = $user['employee_id'];
$isHr = (isset($user['role']) && $user['role'] === 'hr');
$lihatSemua = ($employee_id === null && $isHr);

if ($employee_id === null && !$lihatSemua) {
    Json::error('Akun ini tidak terhubung ke data karyawan', 400);
}

$pdo = DBConnection::pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Kolom opsional (judul/alasan tersimpan di `type`). Tetap kompatibel
// jika skema database belum memiliki kolom reason/comment.
$hasReason = false;
try {
    $col = $pdo->query("SHOW COLUMNS FROM leaves LIKE 'reason'");
    $hasReason = $col && $col->fetch() !== false;
} catch (PDOException $e) {
    $hasReason = false;
}
$hasComment = false;
try {
    $col = $pdo->query("SHOW COLUMNS FROM leaves LIKE 'comment'");
    $hasComment = $col && $col->fetch() !== false;
} catch (PDOException $e) {
    $hasComment = false;
}

$fields = "id, employee_id, type, leave_from, leave_to, status, FROM_UNIXTIME(created_at) AS created_at";
if ($hasReason) $fields .= ", reason";
if ($hasComment) $fields .= ", comment";

// Sembunyikan data yang sudah dihapus HR (soft-delete).
$hasDeletedAt = false;
try {
    $col = $pdo->query("SHOW COLUMNS FROM leaves LIKE 'deleted_at'");
    $hasDeletedAt = $col && $col->fetch() !== false;
} catch (PDOException $e) {
    $hasDeletedAt = false;
}
$filter = $hasDeletedAt ? " WHERE deleted_at IS NULL" : "";

// Admin HR tidak memiliki employee_id: tampilkan seluruh pengajuan.
// Karyawan tetap hanya melihat riwayat miliknya (employee_id dari token/session).
if ($lihatSemua) {
    $stmt = $pdo->query("SELECT $fields FROM leaves $filter ORDER BY id DESC");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $message = 'Riwayat cuti seluruh karyawan';
} else {
    $filter .= $hasDeletedAt ? " AND employee_id = ?" : " WHERE employee_id = ?";
    $stmt = $pdo->prepare("SELECT $fields FROM leaves $filter ORDER BY id DESC");
    $stmt->execute([$employee_id]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $message = 'Riwayat cuti berhasil diambil';
}

http_response_code(200);
echo json_encode([
    "success" => true,
    "message" => $message,
    "data" => $data
]);
exit;
?>
