<?php
// Izinkan akses dari mana saja (CORS) - Penting untuk API
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once __DIR__ . '/../common/DBConnection.php';

// Ambil data JSON yang dikirim dari Flutter
$data = json_decode(file_get_contents("php://input"));

if (!empty($data->username) && !empty($data->password)) {
    $username = $data->username;
    $password = $data->password;

    $db = new DBConnection();
    
    // Cari user di database (menggunakan tabel users, bukan admins)
    $user = $db->selectOne(
        'SELECT u.*, e.first_name, e.last_name 
         FROM users u 
         LEFT JOIN employees e ON e.id = u.employee_id
         WHERE u.username = ? AND u.is_active = 1 LIMIT 1',
        array($username)
    );

    // Cek apakah user ada dan password cocok
    if ($user && password_verify($password, $user['password_hash'])) {
        // Jika berhasil, kirim response JSON
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "message" => "Login berhasil",
            "data" => [
                "user_id" => $user['id'],
                "username" => $user['username'],
                "role" => $user['role'],
                "nama_lengkap" => trim($user['first_name'] . ' ' . $user['last_name'])
            ]
        ]);
    } else {
        // Jika gagal
        http_response_code(401);
        echo json_encode([
            "status" => "error",
            "message" => "Username atau password salah"
        ]);
    }
} else {
    // Jika data tidak lengkap
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Data username dan password harus diisi"
    ]);
}
?>