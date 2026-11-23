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

if (empty($data['latitude']) || empty($data['longitude'])) {
    Json::error('latitude dan longitude wajib diisi', 400);
}

// Validasi rentang GPS
$lat = (float)$data['latitude'];
$lng = (float)$data['longitude'];

if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    Json::error('Koordinat GPS berada di luar rentang yang valid', 422);
}

// Ambil tanggal dan waktu
if (isset($data['tanggal_lokal']) && isset($data['waktu_lokal'])) {
    $tanggal = $data['tanggal_lokal'];
    $waktu_sekarang = $data['waktu_lokal'];
} else {
    $tanggal = date('Y-m-d');
    $waktu_sekarang = date('H:i:s');
}

// --- LOGIKA GEOFENCING ---

// 1. Ambil semua lokasi dari database
$pdo = DBConnection::pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmtLokasi = $pdo->query("SELECT * FROM office_locations");
$lokasi_kantor = $stmtLokasi->fetchAll(PDO::FETCH_ASSOC);

$is_dalam_radius = false;

// 2. Looping cek jarak ke setiap lokasi menggunakan rumus Haversine
foreach ($lokasi_kantor as $lokasi) {
    $kantor_lat = (float)$lokasi['latitude'];
    $kantor_lng = (float)$lokasi['longitude'];
    $radius_maksimal = (float)$lokasi['radius_meter'];

    // Haversine formula
    $delta_lat = deg2rad($kantor_lat - $lat);
    $delta_lng = deg2rad($kantor_lng - $lng);
    $a = sin($delta_lat / 2) * sin($delta_lat / 2) +
         cos(deg2rad($lat)) * cos(deg2rad($kantor_lat)) *
         sin($delta_lng / 2) * sin($delta_lng / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    $jarak_meter = 6371000 * $c; // 6371 km dalam meter

    // Jika karyawan berada di dalam radius salah satu lokasi
    if ($jarak_meter <= $radius_maksimal) {
        $is_dalam_radius = true;
        break; // Keluar dari loop, tidak perlu cek lokasi lain
    }
}

// 3. Jika tidak ada satu pun lokasi yang cocok
if (!$is_dalam_radius) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Anda berada di luar area absensi yang diizinkan."
    ]);
    exit;
}

// 4. Cek apakah sudah absen hari ini (berdasarkan employee_id dari session)
$stmtCek = $pdo->prepare("SELECT id FROM attendances WHERE employee_id = ? AND date = ?");
$stmtCek->execute([$employee_id, $tanggal]);

if ($stmtCek->fetch()) {
    http_response_code(400); // Bad Request
    echo json_encode([
        "success" => false,
        "message" => "Anda sudah melakukan absen hari ini."
    ]);
    exit;
}

// 5. Insert absen baru
$query = "INSERT INTO attendances (employee_id, date, clock_in, clock_out, total_work, status, latitude, longitude) 
          VALUES (?, ?, ?, '00:00:00', 0, 1, ?, ?)";

$stmt = $pdo->prepare($query);
$result = $stmt->execute([$employee_id, $tanggal, $lat, $lng]);

if ($result) {
    http_response_code(201);
    echo json_encode([
        "success" => true,
        "message" => "Absen berhasil disimpan",
        "data" => [
            "jam_masuk" => $waktu_sekarang
        ]
    ]);
    exit;
} else {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Gagal menyimpan absen"
    ]);
    exit;
}
?>