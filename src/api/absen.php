<?php
date_default_timezone_set('Asia/Jakarta'); // <-- TAMBAHKAN BARIS INI

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once __DIR__ . '/../common/DBConnection.php';

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->employee_id) && !empty($data->latitude) && !empty($data->longitude)) {
    try {
        $db = new DBConnection();
        $tanggal = date('Y-m-d');
        $waktu_absen = date('H:i:s');
        
        // clock_out diisi '00:00:00' dan total_work diisi 0 agar tidak error NOT NULL
        $query = "INSERT INTO attendances (employee_id, date, clock_in, clock_out, total_work, status, latitude, longitude) 
                  VALUES (?, ?, ?, '00:00:00', 0, 1, ?, ?)";
                  
        $db->run($query, array(
            $data->employee_id, 
            $tanggal, 
            $waktu_absen, 
            $data->latitude, 
            $data->longitude
        ));
        
        http_response_code(201);
        echo json_encode([
            "status" => "success", 
            "message" => "Absen berhasil disimpan",
            "jam_masuk" => $waktu_absen // <-- Tambahkan ini agar dikirim ke Flutter
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error", 
            "message" => "Gagal menyimpan: " . $e->getMessage()
        ]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Data tidak lengkap"]);
}
?>