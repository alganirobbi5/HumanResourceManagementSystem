<?php

require_once __DIR__ . '/../common/DBConnection.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../common/Config.php';
require_once __DIR__ . '/../service/AttendanceService.php';

$user = Auth::requireApi();
$employee_id = $user['employee_id'];

if (!$employee_id) {
    Json::error('Akun ini tidak terhubung ke data karyawan', 400);
}

$data = Json::readRequest();

if (!isset($data['latitude']) || trim((string)$data['latitude']) === '' ||
    !isset($data['longitude']) || trim((string)$data['longitude']) === '') {
    Json::error('latitude dan longitude wajib diisi', 400);
}

if (!is_numeric($data['latitude']) || !is_numeric($data['longitude'])) {
    Json::error('Koordinat GPS tidak valid', 422);
}

$lat = (float)$data['latitude'];
$lng = (float)$data['longitude'];

if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    Json::error('Koordinat GPS berada di luar rentang yang valid', 422);
}

$tanggal = Config::companyToday();
$waktu_sekarang = Config::companyNow()->format('H:i:s');

$pdo = DBConnection::pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $pdo->prepare("SELECT id, clock_in, clock_out FROM attendances WHERE employee_id = ? AND date = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$employee_id, $tanggal]);
$absen = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$absen) {
    Json::error("Anda belum absen masuk hari ini untuk tanggal $tanggal.", 400);
}

if ($absen['clock_out'] && $absen['clock_out'] !== '00:00:00') {
    Json::error('Anda sudah absen pulang hari ini.', 400);
}

$clock_in = new DateTime($absen['clock_in']);
$clock_out = new DateTime($waktu_sekarang);
$diff = $clock_in->diff($clock_out);
$total_jam = $diff->h + ($diff->i / 60);

$selfie = AttendanceService::storeSelfie(
    isset($data['selfie']) ? $data['selfie'] : null,
    $tanggal,
    $employee_id,
    'selfie_out'
);

$updates = array('clock_out = ?', 'total_work = ?');
$values = array($waktu_sekarang, $total_jam);
if ($selfie) {
    foreach (array('selfie_out_path' => $selfie['path'], 'selfie_out_mime_type' => $selfie['mime_type']) as $column => $value) {
        $check = $pdo->query("SHOW COLUMNS FROM attendances LIKE '" . $column . "'");
        if ($check && $check->fetch()) {
            $updates[] = $column . ' = ?';
            $values[] = $value;
        }
    }
}
$values[] = $absen['id'];
$update = $pdo->prepare("UPDATE attendances SET " . implode(', ', $updates) . " WHERE id = ?");
$update->execute($values);

Json::out(array(
    'success' => true,
    'message' => 'Absen pulang berhasil',
    'data' => array(
        'total_jam_kerja' => round($total_jam, 2) . ' jam',
        'latitude' => $lat,
        'longitude' => $lng,
        'selfie_out_url' => $selfie ? 'selfie.php?id=' . (int)$absen['id'] . '&type=out' : null,
    ),
));
