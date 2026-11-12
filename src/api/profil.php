<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

require_once __DIR__ . '/../common/DBConnection.php';

$employee_id = isset($_GET['employee_id']) ? $_GET['employee_id'] : null;

if ($employee_id) {
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=hrm;charset=utf8mb4", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 1. Coba cari di tabel employees (untuk Karyawan)
        $query = "SELECT e.first_name, e.last_name, e.email, u.username, u.role 
                  FROM employees e 
                  JOIN users u ON e.id = u.employee_id 
                  WHERE e.id = ? LIMIT 1";
                  
        $stmt = $pdo->prepare($query);
        $stmt->execute([$employee_id]);
        $profil = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($profil) {
            $profil['nama_lengkap'] = trim($profil['first_name'] . ' ' . $profil['last_name']);
            
            // Ambil sisa cuti
            $stmtCuti = $pdo->prepare("SELECT remaining_leave FROM employee_leaves WHERE employee_id = ? LIMIT 1");
            $stmtCuti->execute([$employee_id]);
            $cuti = $stmtCuti->fetch(PDO::FETCH_ASSOC);
            $profil['sisa_cuti'] = $cuti ? (int)$cuti['remaining_leave'] : 0;

            http_response_code(200);
            echo json_encode(["status" => "success", "data" => $profil]);
            exit;
        }

        // 2. JIKA TIDAK KETEMU DI EMPLOYEES, COBA CARI DI TABEL ADMINS (Untuk HR)
        $stmtAdmin = $pdo->prepare("SELECT * FROM admins WHERE id = ? LIMIT 1");
        $stmtAdmin->execute([$employee_id]);
        $admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

        if ($admin) {
            http_response_code(200);
            echo json_encode([
                "status" => "success", 
                "data" => [
                    "nama_lengkap" => $admin['name'],
                    "email" => "admin@perusahaan.com", // Dummy karena tabel admin tidak punya email
                    "username" => $admin['name'],
                    "role" => "hr",
                    "sisa_cuti" => 12 // HR biasanya punya kuota penuh
                ]
            ]);
            exit;
        }

        // Jika tidak ada di keduanya
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Profil tidak ditemukan"]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Gagal: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "employee_id tidak ditemukan"]);
}
?>