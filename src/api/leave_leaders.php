<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../service/LeaveLeaderService.php';

$user = Auth::requireApi();
$service = new LeaveLeaderService();
$method = $_SERVER['REQUEST_METHOD'];
$companyId = Auth::companyId();

if ($method === 'GET') {
    $data = array('leaders' => $service->listLeaders($companyId));
    if (!empty($_GET['eligible']) && (string)$_GET['eligible'] === '1' && Auth::isHr()) {
        $data['eligible_users'] = $service->listEligibleUsers($companyId);
    }
    Json::ok('OK', $data);
}

if ($method === 'POST' || $method === 'DELETE') {
    $hr = Auth::requireApiHr();
    $body = Json::readRequest();
    $action = isset($body['action']) ? strtolower((string)$body['action']) : '';
    if ($method === 'DELETE') {
        $action = 'delete';
    }

    try {
        if ($action === 'delete') {
            $leaderId = isset($body['id']) ? $body['id'] : (isset($_GET['id']) ? $_GET['id'] : null);
            $userId = isset($body['user_id']) ? $body['user_id'] : (isset($_GET['user_id']) ? $_GET['user_id'] : null);
            $byUserId = ($leaderId === null || $leaderId === '');
            $target = $byUserId ? $userId : $leaderId;
            if (!$target) {
                Json::error('id atau user_id wajib diisi', 400);
            }
            $service->removeLeader($target, $companyId, $byUserId);
            Json::ok('Leader berhasil dihapus');
        }

        $targetUserId = isset($body['user_id']) ? (int)$body['user_id'] : 0;
        if ($targetUserId <= 0) {
            Json::error('user_id wajib diisi', 400);
        }
        $keterangan = isset($body['keterangan']) ? trim((string)$body['keterangan']) : null;
        $jabatan = isset($body['jabatan']) ? trim((string)$body['jabatan']) : '';
        if ($jabatan === '') {
            Json::error('jabatan wajib diisi', 422);
        }
        Json::ok('Leader berhasil ditambahkan', array(
            'data' => $service->addLeader($targetUserId, (int)$hr['id'], $keterangan, $jabatan, $companyId)
        ));
    } catch (InvalidArgumentException $e) {
        Json::error($e->getMessage(), (int)$e->getCode() === 404 ? 404 : 422);
    } catch (RuntimeException $e) {
        Json::error($e->getMessage(), (int)$e->getCode() === 409 ? 409 : 500);
    } catch (Exception $e) {
        Json::error('Gagal mengelola data Leader', 500);
    }
}

Json::error('Method tidak diizinkan', 405);
