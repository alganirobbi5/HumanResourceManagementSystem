<?php

require_once __DIR__ . '/../common/DBConnection.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';

$user = Auth::requireApi();
$employee_id = $user['employee_id'];

if (!$employee_id) {
    Json::error('Akun ini tidak terhubung ke data karyawan', 400);
}

$tanggal = date('Y-m-d');

$pdo = DBConnection::pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// HANYA ABSEN MILIK USER YANG TERLOGIN (dari session)
// Lewatkan parameter employee_id dari GET untuk keamanan
$stmt = $pdo->prepare("SELECT clock_in, clock_out FROM attendances WHERE employee_id = ? AND date = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$employee_id, $tanggal]);
$absen = $stmt->fetch(PDO::FETCH_ASSOC);

if ($absen) {
    echo json_encode([
        "success" => true,
        "data" => [
            "sudah_masuk" => true,
            "jam_masuk" => $absen['clock_in'],
            "jam_pulang" => ($absen['clock_out'] == '00:00:00' || $absen['clock_out'] == null) ? null : $absen['clock_out'],
            "sudah_pulang" => ($absen['clock_out'] != '00:00:00' && $absen['clock_out'] != null)
        ]
    ]);
} else {
    echo json_encode([
        "success" => true,
        "data" => [
            "sudah_masuk" => false,
            "jam_masuk" => null,
            "jam_pulang" => null,
            "sudah_pulang" => false
        ]
    ]);
}
exit;
?>