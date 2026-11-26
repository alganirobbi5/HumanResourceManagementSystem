<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
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

$data = Json::readRequest();
$waktuLokal = isset($data['waktu_lokal']) ? $data['waktu_lokal'] : null;
$latitude = isset($data['latitude']) ? $data['latitude'] : null;
$longitude = isset($data['longitude']) ? $data['longitude'] : null;
$selfie = isset($data['selfie']) ? $data['selfie'] : null;

if (empty($waktuLokal)) {
    Json::error('waktu_lokal wajib diisi', 400);
}
if (!isset($latitude, $longitude)) {
    Json::error('Koordinat GPS wajib diisi', 400);
}

try {
    $service = new AdminAttendanceService();
    $status = $service->checkOut($adminId, $waktuLokal, $latitude, $longitude, $selfie, Auth::companyId());
    Json::ok('Absen pulang Admin HR berhasil disimpan', array('data' => $status));
} catch (InvalidArgumentException $e) {
    Json::error($e->getMessage(), 422);
} catch (RuntimeException $e) {
    Json::error($e->getMessage(), 409);
} catch (Exception $e) {
    Json::error('Gagal menyimpan absen pulang Admin HR', 500);
}
