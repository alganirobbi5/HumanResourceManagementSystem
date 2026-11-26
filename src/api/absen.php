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
$companyId = Auth::companyId();

if (!$employee_id) {
    Json::error('Akun ini tidak terhubung ke data karyawan', 400);
}

$data = Json::readRequest();

$isHrAdmin = !$user['employee_id'] && (isset($user['role']) && $user['role'] === 'hr') && Auth::adminId() !== null;
if ($isHrAdmin) {
    require_once __DIR__ . '/../service/AdminAttendanceService.php';
    require_once __DIR__ . '/../service/RateLimiter.php';
    if (!RateLimiter::allow('attendance_admin', Config::RATE_LIMIT_ATTENDANCE_SECONDS)) {
        Json::error('Terlalu sering mengirim absensi, tunggu beberapa saat', 429);
    }
    $requestId = isset($data['request_id']) ? preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)$data['request_id']) : null;
    try {
        $service = new AdminAttendanceService();
        $status = $service->checkIn(
            Auth::adminId(),
            isset($data['latitude']) ? $data['latitude'] : null,
            isset($data['longitude']) ? $data['longitude'] : null,
            isset($data['gps_accuracy']) ? $data['gps_accuracy'] : null,
            $requestId,
            isset($data['selfie']) ? $data['selfie'] : null,
            $companyId
        );
        Json::out(array(
            'success' => true,
            'message' => 'Absen berhasil disimpan',
            'data' => array(
                'id' => $status['id'],
                'jam_masuk' => $status['jam_masuk'],
                'tanggal' => $status['tanggal'],
                'latitude' => isset($data['latitude']) ? (float)$data['latitude'] : null,
                'longitude' => isset($data['longitude']) ? (float)$data['longitude'] : null,
                'selfie_in_url' => $status['selfie_in_url'],
                'selfie_out_url' => null,
            ),
        ), 201);
    } catch (InvalidArgumentException $e) {
        Json::error($e->getMessage(), 422);
    } catch (RuntimeException $e) {
        Json::error($e->getMessage(), 409);
    } catch (Exception $e) {
        Json::error('Gagal menyimpan absensi Admin HR', 500);
    }
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

require_once __DIR__ . '/../service/CompanyMasterService.php';
$master = new CompanyMasterService($pdo);
$lokasi_kantor = $master->officeLocations($companyId);

$is_dalam_radius = false;
foreach ($lokasi_kantor as $lokasi) {
    $kantor_lat = (float)$lokasi['latitude'];
    $kantor_lng = (float)$lokasi['longitude'];
    $radius_maksimal = (float)$lokasi['radius_meter'];

    $delta_lat = deg2rad($kantor_lat - $lat);
    $delta_lng = deg2rad($kantor_lng - $lng);
    $a = sin($delta_lat / 2) * sin($delta_lat / 2) +
         cos(deg2rad($lat)) * cos(deg2rad($kantor_lat)) *
         sin($delta_lng / 2) * sin($delta_lng / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    $jarak_meter = 6371000 * $c;

    if ($jarak_meter <= $radius_maksimal) {
        $is_dalam_radius = true;
        break;
    }
}

if (!$is_dalam_radius) {
    Json::error('Anda berada di luar area absensi yang diizinkan.', 400);
}

$filterDeletedCek = '';
$colDeleted = $pdo->query("SHOW COLUMNS FROM attendances LIKE 'deleted_at'");
if ($colDeleted && $colDeleted->fetch()) {
    $filterDeletedCek = ' AND deleted_at IS NULL';
}
$cekParams = array($employee_id, $tanggal);
if ($companyId !== null) {
    $filterDeletedCek .= ' AND company_id = ?';
    $cekParams[] = (int)$companyId;
}
$stmtCek = $pdo->prepare("SELECT id FROM attendances WHERE employee_id = ? AND date = ?$filterDeletedCek LIMIT 1");
$stmtCek->execute($cekParams);
if ($stmtCek->fetch()) {
    Json::error('Anda sudah melakukan absen hari ini.', 400);
}

$columns = array(
    'employee_id', 'date', 'clock_in', 'clock_out', 'total_work',
    'status', 'latitude', 'longitude',
);
$values = array(
    $employee_id, $tanggal, $waktu_sekarang, '00:00:00', 0, 1, $lat, $lng,
);
if ($companyId !== null) {
    $columns[] = 'company_id';
    $values[] = (int)$companyId;
}

$selfie = AttendanceService::storeSelfie(
    isset($data['selfie']) ? $data['selfie'] : null,
    $tanggal,
    $employee_id,
    'selfie'
);

$optional = array(
    'gps_accuracy' => isset($data['gps_accuracy']) && is_numeric($data['gps_accuracy'])
        ? (float)$data['gps_accuracy'] : null,
    'selfie_path' => $selfie ? $selfie['path'] : null,
    'selfie_mime_type' => $selfie ? $selfie['mime_type'] : null,
);
foreach ($optional as $column => $value) {
    if ($value === null) {
        continue;
    }
    $check = $pdo->query("SHOW COLUMNS FROM attendances LIKE '" . $column . "'");
    if ($check && $check->fetch()) {
        $columns[] = $column;
        $values[] = $value;
    }
}

$placeholders = implode(', ', array_fill(0, count($columns), '?'));
$query = "INSERT INTO attendances (" . implode(', ', $columns) . ") VALUES ($placeholders)";
$stmt = $pdo->prepare($query);
$result = $stmt->execute($values);

if (!$result) {
    Json::error('Gagal menyimpan absen', 500);
}
$attendanceId = (int)$pdo->lastInsertId();

try {
    require_once __DIR__ . '/../service/NotificationService.php';
    $namaKaryawan = '';
    $employeeRow = $pdo->prepare(
        'SELECT first_name, last_name FROM employees WHERE id = ? LIMIT 1'
    );
    $employeeRow->execute(array((int)$employee_id));
    if ($found = $employeeRow->fetch(PDO::FETCH_ASSOC)) {
        $namaKaryawan = trim((string)$found['first_name'] . ' ' . (string)$found['last_name']);
    }
    if ($namaKaryawan === '') {
        $namaKaryawan = (string)(isset($user['username']) ? $user['username'] : 'Karyawan');
    }
    (new NotificationService())->notifyHr(
        'Absen Masuk Karyawan',
        $namaKaryawan . ' melakukan absen masuk pukul ' . $waktu_sekarang,
        'attendance',
        'attendance',
        $attendanceId
    );
} catch (Exception $e) {
    error_log('[FCM] notifikasi absen masuk gagal: ' . $e->getMessage());
}

Json::out(array(
    'success' => true,
    'message' => 'Absen berhasil disimpan',
    'data' => array(
        'id' => $attendanceId,
        'jam_masuk' => $waktu_sekarang,
        'tanggal' => $tanggal,
        'latitude' => $lat,
        'longitude' => $lng,
        'selfie_in_url' => $selfie ? 'selfie.php?id=' . $attendanceId . '&type=in' : null,
        'selfie_out_url' => null,
    ),
), 201);
