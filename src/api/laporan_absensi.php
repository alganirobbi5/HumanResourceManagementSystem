<?php

require_once __DIR__ . '/../common/DBConnection.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../service/AttendanceReportService.php';

Auth::requireApiHr();

$tanggal = isset($_GET['date']) ? trim((string)$_GET['date']) : '';
if (isset($_GET['tanggal']) && $tanggal === '') {
    $tanggal = trim((string)$_GET['tanggal']);
}
$startDate = isset($_GET['start_date']) ? trim((string)$_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? trim((string)$_GET['end_date']) : '';
$keyword = isset($_GET['keyword']) ? trim((string)$_GET['keyword']) : '';

if ($tanggal !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
    Json::error('Format tanggal tidak valid.', 400);
}
if ($startDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    Json::error('Format rentang tanggal tidak valid.', 400);
}
if ($endDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    Json::error('Format rentang tanggal tidak valid.', 400);
}
if ($startDate !== '' && $endDate !== '' && $startDate > $endDate) {
    Json::error('Tanggal akhir tidak boleh sebelum tanggal mulai.', 400);
}

try {
    $service = new AttendanceReportService(DBConnection::get());
    $data = $service->all(array(
        'company_id' => Auth::companyId(),
        'tanggal' => $tanggal,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'keyword' => $keyword,
    ));
    http_response_code(200);
    echo json_encode(array('status' => 'success', 'data' => $data));
    exit;
} catch (Exception $e) {
    Json::error('Gagal memuat data absensi', 500);
}
