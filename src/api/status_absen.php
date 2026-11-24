<?php

require_once __DIR__ . '/../common/DBConnection.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../common/Config.php';

$user = Auth::requireApi();
$employee_id = $user['employee_id'];

if (!$employee_id) {
    $isHr = (isset($user['role']) && $user['role'] === 'hr');
    $adminId = Auth::adminId();
    if ($isHr && $adminId !== null) {
        require_once __DIR__ . '/../service/AdminAttendanceService.php';
        $service = new AdminAttendanceService();
        $statusAdmin = $service->status($adminId, Config::companyToday());
        echo json_encode([
            "success" => true,
            "message" => "Status absensi Admin HR",
            "data" => [
                "sudah_masuk" => (bool)$statusAdmin['sudah_masuk'],
                "jam_masuk" => $statusAdmin['jam_masuk'],
                "jam_pulang" => $statusAdmin['jam_pulang'],
                "sudah_pulang" => (bool)$statusAdmin['sudah_pulang'],
                "selfie_in_url" => null,
                "selfie_out_url" => null
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

// HANYA ABSEN MILIK USER YANG TERLOGIN (dari session)
// Lewatkan parameter employee_id dari GET untuk keamanan
$stmt = $pdo->prepare("SELECT id, clock_in, clock_out$selfieColumn FROM attendances WHERE employee_id = ? AND date = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$employee_id, $tanggal]);
$absen = $stmt->fetch(PDO::FETCH_ASSOC);

if ($absen) {
    echo json_encode([
        "success" => true,
        "data" => [
            "sudah_masuk" => true,
            "jam_masuk" => $absen['clock_in'],
            "jam_pulang" => ($absen['clock_out'] == '00:00:00' || $absen['clock_out'] == null) ? null : $absen['clock_out'],
            "sudah_pulang" => ($absen['clock_out'] != '00:00:00' && $absen['clock_out'] != null),
            "selfie_in_url" => !empty($absen['selfie_path']) ? 'selfie.php?id=' . (int)$absen['id'] . '&type=in' : null,
            "selfie_out_url" => !empty($absen['selfie_out_path']) ? 'selfie.php?id=' . (int)$absen['id'] . '&type=out' : null
        ]
    ]);
} else {
    echo json_encode([
        "success" => true,
        "data" => [
            "sudah_masuk" => false,
            "jam_masuk" => null,
            "jam_pulang" => null,
            "sudah_pulang" => false,
            "selfie_in_url" => null,
            "selfie_out_url" => null
        ]
    ]);
}
exit;
?>