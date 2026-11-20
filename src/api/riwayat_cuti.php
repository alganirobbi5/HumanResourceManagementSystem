<?php

require_once __DIR__ . '/../common/DBConnection.php';
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

$fields = "id, type, leave_from, leave_to, status";
if ($hasReason) $fields .= ", reason";
if ($hasComment) $fields .= ", comment";

// HANYA RIWAYAT CUTI MILIK USER YANG TERLOGIN (employee_id dari token/session)
// Lewatkan parameter employee_id dari GET untuk keamanan
$stmt = $pdo->prepare("SELECT $fields FROM leaves WHERE employee_id = ? ORDER BY id DESC");
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
