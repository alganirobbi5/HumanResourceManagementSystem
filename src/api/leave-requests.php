<?php

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../common/Config.php';
require_once __DIR__ . '/../service/LeaveService.php';
require_once __DIR__ . '/../service/RateLimiter.php';

$user = Auth::requireApi();
$method = $_SERVER['REQUEST_METHOD'];
$service = new LeaveService();

if ($method === 'GET') {
    $scope = isset($_GET['scope']) ? $_GET['scope'] : 'me';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

    if ($scope === 'pending') {
        if ($user['role'] !== 'hr') {
            Json::error('Anda tidak memiliki izin melihat daftar persetujuan cuti', 403);
        }
        Json::ok('OK', $service->pending($page));
    }

    if ($scope === 'all') {
        if ($user['role'] !== 'hr') {
            Json::error('Anda tidak memiliki izin melihat seluruh pengajuan cuti', 403);
        }
        $filters = array();
        if (!empty($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }
        if (!empty($_GET['keyword'])) {
            $filters['keyword'] = trim($_GET['keyword']);
        }
        Json::ok('OK', $service->all($filters, $page));
    }

    if ($scope === 'detail') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $row = $service->detail($id);
        if (!$row) {
            Json::error('Pengajuan cuti tidak ditemukan', 404);
        }
        $isOwner = $row['employee_id'] == $user['employee_id'];
        if (!$isOwner && $user['role'] !== 'hr') {
            Json::error('Anda tidak dapat melihat pengajuan milik karyawan lain', 403);
        }
        Json::ok('OK', array('data' => $row));
    }

    if (!$user['employee_id']) {
        Json::error('Akun ini tidak terhubung ke data karyawan', 400);
    }
    Json::ok('OK', $service->mine($user['employee_id'], $page));
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
        try {
            $row = $service->createRequest(
                $user['employee_id'],
                isset($body['reason']) ? $body['reason'] : '',
                isset($body['start_date']) ? $body['start_date'] : '',
                isset($body['end_date']) ? $body['end_date'] : '',
                isset($body['replacement_employee_id']) ? (int)$body['replacement_employee_id'] : 0
            );
            Json::ok('Pengajuan cuti berhasil disimpan dan menunggu persetujuan', array('data' => $row));
        } catch (InvalidArgumentException $e) {
            Json::error($e->getMessage(), 422);
        } catch (RuntimeException $e) {
            Json::error($e->getMessage(), 409);
        } catch (Exception $e) {
            Json::error($e->getMessage(), 500);
        }
    }

    if ($action === 'approve' || $action === 'reject') {
        if ($user['role'] !== 'hr') {
            Json::error('Anda tidak memiliki izin memproses pengajuan cuti', 403);
        }
        $id = isset($body['id']) ? (int)$body['id'] : 0;
        if (!$id) {
            Json::error('Pengajuan cuti tidak valid', 422);
        }
        try {
            if ($action === 'approve') {
                $row = $service->approve($id, (int)$user['id']);
                Json::ok('Pengajuan cuti disetujui', array('data' => $row));
            } else {
                $row = $service->reject($id, (int)$user['id'], isset($body['rejection_reason']) ? $body['rejection_reason'] : '');
                Json::ok('Pengajuan cuti ditolak', array('data' => $row));
            }
        } catch (InvalidArgumentException $e) {
            Json::error($e->getMessage(), 422);
        } catch (RuntimeException $e) {
            Json::error($e->getMessage(), 409);
        } catch (Exception $e) {
            Json::error($e->getMessage(), 500);
        }
    }

    Json::error('Aksi tidak dikenal', 400);
}

Json::error('Method tidak diizinkan', 405);
