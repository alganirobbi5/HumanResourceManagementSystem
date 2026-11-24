<?php

require_once __DIR__ . '/../common/DBConnection.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';

$user = Auth::requireApiHr();  // Hanya role HR yang boleh

$bulan = isset($_GET['bulan']) && $_GET['bulan'] !== '' ? (int)$_GET['bulan'] : null;
$tahun = isset($_GET['tahun']) && $_GET['tahun'] !== '' ? (int)$_GET['tahun'] : null;
$where = '';
$params = array();
if ($bulan !== null || $tahun !== null) {
    if ($bulan === null || $tahun === null || $bulan < 1 || $bulan > 12 || $tahun < 2000 || $tahun > 2100) {
        Json::error('Parameter bulan (1-12) dan tahun tidak valid', 422);
    }
    $where = " AND MONTH(FROM_UNIXTIME(l.created_at)) = ? AND YEAR(FROM_UNIXTIME(l.created_at)) = ?";
    $params = array($bulan, $tahun);
}

$pdo = DBConnection::pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Sembunyikan data yang sudah dihapus HR (soft-delete).
$hasDeletedAt = false;
try {
    $col = $pdo->query("SHOW COLUMNS FROM leaves LIKE 'deleted_at'");
    $hasDeletedAt = $col && $col->fetch() !== false;
} catch (PDOException $e) {
    $hasDeletedAt = false;
}
$where = ($hasDeletedAt ? " WHERE l.deleted_at IS NULL" : " WHERE 1=1") . $where;

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
          $where
          ORDER BY l.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

http_response_code(200);
echo json_encode([
    "success" => true,
    "message" => "Laporan cuti seluruh karyawan",
    "data" => $data
]);
exit;
?>
