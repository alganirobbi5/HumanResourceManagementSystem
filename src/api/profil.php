<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';

$user = Auth::requireApi();
$employee_id_from_client = isset($_GET['employee_id']) ? $_GET['employee_id'] : null;

// Karyawan biasa hanya boleh melihat profil sendiri
if (!$user['isHr']) {
    // Ambil profil dari employee_id di session, abaikan parameter dari client
    $my_employee_id = $user['employee_id'];
    if (!$my_employee_id) {
        Json::error('Akun ini tidak terhubung ke data karyawan', 400);
    }

    $pdo = new PDO("mysql:host=localhost;dbname=hris;charset=utf8mb4", "HRIS", "Admin@2026");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SELECT u.username, u.role, e.first_name, e.last_name, e.email, u.avatar 
                           FROM users u 
                           JOIN employees e ON e.id = u.employee_id 
                           WHERE u.employee_id = ? LIMIT 1");
    $stmt->execute([$my_employee_id]);
    $profil = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profil) {
        Json::error('Profil tidak ditemukan', 404);
    }

    $profil['nama_lengkap'] = trim($profil['first_name'] . ' ' . $profil['last_name']);

    // Cek sisa cuti
    $stmtCuti = $pdo->prepare("SELECT remaining_leave FROM employee_leaves WHERE employee_id = ? LIMIT 1");
    $stmtCuti->execute([$my_employee_id]);
    $cuti = $stmtCuti->fetch(PDO::FETCH_ASSOC);
    $profil['sisa_cuti'] = $cuti ? (int)$cuti['remaining_leave'] : 0;

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "message" => "Profil berhasil diambil",
        "data" => $profil
    ]);
    exit;
}

// HR dapat melihat profil karyawan lain, TAPI hanya jika employee_id valid di request
// dan sesuai aturan role
if ($user['isHr'] && $employee_id_from_client) {
    // Validasi: employee_id dari client harus berupa angka
    $target_employee_id = (int)$employee_id_from_client;

    $pdo = new PDO("mysql:host=localhost;dbname=hris;charset=utf8mb4", "HRIS", "Admin@2026");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SELECT u.username, u.role, e.first_name, e.last_name, e.email, u.avatar 
                           FROM users u 
                           JOIN employees e ON e.id = u.employee_id 
                           WHERE u.employee_id = ? LIMIT 1");
    $stmt->execute([$target_employee_id]);
    $profil = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profil) {
        http_response_code(404);
        echo json_encode([
            "success" => false,
            "message" => "Profil karyawan tujuan tidak ditemukan"
        ]);
        exit;
    }

    $profil['nama_lengkap'] = trim($profil['first_name'] . ' ' . $profil['last_name']);

    // Cek sisa cuti
    $stmtCuti = $pdo->prepare("SELECT remaining_leave FROM employee_leaves WHERE employee_id = ? LIMIT 1");
    $stmtCuti->execute([$target_employee_id]);
    $cuti = $stmtCuti->fetch(PDO::FETCH_ASSOC);
    $profil['sisa_cuti'] = $cuti ? (int)$cuti['remaining_leave'] : 0;

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "message" => "Profil karyawan berhasil diambil",
        "data" => $profil
    ]);
    exit;
}

// Jika HR tapi tidak ada employee_id di request
if ($user['isHr'] && !$employee_id_from_client) {
    Json::error('employee_id harus disediakan untuk melihat profil karyawan lain', 400);
}

// Jika tidak ada kondisi di atas terpenuhi
Json::error('Anda tidak berwenang mengakses profil ini', 403);
?>