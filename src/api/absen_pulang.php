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

$data = Json::readRequest();

$isHrAdmin = !$employee_id && (isset($user['role']) && $user['role'] === 'hr') && Auth::adminId() !== null;
if ($isHrAdmin) {
    require_once __DIR__ . '/../service/AdminAttendanceService.php';
    $waktuLokal = isset($data['waktu_lokal']) && $data['waktu_lokal'] !== ''
        ? $data['waktu_lokal']
        : Config::companyNow()->format('Y-m-d H:i:s');
    try {
        $service = new AdminAttendanceService();
        $status = $service->checkOut(
            Auth::adminId(),
            $waktuLokal,
            isset($data['latitude']) ? $data['latitude'] : null,
            isset($data['longitude']) ? $data['longitude'] : null,
            isset($data['selfie']) ? $data['selfie'] : null
        );
        $totalJam = null;
        if ($status['jam_masuk'] && $status['jam_pulang']) {
            $tanggalKerja = Config::companyToday();
            $diff = (new DateTime($tanggalKerja . ' ' . $status['jam_masuk']))
                ->diff(new DateTime($tanggalKerja . ' ' . $status['jam_pulang']));
            $totalJam = round($diff->h + ($diff->i / 60), 2) . ' jam';
        }
        Json::out(array(
            'success' => true,
            'message' => 'Absen pulang berhasil',
            'data' => array(
                'id' => $status['id'],
                'jam_pulang' => $status['jam_pulang'],
                'total_jam_kerja' => $totalJam,
                'latitude' => isset($data['latitude']) ? (float)$data['latitude'] : null,
                'longitude' => isset($data['longitude']) ? (float)$data['longitude'] : null,
                'selfie_in_url' => $status['selfie_in_url'],
                'selfie_out_url' => $status['selfie_out_url'],
            ),
        ));
    } catch (InvalidArgumentException $e) {
        Json::error($e->getMessage(), 422);
    } catch (RuntimeException $e) {
        Json::error($e->getMessage(), 409);
    } catch (Exception $e) {
        Json::error('Gagal menyimpan absen pulang Admin HR', 500);
    }
}

if (!$employee_id) {
    Json::error('Akun ini tidak terhubung ke data karyawan', 400);
}

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

$selfieColumn = '';
foreach (array('selfie_path', 'selfie_out_path') as $column) {
    $check = $pdo->query("SHOW COLUMNS FROM attendances LIKE '" . $column . "'");
    if ($check && $check->fetch()) {
        $selfieColumn .= ', ' . $column;
    }
}
$filterDeleted = '';
$checkDeleted = $pdo->query("SHOW COLUMNS FROM attendances LIKE 'deleted_at'");
if ($checkDeleted && $checkDeleted->fetch()) {
    $filterDeleted = ' AND deleted_at IS NULL';
}
$stmt = $pdo->prepare("SELECT id, clock_in, clock_out$selfieColumn FROM attendances WHERE employee_id = ? AND date = ?$filterDeleted ORDER BY id DESC LIMIT 1");
$stmt->execute([$employee_id, $tanggal]);
$absen = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$absen) {
    Json::error("Anda belum absen masuk hari ini untuk tanggal $tanggal.", 400);
}

if ($absen['clock_out'] && $absen['clock_out'] !== '00:00:00') {
    Json::error('Anda sudah absen pulang hari ini.', 400);
}

$clock_in = new DateTime($tanggal . ' ' . $absen['clock_in']);
$clock_out = new DateTime($tanggal . ' ' . $waktu_sekarang);
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
        'id' => (int)$absen['id'],
        'jam_pulang' => $waktu_sekarang,
        'total_jam_kerja' => round($total_jam, 2) . ' jam',
        'latitude' => $lat,
        'longitude' => $lng,
        'selfie_in_url' => !empty($absen['selfie_path']) ? 'selfie.php?id=' . (int)$absen['id'] . '&type=in' : null,
        'selfie_out_url' => $selfie ? 'selfie.php?id=' . (int)$absen['id'] . '&type=out' : null,
    ),
));
