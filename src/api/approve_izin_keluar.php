<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
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
$leaderUserId = Auth::userId();
$leaders = new LeaveLeaderService();
if (!$leaders->isLeader($leaderUserId)) {
    Json::error(LeaveLeaderService::NOT_LEADER_MESSAGE, 403);
}

$data = Json::readRequest();
$id = isset($data['id']) ? (int)$data['id'] : 0;
$status = isset($data['status']) ? trim((string)$data['status']) : '';
$catatan = isset($data['catatan_leader']) ? trim((string)$data['catatan_leader'])
    : (isset($data['catatan_hr']) ? trim((string)$data['catatan_hr']) : null);

if ($id <= 0) {
    Json::error('id pengajuan wajib diisi', 400);
}
if (!in_array(strtolower($status), array('approved', 'rejected'), true)) {
    Json::error('Status harus approved atau rejected', 422);
}

try {
    $service = new IzinKeluarService();
    $row = $service->decide($id, $status, $leaderUserId, $catatan);
    Json::ok('Pengajuan izin keluar berhasil diproses', array('data' => $row));
} catch (InvalidArgumentException $e) {
    Json::error($e->getMessage(), (int)$e->getCode() === 404 ? 404 : 422);
} catch (RuntimeException $e) {
    $code = (int)$e->getCode();
    Json::error($e->getMessage(), in_array($code, array(403, 404, 409), true) ? $code : 409);
} catch (Exception $e) {
    Json::error('Gagal memproses pengajuan izin keluar', 500);
}
