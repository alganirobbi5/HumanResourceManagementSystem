<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once __DIR__ . '/../common/DBConnection.php';

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->username) && !empty($data->password)) {
    $username = $data->username;
    $password = $data->password;

    try {
        $pdo = new PDO("mysql:host=localhost;dbname=hrm;charset=utf8mb4", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 1. CEK KE TABEL USERS (KARYAWAN / HR BARU)
        $stmt = $pdo->prepare("SELECT u.*, e.first_name, e.last_name 
                               FROM users u 
                               LEFT JOIN employees e ON e.id = u.employee_id
                               WHERE u.username = ? AND u.is_active = 1 LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "data" => [
                    "user_id" => $user['id'],
                    "username" => $user['username'],
                    "role" => $user['role'],
                    "nama_lengkap" => trim($user['first_name'] . ' ' . $user['last_name'])
                ]
            ]);
            exit;
        }

        // 2. CEK KE TABEL ADMINS (HR LAMA / RAZU)
        $stmtAdmin = $pdo->prepare("SELECT * FROM admins WHERE name = ? LIMIT 1");
        $stmtAdmin->execute([$username]);
        $admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

        // Karena di tabel admins passwordnya teks biasa (123), kita cek langsung
        if ($admin && $password == $admin['password']) {
            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "data" => [
                    "user_id" => $admin['id'],
                    "username" => $admin['name'],
                    "role" => "hr", // Paksa role menjadi hr
                    "nama_lengkap" => $admin['name']
                ]
            ]);
            exit;
        }

        // Jika tidak ketemu di dua tabel
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Username atau password salah"]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Error: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Data tidak lengkap"]);
}
?>