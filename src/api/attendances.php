<?php

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../common/Config.php';
require_once __DIR__ . '/../service/AttendanceService.php';
require_once __DIR__ . '/../service/RateLimiter.php';

$user = Auth::requireApi();
$method = $_SERVER['REQUEST_METHOD'];
$service = new AttendanceService();

if ($method === 'GET') {
    $scope = isset($_GET['scope']) ? $_GET['scope'] : 'me';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

    if ($scope === 'all') {
        if ($user['role'] !== 'hr') {
            Json::error('Anda tidak memiliki izin melihat data absensi seluruh karyawan', 403);
        }
        $filters = array();
        if (!empty($_GET['date'])) {
            $filters['date'] = $_GET['date'];
        }
        if (!empty($_GET['start_date'])) {
            $filters['start_date'] = $_GET['start_date'];
        }
        if (!empty($_GET['end_date'])) {
            $filters['end_date'] = $_GET['end_date'];
        }
        if (!empty($_GET['keyword'])) {
            $filters['keyword'] = trim($_GET['keyword']);
        }
        Json::ok('OK', $service->all($filters, $page));
    }

    if (!$user['employee_id']) {
        Json::error('Akun ini tidak terhubung ke data karyawan', 400);
    }
    Json::ok('OK', $service->mine($user['employee_id'], $page));
}

if ($method === 'POST') {
    if (!$user['employee_id']) {
        Json::error('Akun ini tidak terhubung ke data karyawan', 400);
    }
    if (!RateLimiter::allow('attendance', Config::RATE_LIMIT_ATTENDANCE_SECONDS)) {
        Json::error('Terlalu sering mengirim absensi, tunggu beberapa saat', 429);
    }

    $latitude = isset($_POST['latitude']) ? $_POST['latitude'] : null;
    $longitude = isset($_POST['longitude']) ? $_POST['longitude'] : null;
    $gpsAccuracy = isset($_POST['gps_accuracy']) ? $_POST['gps_accuracy'] : null;
    $requestId = isset($_POST['request_id']) ? preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)$_POST['request_id']) : null;
    $file = isset($_FILES['selfie']) ? $_FILES['selfie'] : array('error' => UPLOAD_ERR_NO_FILE);

    try {
        $attendance = $service->create($user['employee_id'], $latitude, $longitude, $gpsAccuracy, $file, $requestId);
        Json::ok('Absensi berhasil disimpan', array('data' => $attendance));
    } catch (InvalidArgumentException $e) {
        Json::error($e->getMessage(), 422);
    } catch (Exception $e) {
        Json::error($e->getMessage(), 500);
    }
}

Json::error('Method tidak diizinkan', 405);
