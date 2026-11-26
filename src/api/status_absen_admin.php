<?php

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
require_once __DIR__ . '/../common/Config.php';
require_once __DIR__ . '/../service/AdminAttendanceService.php';

$user = Auth::requireApiAdmin();
$adminId = Auth::adminId();

$tanggal = isset($_GET['date']) ? trim((string)$_GET['date']) : null;

try {
    $service = new AdminAttendanceService();
    Json::ok('OK', array('data' => $service->status($adminId, $tanggal, Auth::companyId())));
} catch (InvalidArgumentException $e) {
    Json::error($e->getMessage(), 422);
} catch (Exception $e) {
    Json::error('Gagal memuat status absensi', 500);
}
