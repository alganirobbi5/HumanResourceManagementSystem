<?php

require_once __DIR__ . '/../common/DBConnection.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';

$user = Auth::requireApiHr();  // Hanya role HR yang boleh

$pdo = DBConnection::pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Kolom opsional; tetap kompatibel jika migrasi belum dijalankan.
$hasReason = false;
$hasComment = false;
try {
    $col = $pdo->query("SHOW COLUMNS FROM leaves LIKE 'reason'");
    $hasReason = $col && $col->fetch() !== false;
    $col = $pdo->query("SHOW COLUMNS FROM leaves LIKE 'comment'");
    $hasComment = $col && $col->fetch() !== false;
} catch (PDOException $e) {
    $hasReason = false;
    $hasComment = false;
}

$extra = '';
if ($hasReason) $extra .= ", l.reason AS alasan";
if ($hasComment) $extra .= ", l.comment";

// Menampilkan seluruh pengajuan cuti dari semua karyawan (khusus role HR)
$query = "SELECT
            l.id,
            l.employee_id,
            TRIM(CONCAT(COALESCE(e.first_name, ''), ' ', COALESCE(e.last_name, ''))) AS employee_name,
            l.type AS reason,
            l.leave_from,
            l.leave_to,
            l.status
            $extra
          FROM leaves l
          LEFT JOIN employees e ON e.id = l.employee_id
          ORDER BY l.id DESC";

$stmt = $pdo->query($query);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

http_response_code(200);
echo json_encode([
    "success" => true,
    "message" => "Laporan cuti seluruh karyawan",
    "data" => $data
]);
exit;
?>
