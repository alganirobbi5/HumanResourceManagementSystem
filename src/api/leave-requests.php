<?php

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../common/Config.php';
require_once __DIR__ . '/../service/LeaveService.php';
require_once __DIR__ . '/../service/LeaveLeaderService.php';
require_once __DIR__ . '/../service/RateLimiter.php';

$user = Auth::requireApi();
$method = $_SERVER['REQUEST_METHOD'];
$service = new LeaveService();
$leaders = new LeaveLeaderService();
$userId = Auth::userId();
$companyId = Auth::companyId();
$isHr = ($user['role'] === 'hr');
$leaderUserId = $isHr ? null : ($leaders->isLeader($userId, $companyId) ? $userId : null);

if ($method === 'GET') {
    $scope = isset($_GET['scope']) ? strtolower(trim((string)$_GET['scope'])) : 'me';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

    if ($scope === 'leader') {
        if (!$leaders->isLeader((int)$user['id'], $companyId)) {
            Json::error(LeaveLeaderService::NOT_LEADER_MESSAGE, 403);
        }
        Json::ok('OK', $service->forLeader((int)$user['id'], $page, 10, $companyId));
    }

    if ($scope === 'pending') {
        if (!$isHr && !$leaderUserId) {
            Json::error('Anda tidak memiliki izin melihat daftar persetujuan cuti', 403);
        }
        Json::ok('OK', $service->pending($page, 10, $leaderUserId, $companyId));
    }

    if ($scope === 'all') {
        if (!$isHr && !$leaderUserId) {
            Json::error('Anda tidak memiliki izin melihat seluruh pengajuan cuti', 403);
        }
        $filters = array();
        if (!empty($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }
        if (!empty($_GET['keyword'])) {
            $filters['keyword'] = trim($_GET['keyword']);
        }
        Json::ok('OK', $service->all($filters, $page, 10, $leaderUserId, $companyId));
    }

    if ($scope === 'detail') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $row = $service->detail($id, $companyId);
        if (!$row) {
            Json::error('Pengajuan cuti tidak ditemukan', 404);
        }
        $isOwner = $row['employee_id'] == $user['employee_id'];
        $isTargetLeader = !empty($row['leader_user_id'])
            && (int)$row['leader_user_id'] === (int)$user['id']
            && $leaders->isLeader((int)$user['id'], $companyId);
        if (!$isOwner && !$isTargetLeader && $user['role'] !== 'hr') {
            Json::error('Anda tidak dapat melihat pengajuan milik karyawan lain', 403);
        }
        Json::ok('OK', array('data' => $row));
    }

    if (!$user['employee_id']) {
        Json::error('Akun ini tidak terhubung ke data karyawan', 400);
    }
    Json::ok('OK', $service->mine($user['employee_id'], $page, 10, $companyId));
}

if ($method === 'POST') {
    $body = Json::readBody();
    if (empty($body['action'])) {
        // Dukung juga input form klasik
        $body = $_POST;
    }
    $action = isset($body['action']) ? $body['action'] : '';

    if ($action === 'create') {
        if (!$user['employee_id']) {
            Json::error('Akun ini tidak terhubung ke data karyawan', 400);
        }
        if (!RateLimiter::allow('leave_create', Config::RATE_LIMIT_LEAVE_SECONDS)) {
            Json::error('Terlalu sering mengirim pengajuan, tunggu beberapa saat', 429);
        }
        $leaderUserId = isset($body['leader_user_id']) ? (int)$body['leader_user_id'] : 0;
        if ($leaderUserId > 0 && $leaderUserId === (int)$user['id']) {
            Json::error('Tidak dapat memilih diri sendiri sebagai Leader persetujuan', 422);
        }
        try {
            $row = $service->createRequest(
                $user['employee_id'],
                isset($body['reason']) ? $body['reason'] : '',
                isset($body['start_date']) ? $body['start_date'] : '',
                isset($body['end_date']) ? $body['end_date'] : '',
                isset($body['replacement_employee_id']) ? (int)$body['replacement_employee_id'] : 0,
                isset($body['leader_user_id']) ? (int)$body['leader_user_id'] : 0,
                $companyId,
                (int)$user['id']
            );
            Json::ok('Pengajuan cuti berhasil disimpan dan menunggu persetujuan', array('data' => $row));
        } catch (InvalidArgumentException $e) {
            Json::error($e->getMessage(), 422);
        } catch (RuntimeException $e) {
            Json::error($e->getMessage(), 409);
        } catch (Exception $e) {
            Json::error('Gagal menyimpan pengajuan cuti', 500);
        }
    }

    if ($action === 'approve' || $action === 'reject') {
        $id = isset($body['id']) ? (int)$body['id'] : 0;
        if (!$id) {
            Json::error('Pengajuan cuti tidak valid', 422);
        }
        try {
            $catatanLeader = isset($body['catatan_leader']) ? trim((string)$body['catatan_leader']) : null;
            if ($action === 'approve') {
                $row = $service->approve($id, (int)$user['id'], $catatanLeader, $companyId);
                Json::ok('Pengajuan cuti disetujui', array('data' => $row));
            } else {
                $row = $service->reject($id, (int)$user['id'], isset($body['rejection_reason']) ? $body['rejection_reason'] : '', $catatanLeader, $companyId);
                Json::ok('Pengajuan cuti ditolak', array('data' => $row));
            }
        } catch (InvalidArgumentException $e) {
            Json::error($e->getMessage(), (int)$e->getCode() === 404 ? 404 : 422);
        } catch (RuntimeException $e) {
            $code = (int)$e->getCode();
            Json::error($e->getMessage(), in_array($code, array(403, 404, 409), true) ? $code : 409);
        } catch (Exception $e) {
            Json::error('Gagal memproses pengajuan cuti', 500);
        }
    }

    Json::error('Aksi tidak dikenal', 400);
}

Json::error('Method tidak diizinkan', 405);

