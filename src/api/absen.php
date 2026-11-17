<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once __DIR__ . '/../common/DBConnection.php';

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->employee_id) && !empty($data->latitude) && !empty($data->longitude)) {
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=hris;charset=utf8mb4", "HRIS", "Admin@2026");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Gunakan waktu dari Flutter (jika ada), jika tidak gunakan waktu server
        if (isset($data->tanggal_lokal) && isset($data->waktu_lokal)) {
            $tanggal = $data->tanggal_lokal;
            $waktu_sekarang = $data->waktu_lokal;
        } else {
            $tanggal = date('Y-m-d');
            $waktu_sekarang = date('H:i:s');
        }
        
        $cekAbsen = $pdo->prepare("SELECT id FROM attendances WHERE employee_id = ? AND date = ?");
        $cekAbsen->execute([$data->employee_id, $tanggal]);
        
        if ($cekAbsen->fetch()) {
            http_response_code(400); // Bad Request
            echo json_encode([
                "status" => "error", 
                "message" => "Anda sudah melakukan absen hari ini."
            ]);
            exit; // Hentikan proses
        }

        $query = "INSERT INTO attendances (employee_id, date, clock_in, clock_out, total_work, status, latitude, longitude) 
                  VALUES (?, ?, ?, '00:00:00', 0, 1, ?, ?)";
                  
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $data->employee_id, 
            $tanggal, 
            $waktu_sekarang, 
            $data->latitude, 
            $data->longitude
        ]);
        
        http_response_code(201);
        echo json_encode([
            "status" => "success", 
            "message" => "Absen berhasil disimpan",
            "jam_masuk" => $waktu_sekarang
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error", 
            "message" => "MYSQL ERROR: " . $e->getMessage()
        ]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Data tidak lengkap"]);
}
?>