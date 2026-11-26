<?php

require_once __DIR__ . '/../common/DBConnection.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../service/LeaveLeaderService.php';

$user = Auth::requireApi();
$employee_id = $user['employee_id'];
$userId = Auth::userId();
$isHr = (isset($user['role']) && $user['role'] === 'hr');
$lihatSemua = ($employee_id === null && $isHr);
$leaders = new LeaveLeaderService();
$isLeader = !$isHr && $leaders->isLeader($userId);

if ($employee_id === null && !$lihatSemua && !$isLeader) {
    Json::error('Akun ini tidak terhubung ke data karyawan', 400);
}

$pdo = DBConnection::pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function riwayatHasColumn($pdo, $column)
{
    $col = $pdo->query("SHOW COLUMNS FROM leaves LIKE '" . $column . "'");
    return $col && $col->fetch() !== false;
}

$fields = "l.id, l.employee_id, l.type, l.leave_from, l.leave_to, l.status,
           FROM_UNIXTIME(l.created_at) AS created_at";
foreach (array('reason', 'comment', 'catatan_leader', 'approved_by_name', 'decided_at') as $column) {
    if (riwayatHasColumn($pdo, $column)) {
        $fields .= ', l.' . $column;
    }
}
$leaderJoin = '';
foreach (array('leader_user_id', 'approved_by_user_id') as $column) {
    if (riwayatHasColumn($pdo, $column)) {
        $fields .= ', l.' . $column;
    }
}
if (riwayatHasColumn($pdo, 'leader_user_id')) {
    $fields .= ', CONCAT_WS(" ", le.first_name, le.last_name) AS target_leader_name';
    $leaderJoin = ' LEFT JOIN users lu ON lu.id = l.leader_user_id
                    LEFT JOIN employees le ON le.id = lu.employee_id';
}

$filter = riwayatHasColumn($pdo, 'deleted_at') ? ' WHERE l.deleted_at IS NULL' : '';

if ($lihatSemua) {
    $stmt = $pdo->query("SELECT $fields FROM leaves l $leaderJoin $filter ORDER BY l.id DESC");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $message = 'Riwayat cuti seluruh karyawan';
} elseif ($isLeader && riwayatHasColumn($pdo, 'leader_user_id')) {
    // Leader melihat cuti miliknya sendiri DAN pengajuan yang ditujukan kepadanya.
    $own = $employee_id !== null ? 'l.employee_id = ?' : '0';
    $condition = '(' . $own . ' OR l.leader_user_id = ?)';
    $filter .= $filter !== '' ? ' AND ' . $condition : ' WHERE ' . $condition;
    $params = array();
    if ($employee_id !== null) {
        $params[] = $employee_id;
    }
    $params[] = $userId;
    $stmt = $pdo->prepare("SELECT $fields FROM leaves l $leaderJoin $filter ORDER BY l.id DESC");
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $message = 'Riwayat cuti berhasil diambil';
} else {
    $filter .= $filter !== '' ? ' AND l.employee_id = ?' : ' WHERE l.employee_id = ?';
    $stmt = $pdo->prepare("SELECT $fields FROM leaves l $leaderJoin $filter ORDER BY l.id DESC");
    $stmt->execute([$employee_id]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $message = 'Riwayat cuti berhasil diambil';
}

Json::ok($message, array('data' => $data));
