<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Authorization");

if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../service/IzinKeluarService.php';
require_once __DIR__ . '/../service/LeaveLeaderService.php';

$user = Auth::requireApi();
$userId = Auth::userId();
$service = new IzinKeluarService();
$leaders = new LeaveLeaderService();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $scope = isset($_GET['scope']) ? strtolower(trim((string)$_GET['scope'])) : 'me';
    $status = isset($_GET['status']) ? trim((string)$_GET['status']) : null;
    $tanggal = isset($_GET['tanggal']) ? trim((string)$_GET['tanggal']) : null;
    $isHr = Auth::isHr();
    $isLeader = $leaders->isLeader($userId);

    if ($scope === 'all') {
        if ($isHr) {
            Json::ok('OK', array('data' => $service->listAll($status, $tanggal)));
        }
        if ($isLeader) {
            Json::ok('OK', array('data' => $service->listForLeader($userId, $status, $tanggal)));
        }
        Json::error('Anda tidak memiliki izin melihat seluruh pengajuan izin keluar', 403);
    }

    if ($isLeader) {
        Json::ok('OK', array('data' => $service->listForLeader($userId, $status, $tanggal)));
    }
    if ($user['employee_id']) {
        Json::ok('OK', array('data' => $service->listByEmployee((int)$user['employee_id'], $status)));
    }
    if ($isHr) {
        Json::ok('OK', array('data' => $service->listAll($status, $tanggal)));
    }
    Json::error('Akun ini tidak terhubung ke data karyawan', 400);
}

if ($method === 'POST') {
    if (!$user['employee_id']) {
        Json::error('Akun ini tidak terhubung ke data karyawan', 400);
    }
    $data = Json::readRequest();
    try {
        $row = $service->createRequest((int)$user['employee_id'], $data);
        Json::ok('Pengajuan izin keluar berhasil disimpan dan menunggu persetujuan', array('data' => $row));
    } catch (InvalidArgumentException $e) {
        Json::error($e->getMessage(), (int)$e->getCode() === 404 ? 404 : 422);
    } catch (RuntimeException $e) {
        Json::error($e->getMessage(), 409);
    } catch (Exception $e) {
        Json::error('Gagal menyimpan pengajuan izin keluar', 500);
    }
}

Json::error('Method tidak diizinkan', 405);
