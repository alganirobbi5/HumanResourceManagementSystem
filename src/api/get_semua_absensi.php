<?php

require_once __DIR__ . '/../common/DBConnection.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../service/AttendanceReportService.php';

Auth::requireApiHr();

try {
    $service = new AttendanceReportService(DBConnection::get());
    $data = $service->all(array(
        'company_id' => Auth::companyId(),
        'tanggal' => isset($_GET['date']) ? trim((string)$_GET['date']) : '',
        'start_date' => isset($_GET['start_date']) ? trim((string)$_GET['start_date']) : '',
        'end_date' => isset($_GET['end_date']) ? trim((string)$_GET['end_date']) : '',
        'keyword' => isset($_GET['keyword']) ? trim((string)$_GET['keyword']) : '',
    ));
    Json::ok('Daftar absensi seluruh karyawan dan Admin HR', array(
        'data' => $data,
        'holidays' => $service->holidays()
    ));
} catch (Exception $e) {
    Json::error('Gagal memuat data absensi', 500);
}
