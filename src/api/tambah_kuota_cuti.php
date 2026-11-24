<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../common/Config.php';
require_once __DIR__ . '/../service/LeaveService.php';

$user = Auth::requireApiHr();

$data = json_decode(file_get_contents("php://input"));
$employeeId = isset($data->employee_id) ? (int)$data->employee_id : 0;
$days = isset($data->days) ? (float)$data->days : 0;
$year = isset($data->entitlement_year) && (int)$data->entitlement_year > 0
    ? (int)$data->entitlement_year
    : (int)Config::companyNow()->format('Y');

if ($employeeId <= 0 || $days <= 0) {
    Json::error('Karyawan dan jumlah hari wajib diisi', 400);
}

try {
    $service = new LeaveService();
    $balance = $service->addEntitlement($employeeId, $days, $year);

    Json::ok('Kuota cuti berhasil ditambahkan', array(
        'data' => array(
            'employee_id' => (int)$balance['employee_id'],
            'entitlement_year' => (int)$balance['entitlement_year'],
            'entitled_days' => (float)$balance['entitled_days'],
            'used_days' => (float)$balance['used_days'],
            'remaining_days' => (float)$balance['remaining_days'],
        ),
    ));
} catch (InvalidArgumentException $e) {
    Json::error($e->getMessage(), 422);
} catch (Exception $e) {
    Json::error('Gagal menambahkan kuota cuti', 500);
}
