<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../common/DBConnection.php';
require_once __DIR__ . '/../service/LeaveQuotaService.php';

Auth::requireApiHr();

try {
    $service = new LeaveQuotaService(DBConnection::get());
    Json::ok('OK', array('data' => $service->employeesWithQuota()));
} catch (Exception $e) {
    Json::error('Gagal memuat daftar kuota cuti karyawan', 500);
}
