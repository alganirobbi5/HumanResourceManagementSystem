<?php

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../service/LeaveService.php';

$user = Auth::requireApi();

if (!$user['employee_id']) {
    Json::error('Akun ini tidak terhubung ke data karyawan', 400);
}

$service = new LeaveService();
$balance = $service->getBalance($user['employee_id']);

Json::ok('OK', array(
    'data' => array(
        'employee_id' => (int)$balance['employee_id'],
        'leave_type' => $balance['leave_type'],
        'entitlement_year' => (int)$balance['entitlement_year'],
        'entitled_days' => (float)$balance['entitled_days'],
        'used_days' => (float)$balance['used_days'],
        'remaining_days' => (float)$balance['remaining_days'],
    )
));
