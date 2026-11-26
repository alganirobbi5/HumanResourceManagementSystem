<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../common/DBConnection.php';
require_once __DIR__ . '/../service/LeaveLeaderService.php';
require_once __DIR__ . '/../service/LeaveService.php';

$user = Auth::requireApi();
if (!$user['employee_id']) {
    Json::error('Akun ini tidak terhubung ke data karyawan', 400);
}

$data = Json::readRequest();
$leaderUserId = isset($data['leader_user_id']) ? (int)$data['leader_user_id'] : 0;
$leaderService = new LeaveLeaderService();
if ($leaderUserId <= 0 || !$leaderService->isLeader($leaderUserId)) {
    Json::error('Leader persetujuan cuti tidak valid', 422);
}

$startDate = isset($data['start_date']) ? trim((string)$data['start_date']) : '';
$endDate = isset($data['end_date']) ? trim((string)$data['end_date']) : '';
$reason = isset($data['reason']) ? trim((string)$data['reason']) : '';
if ($startDate === '' || $endDate === '' || $reason === '') {
    Json::error('Data cuti tidak lengkap', 400);
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    Json::error('Format tanggal tidak valid', 422);
}

try {
    $pdo = DBConnection::get();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $judul = isset($data['title']) && $data['title'] !== '' ? (string)$data['title'] : $reason;
    $leaveService = new LeaveService();
    $totalLeave = $leaveService->calculateDays($startDate, $endDate);

    $columns = array('employee_id', 'type', 'leave_from', 'leave_to', 'total_leave', 'status', 'leader_user_id', 'created_at');
    $values = array((int)$user['employee_id'], $judul, $startDate, $endDate, $totalLeave, 1, $leaderUserId, time());
    $optional = array('reason' => $reason);
    if (!empty($data['replacement_employee_id'])) {
        $optional['replacement_employee_id'] = (int)$data['replacement_employee_id'];
    }
    foreach ($optional as $column => $value) {
        $col = $pdo->query("SHOW COLUMNS FROM leaves LIKE '" . $column . "'");
        if ($col && $col->fetch()) {
            $columns[] = $column;
            $values[] = $value;
        }
    }
    $stmt = $pdo->prepare('INSERT INTO leaves (' . implode(', ', $columns) . ') VALUES (' .
        implode(', ', array_fill(0, count($values), '?')) . ')');
    $stmt->execute($values);

    echo json_encode(array(
        'success' => true,
        'status' => 'success',
        'message' => 'Pengajuan cuti berhasil dikirim',
        'data' => array(
            'id' => (int)$pdo->lastInsertId(),
            'leader_user_id' => $leaderUserId,
            'total_leave' => $totalLeave,
        ),
    ));
    exit;
} catch (InvalidArgumentException $e) {
    Json::error($e->getMessage(), 422);
} catch (PDOException $e) {
    Json::error('Gagal menyimpan pengajuan cuti', 500);
}
