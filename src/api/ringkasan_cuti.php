<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../common/DBConnection.php';
require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';

$user = Auth::requireApiHr();

$bulan = isset($_GET['bulan']) && $_GET['bulan'] !== ''
    ? (int) $_GET['bulan']
    : (int) date('m');
$tahun = isset($_GET['tahun']) && $_GET['tahun'] !== ''
    ? (int) $_GET['tahun']
    : (int) date('Y');

if ($bulan < 1 || $bulan > 12 || $tahun < 2000 || $tahun > 2100) {
    Json::error('Parameter bulan (1-12) dan tahun tidak valid', 422);
}

try {
    $pdo = DBConnection::pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Kolom status di skema lama bernama `staus` (typo); produksi mungkin
    // sudah memakai `status`. Deteksi kolom yang tersedia agar query tidak
    // gagal dengan "unknown column".
    $statusColumn = 'staus';
    try {
        $col = $pdo->query("SHOW COLUMNS FROM leaves LIKE 'status'");
        if ($col && $col->fetch()) {
            $statusColumn = 'status';
        }
    } catch (PDOException $e) {
        $statusColumn = 'staus';
    }

    $deletedFilter = '';
    try {
        $col = $pdo->query("SHOW COLUMNS FROM leaves LIKE 'deleted_at'");
        if ($col && $col->fetch()) {
            $deletedFilter = " AND deleted_at IS NULL";
        }
    } catch (PDOException $e) {
        $deletedFilter = '';
    }

    $stmt = $pdo->prepare(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN $statusColumn = 1 THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN $statusColumn = 2 THEN 1 ELSE 0 END) AS approved,
            SUM(CASE WHEN $statusColumn = 3 THEN 1 ELSE 0 END) AS rejected
         FROM leaves
         WHERE MONTH(FROM_UNIXTIME(created_at)) = ?
           AND YEAR(FROM_UNIXTIME(created_at)) = ?
           $deletedFilter"
    );
    $stmt->execute(array($bulan, $tahun));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $labelBulan = date('F', mktime(0, 0, 0, $bulan, 1, $tahun)) . ' ' . $tahun;

    http_response_code(200);
    echo json_encode(array(
        'success' => true,
        'status' => 'success',
        'message' => 'Ringkasan cuti bulanan',
        'data' => array(
            'bulan' => $labelBulan,
            'bulan_angka' => $bulan,
            'tahun' => $tahun,
            'total' => (int) $row['total'],
            'pending' => (int) $row['pending'],
            'approved' => (int) $row['approved'],
            'rejected' => (int) $row['rejected'],
        ),
    ));
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'status' => 'error',
        'message' => 'Gagal memuat ringkasan cuti',
    ));
}
