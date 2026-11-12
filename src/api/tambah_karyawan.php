<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once __DIR__ . '/../common/DBConnection.php';

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->first_name) && !empty($data->username) && !empty($data->password) && !empty($data->email)) {
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=hrm;charset=utf8mb4", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 1. Cek apakah username sudah dipakai
        $cekUser = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $cekUser->execute([$data->username]);
        if ($cekUser->fetch()) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Username sudah digunakan"]);
            exit;
        }

        // 2. Masukkan ke tabel employees (TAMBAHKAN password)
        $stmtEmp = $pdo->prepare("INSERT INTO employees (first_name, last_name, user_name, email, gender, password, department_id, designation_id, mobile_no, status) 
                                  VALUES (?, ?, ?, ?, 'L', ?, 0, 0, '', 1)");
        $stmtEmp->execute([
            $data->first_name,
            $data->last_name ?? '',
            $data->username, 
            $data->email,
            $data->password // <-- Isi kolom password dengan password dari input
        ]);
        
        $employee_id = $pdo->lastInsertId();

        // 3. Masukkan ke tabel users (untuk login)
        $hash = password_hash($data->password, PASSWORD_DEFAULT);
        $stmtUser = $pdo->prepare("INSERT INTO users (employee_id, username, password_hash, role, is_active) 
                                   VALUES (?, ?, ?, 'employee', 1)");
        $stmtUser->execute([
            $employee_id,
            $data->username,
            $hash
        ]);

        http_response_code(201);
        echo json_encode(["status" => "success", "message" => "Karyawan berhasil ditambahkan"]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Gagal: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Data tidak lengkap"]);
}
?>