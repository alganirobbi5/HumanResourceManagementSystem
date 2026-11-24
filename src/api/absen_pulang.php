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

$waktu_lokal = isset($data['waktu_lokal']) ? trim((string)$data['waktu_lokal']) : '';
if ($waktu_lokal === '') {
    Json::error('waktu_lokal wajib diisi', 400);
}

$tanggal = date('Y-m-d', strtotime($waktu_lokal));
$waktu_sekarang = date('H:i:s', strtotime($waktu_lokal));

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

$selfiePath = null;
if (isset($_FILES['selfie']) && $_FILES['selfie']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['selfie'];
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
    $filename = 'selfie_pulang_' . uniqid('', true) . '.' . $ext;
    $absolutePath = $absoluteDir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
        Json::error('Gagal menyimpan foto selfie, silakan coba lagi', 500);
    }
    @chmod($absolutePath, 0644);
    $selfiePath = $relativeDir . '/' . $filename;
}

$clock_in = new DateTime($absen['clock_in']);
$clock_out = new DateTime($waktu_sekarang);
$diff = $clock_in->diff($clock_out);
$total_jam = $diff->h + ($diff->i / 60);

$update = $pdo->prepare("UPDATE attendances SET clock_out = ?, total_work = ? WHERE id = ?");
$update->execute([$waktu_sekarang, $total_jam, $absen['id']]);

Json::out(array(
    'success' => true,
    'message' => 'Absen pulang berhasil',
    'data' => array(
        'total_jam_kerja' => round($total_jam, 2) . ' jam',
        'latitude' => $lat,
        'longitude' => $lng,
        'selfie_path' => $selfiePath,
    ),
));
