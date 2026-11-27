<?php

require_once __DIR__ . '/../common/DBConnection.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../common/Config.php';
require_once __DIR__ . '/../service/AttendanceService.php';

$user = Auth::requireApi();
$employee_id = $user['employee_id'];
$companyId = Auth::companyId();

if (!$employee_id) {
    $isHr = (isset($user['role']) && $user['role'] === 'hr');
    $adminId = Auth::adminId();
    if ($isHr && $adminId !== null) {
        require_once __DIR__ . '/../service/AdminAttendanceService.php';
        $service = new AdminAttendanceService();
        $statusAdmin = $service->status($adminId, Config::companyToday(), $companyId);
        echo json_encode([
            "success" => true,
            "message" => "Status absensi Admin HR",
            "data" => [
                "id" => $statusAdmin['id'],
                "sudah_masuk" => (bool)$statusAdmin['sudah_masuk'],
                "jam_masuk" => $statusAdmin['jam_masuk'],
                "jam_pulang" => $statusAdmin['jam_pulang'],
                "clock_in" => $statusAdmin['clock_in'],
                "clock_out" => $statusAdmin['clock_out'],
                "working_seconds" => (int)$statusAdmin['working_seconds'],
                "sudah_pulang" => (bool)$statusAdmin['sudah_pulang'],
                "selfie_in_url" => $statusAdmin['selfie_in_url'],
                "selfie_out_url" => $statusAdmin['selfie_out_url']
            ]
        ]);
        exit;
    }
    Json::error('Akun ini tidak terhubung ke data karyawan', 400);
}

$tanggal = Config::companyToday();

$pdo = DBConnection::pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$selfieColumn = '';
try {
    foreach (array('selfie_path', 'selfie_out_path') as $column) {
        $col = $pdo->query("SHOW COLUMNS FROM attendances LIKE '" . $column . "'");
        if ($col && $col->fetch()) {
            $selfieColumn .= ', ' . $column;
        }
    }
} catch (PDOException $e) {
    $selfieColumn = '';
}

$filterDeleted = '';
try {
    $col = $pdo->query("SHOW COLUMNS FROM attendances LIKE 'deleted_at'");
    if ($col && $col->fetch()) {
        $filterDeleted = ' AND deleted_at IS NULL';
    }
} catch (PDOException $e) {
    $filterDeleted = '';
}

// HANYA ABSEN MILIK USER YANG TERLOGIN (dari session).
// Baris yang sudah dihapus HR (soft delete) tidak boleh terbaca lagi.
$statusParams = array($employee_id, $tanggal);
if ($companyId !== null) {
    $filterDeleted .= ' AND company_id = ?';
    $statusParams[] = (int)$companyId;
}
$stmt = $pdo->prepare("SELECT id, clock_in, clock_out$selfieColumn FROM attendances WHERE employee_id = ? AND date = ?$filterDeleted ORDER BY id DESC LIMIT 1");
$stmt->execute($statusParams);
$absen = $stmt->fetch(PDO::FETCH_ASSOC);

if ($absen) {
    $clockIn = AttendanceService::normalizedTime($absen['clock_in']);
    $clockOut = AttendanceService::normalizedTime($absen['clock_out']);
    echo json_encode([
        "success" => true,
        "data" => [
            "id" => (int)$absen['id'],
            "sudah_masuk" => true,
            "jam_masuk" => $clockIn,
            "jam_pulang" => $clockOut,
            "clock_in" => $clockIn,
            "clock_out" => $clockOut,
            "working_seconds" => AttendanceService::workingSeconds($clockIn, $clockOut, $tanggal),
            "sudah_pulang" => $clockOut !== null,
            "selfie_in_url" => !empty($absen['selfie_path']) ? 'selfie.php?id=' . (int)$absen['id'] . '&type=in' : null,
            "selfie_out_url" => !empty($absen['selfie_out_path']) ? 'selfie.php?id=' . (int)$absen['id'] . '&type=out' : null
        ]
    ]);
} else {
    echo json_encode([
        "success" => true,
        "data" => [
            "id" => null,
            "sudah_masuk" => false,
            "jam_masuk" => null,
            "jam_pulang" => null,
            "clock_in" => null,
            "clock_out" => null,
            "working_seconds" => 0,
            "sudah_pulang" => false,
            "selfie_in_url" => null,
            "selfie_out_url" => null
        ]
    ]);
}
exit;
?>