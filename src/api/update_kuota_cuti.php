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
require_once __DIR__ . '/../common/DBConnection.php';
require_once __DIR__ . '/../service/LeaveQuotaService.php';

Auth::requireApiHr();

$data = Json::readRequest();
$ids = array();
if (!empty($data['employee_ids']) && is_array($data['employee_ids'])) {
    foreach ($data['employee_ids'] as $id) {
        $ids[] = (int)$id;
    }
} elseif (!empty($data['employee_id'])) {
    $ids[] = (int)$data['employee_id'];
}
$days = isset($data['tambahan_hari']) ? $data['tambahan_hari'] : (isset($data['days']) ? $data['days'] : 0);
$mode = isset($data['mode']) ? strtolower(trim((string)$data['mode'])) : 'tambah';
if ($mode !== 'kurang') {
    $mode = 'tambah';
}

if (!$ids || (float)$days <= 0) {
    Json::error('Karyawan dan jumlah hari wajib diisi', 400);
}

try {
    $service = new LeaveQuotaService(DBConnection::get());
    foreach ($ids as $id) {
        if ($mode === 'kurang') {
            $service->deductDays($id, $days);
        } else {
            $service->addDays($id, $days);
        }
    }
    Json::ok($mode === 'kurang'
        ? 'Kuota cuti berhasil dikurangi'
        : 'Kuota cuti berhasil ditambahkan',
        array('updated' => count($ids), 'mode' => $mode));
} catch (InvalidArgumentException $e) {
    Json::error('Karyawan dan jumlah hari tidak valid', 422);
} catch (RuntimeException $e) {
    Json::error('Skema kuota cuti tidak tersedia', 500);
} catch (Exception $e) {
    Json::error('Gagal menyimpan kuota cuti', 500);
}
