<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';

$user = Auth::requireApi();
$employee_id = $user['employee_id'];

if (!$employee_id) {
    Json::error('Akun ini tidak terhubung ke data karyawan', 400);
}

$data = Json::readBody();

// Field opsional dari Flutter (selfie pulang, GPS). Diabaikan aman jika
// skema database belum memiliki kolom pendukung. Identitas tetap dari token.
$selfie = isset($data['selfie']) ? (string)$data['selfie'] : '';
$latitude = isset($data['latitude']) ? (string)$data['latitude'] : '';
$longitude = isset($data['longitude']) ? (string)$data['longitude'] : '';

if (empty($data['waktu_lokal'])) {
    Json::error('waktu_lokal wajib diisi', 400);
}

// Ambil tanggal dan waktu dari HP
$tanggal = date('Y-m-d', strtotime($data['waktu_lokal']));
$waktu_sekarang = date('H:i:s', strtotime($data['waktu_lokal']));

// Cari data absen hari ini (berdasarkan employee_id dari session dan date)
$pdo = new PDO("mysql:host=localhost;dbname=hris;charset=utf8mb4", "HRIS", "Admin@2026");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $pdo->prepare("SELECT id, clock_in, clock_out FROM attendances WHERE employee_id = ? AND date = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$employee_id, $tanggal]);
$absen = $stmt->fetch(PDO::FETCH_ASSOC);

if ($absen) {
    // Jika clock_out masih 00:00:00
    if ($absen['clock_out'] == '00:00:00' || $absen['clock_out'] == null) {
        // Hitung selisih jam
        $clock_in = new DateTime($absen['clock_in']);
        $clock_out = new DateTime($waktu_sekarang);
        $diff = $clock_in->diff($clock_out);
        $total_jam = $diff->h + ($diff->i / 60);

        // Update
        $update = $pdo->prepare("UPDATE attendances SET clock_out = ?, total_work = ? WHERE id = ?");
        $update->execute([$waktu_sekarang, $total_jam, $absen['id']]);

        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Absen pulang berhasil",
            "data" => [
                "total_jam_kerja" => round($total_jam, 2) . " jam"
            ]
        ]);
        exit;
    } else {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Anda sudah absen pulang hari ini."
        ]);
        exit;
    }
} else {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Anda belum absen masuk hari ini untuk tanggal $tanggal."
    ]);
    exit;
}
?>
