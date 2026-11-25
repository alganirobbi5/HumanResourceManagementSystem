<?php

require_once __DIR__ . '/../common/DBConnection.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../common/Config.php';
require_once __DIR__ . '/../service/LeaveLeaderService.php';

$user = Auth::requireApi();
$leaderService = new LeaveLeaderService();
$leaderUserId = Auth::userId();

if (!$leaderService->isLeader($leaderUserId)) {
    Json::error(LeaveLeaderService::NOT_LEADER_MESSAGE, 403);
}

$data = Json::readRequest();
$leaveId = isset($data['id']) ? (int)$data['id'] : (isset($data['leave_id']) ? (int)$data['leave_id'] : 0);
$statusInput = isset($data['status']) ? strtolower(trim((string)$data['status'])) : '';

if ($leaveId <= 0) {
    Json::error('id pengajuan wajib diisi', 400);
}
$decision = null;
if ($statusInput === 'approved' || $statusInput === '2') {
    $decision = 'approved';
} elseif ($statusInput === 'rejected' || $statusInput === '3') {
    $decision = 'rejected';
}
if ($decision === null) {
    Json::error('Status harus approved atau rejected', 422);
}
$catatan = isset($data['catatan_leader']) ? trim((string)$data['catatan_leader'])
    : (isset($data['comment']) ? trim((string)$data['comment']) : null);

try {
    $pdo = DBConnection::get();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare('SELECT id, status, leader_user_id FROM leaves WHERE id = ? LIMIT 1 FOR UPDATE');
    $stmt->execute([$leaveId]);
    $leave = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$leave) {
        Json::error('Pengajuan cuti tidak ditemukan', 404);
    }
    if (in_array((string)$leave['status'], array('2', '3', 'approved', 'rejected'), true)) {
        Json::error('Pengajuan sudah diproses sebelumnya', 409);
    }
    if (!empty($leave['leader_user_id']) && (int)$leave['leader_user_id'] !== (int)$leaderUserId) {
        Json::error('Pengajuan cuti ini ditujukan kepada Leader lain', 403);
    }

    $now = Config::companyNow()->format('Y-m-d H:i:s');
    $leaderName = $leaderService->leaderName($leaderUserId);
    $set = array('status = ?', 'updated_at = NOW()');
    $params = array($decision === 'approved' ? 2 : 3);
    foreach (array(
        'approved_by_user_id' => (int)$leaderUserId,
        'approved_by_name' => $leaderName,
        'catatan_leader' => $catatan,
        'decided_at' => $now,
    ) as $column => $value) {
        $col = $pdo->query("SHOW COLUMNS FROM leaves LIKE '" . $column . "'");
        if (!$col || !$col->fetch()) {
            continue;
        }
        $set[] = $column . ' = ?';
        $params[] = $value;
    }
    $params[] = $leaveId;
    $pdo->prepare('UPDATE leaves SET ' . implode(', ', $set) . ' WHERE id = ?')->execute($params);

    Json::ok('Status cuti berhasil diperbarui', array('data' => array(
        'leave_id' => $leaveId,
        'status_baru' => $decision,
        'approved_by_user_id' => (int)$leaderUserId,
        'approved_by_name' => $leaderName,
        'catatan_leader' => $catatan,
    )));
} catch (PDOException $e) {
    Json::error('Terjadi kesalahan server', 500);
}
