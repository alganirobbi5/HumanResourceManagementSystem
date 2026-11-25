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
require_once __DIR__ . '/../service/RateLimiter.php';

$user = Auth::requireApiAdmin();
$adminId = Auth::adminId();

if (!RateLimiter::allow('attendance_admin', Config::RATE_LIMIT_ATTENDANCE_SECONDS)) {
    Json::error('Terlalu sering mengirim absensi, tunggu beberapa saat', 429);
}

$data = Json::readRequest();
$latitude = isset($data['latitude']) ? $data['latitude'] : null;
$longitude = isset($data['longitude']) ? $data['longitude'] : null;
$gpsAccuracy = isset($data['gps_accuracy']) ? $data['gps_accuracy'] : null;
$requestId = isset($data['request_id']) ? preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)$data['request_id']) : null;
$selfie = isset($data['selfie']) ? $data['selfie'] : null;

try {
    $service = new AdminAttendanceService();
    $status = $service->checkIn($adminId, $latitude, $longitude, $gpsAccuracy, $requestId, $selfie);
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Absen masuk Admin HR berhasil disimpan',
        'data' => $status,
    ]);
    exit;
} catch (InvalidArgumentException $e) {
    Json::error($e->getMessage(), 422);
} catch (RuntimeException $e) {
    Json::error($e->getMessage(), 409);
} catch (Exception $e) {
    Json::error('Gagal menyimpan absensi Admin HR', 500);
}
