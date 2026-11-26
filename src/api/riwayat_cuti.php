<?php

require_once __DIR__ . '/../common/DBConnection.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../service/LeaveService.php';
require_once __DIR__ . '/../service/LeaveLeaderService.php';

$user = Auth::requireApi();
$employeeId = $user['employee_id'];
$isHr = (isset($user['role']) && $user['role'] === 'hr');
$scope = isset($_GET['scope']) ? strtolower(trim((string)$_GET['scope'])) : 'me';
$leaders = new LeaveLeaderService();
$service = new LeaveService();

if ($scope === 'leader') {
    if (!$leaders->isLeader((int)$user['id'])) {
        Json::error(LeaveLeaderService::NOT_LEADER_MESSAGE, 403);
    }
    Json::ok('Pengajuan cuti yang ditujukan kepada Anda', array(
        'data' => $service->listLeaves('leader', (int)$user['id'])
    ));
}

if ($scope === 'all') {
    if (!$isHr) {
        Json::error('Anda tidak memiliki izin melihat seluruh pengajuan cuti', 403);
    }
    Json::ok('Riwayat cuti seluruh karyawan', array('data' => $service->listLeaves('all')));
}

if ($employeeId !== null) {
    Json::ok('Riwayat cuti berhasil diambil', array(
        'data' => $service->listLeaves('mine', (int)$employeeId)
    ));
}

if ($leaders->isLeader((int)$user['id'])) {
    Json::ok('Pengajuan cuti yang ditujukan kepada Anda', array(
        'data' => $service->listLeaves('leader', (int)$user['id'])
    ));
}

if ($isHr) {
    Json::ok('Riwayat cuti seluruh karyawan', array('data' => $service->listLeaves('all')));
}

Json::error('Akun ini tidak terhubung ke data karyawan', 400);
