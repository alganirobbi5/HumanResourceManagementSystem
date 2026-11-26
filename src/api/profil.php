<?php

require_once __DIR__ . '/../common/DBConnection.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';

$user = Auth::requireApi();
$isHr = (isset($user['role']) && $user['role'] === 'hr');
$employee_id_from_client = isset($_GET['employee_id']) ? $_GET['employee_id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        $body = $_POST;
    }
    $email = isset($body['email']) ? trim((string)$body['email']) : '';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Json::error('Alamat email tidak valid', 422);
    }

    $pdo = DBConnection::pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $updatedIn = '';

    try {
        if ($user['employee_id']) {
            $stmt = $pdo->prepare('UPDATE employees SET email = ? WHERE id = ?');
            $stmt->execute(array($email, (int)$user['employee_id']));
            $updatedIn = 'employees';
        } else {
            $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'email'");
            if ($col && $col->fetch()) {
                $stmt = $pdo->prepare('UPDATE users SET email = ? WHERE id = ?');
                $stmt->execute(array($email, (int)$user['id']));
                $updatedIn = 'users';
            } else {
                Json::error('Kolom email akun belum tersedia', 500);
            }
        }
    } catch (PDOException $e) {
        Json::error('Gagal menyimpan email profil', 500);
    }

    Json::ok('Email profil berhasil disimpan', array(
        'email' => $email,
        'tersimpan_di' => $updatedIn,
    ));
    exit;
}

// Karyawan biasa hanya boleh melihat profil sendiri
if (!$isHr) {
    // Ambil profil dari employee_id di session, abaikan parameter dari client
    $my_employee_id = $user['employee_id'];
    if (!$my_employee_id) {
        Json::error('Akun ini tidak terhubung ke data karyawan', 400);
    }

    $pdo = DBConnection::pdo();
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
if ($isHr && $employee_id_from_client) {
    // Validasi: employee_id dari client harus berupa angka
    $target_employee_id = (int)$employee_id_from_client;

    $pdo = DBConnection::pdo();
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

// Admin HR tanpa employee_id: profil diambil dari identitas akun yang login.
if ($isHr && !$employee_id_from_client) {
    $email = null;
    try {
        $pdo = DBConnection::pdo();
        $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'email'");
        if ($col && $col->fetch()) {
            $stmt = $pdo->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
            $stmt->execute(array((int)$user['id']));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $email = $row ? $row['email'] : null;
        }
    } catch (PDOException $e) {
        $email = null;
    }

    Json::ok('Profil berhasil diambil', array('data' => array(
        'username' => $user['username'],
        'role' => $user['role'],
        'nama_lengkap' => $user['display_name'],
        'first_name' => $user['display_name'],
        'last_name' => '',
        'email' => $email,
        'sisa_cuti' => 0,
    )));
}

// Jika tidak ada kondisi di atas terpenuhi
Json::error('Anda tidak berwenang mengakses profil ini', 403);
?>