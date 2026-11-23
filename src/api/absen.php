<?php

require_once __DIR__ . '/../common/DBConnection.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../common/Config.php';

$user = Auth::requireApi();
$employee_id = $user['employee_id'];

if (!$employee_id) {
    Json::error('Akun ini tidak terhubung ke data karyawan', 400);
}

$data = Json::readBody();
if (!is_array($data) || empty($data)) {
    $data = $_POST;
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

$stmtLokasi = $pdo->query("SELECT * FROM office_locations");
$lokasi_kantor = $stmtLokasi->fetchAll(PDO::FETCH_ASSOC);

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

$stmtCek = $pdo->prepare("SELECT id FROM attendances WHERE employee_id = ? AND date = ?");
$stmtCek->execute([$employee_id, $tanggal]);
if ($stmtCek->fetch()) {
    Json::error('Anda sudah melakukan absen hari ini.', 400);
}

if (!isset($_FILES['selfie'])) {
    Json::error('Foto selfie wajib diisi', 400);
}
$file = $_FILES['selfie'];
if ($file['error'] === UPLOAD_ERR_NO_FILE) {
    Json::error('Foto selfie wajib diisi', 400);
}
if ($file['error'] !== UPLOAD_ERR_OK) {
    Json::error('Upload foto gagal, silakan coba lagi', 400);
}
if (!is_uploaded_file($file['tmp_name'])) {
    Json::error('File selfie tidak valid', 400);
}
if ($file['size'] > Config::MAX_SELFIE_BYTES) {
    Json::error('Ukuran foto melebihi batas maksimal 2 MB', 422);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
if (!in_array($mime, Config::ALLOWED_SELFIE_MIME, true)) {
    Json::error('Tipe file foto tidak diizinkan (gunakan JPG, PNG, atau WebP)', 422);
}
$extMap = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp');
$ext = $extMap[$mime];

$relativeDir = $tanggal . '/' . $employee_id;
$absoluteDir = rtrim(Config::SELFIE_STORAGE_DIR, '/\\') . '/' .
    str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
    Json::error('Gagal menyiapkan penyimpanan foto selfie', 500);
}
$filename = 'selfie_' . uniqid('', true) . '.' . $ext;
$absolutePath = $absoluteDir . DIRECTORY_SEPARATOR . $filename;
if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
    Json::error('Gagal menyimpan foto selfie, silakan coba lagi', 500);
}
@chmod($absolutePath, 0644);
$relativePath = $relativeDir . '/' . $filename;

$columns = array(
    'employee_id', 'date', 'clock_in', 'clock_out', 'total_work',
    'status', 'latitude', 'longitude',
);
$values = array(
    $employee_id, $tanggal, $waktu_sekarang, '00:00:00', 0, 1, $lat, $lng,
);

$optional = array(
    'gps_accuracy' => isset($data['gps_accuracy']) && is_numeric($data['gps_accuracy'])
        ? (float)$data['gps_accuracy'] : null,
    'selfie_path' => $relativePath,
    'selfie_mime_type' => $mime,
    'selfie_size' => (int)$file['size'],
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
    @unlink($absolutePath);
    Json::error('Gagal menyimpan absen', 500);
}

Json::out(array(
    'success' => true,
    'message' => 'Absen berhasil disimpan',
    'data' => array(
        'jam_masuk' => $waktu_sekarang,
        'tanggal' => $tanggal,
        'latitude' => $lat,
        'longitude' => $lng,
        'selfie_path' => $relativePath,
    ),
), 201);
