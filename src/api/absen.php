<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once __DIR__ . '/../common/DBConnection.php';

$data = json_decode(file_get_contents("php://input"));

// Validasi data yang dikirim dari Flutter
if (!empty($data->employee_id) && !empty($data->latitude) && !empty($data->longitude)) {
    
    $db = new DBConnection();
    
    // Ambil waktu saat ini
    $waktu_sekarang = date('Y-m-d H:i:s');
    $tanggal_hari_ini = date('Y-m-d');
    
    // Query INSERT disesuaikan dengan tabel attendances (clock_in, status, dll)
    $query = "INSERT INTO attendances (employee_id, date, clock_in, latitude, longitude, status, created_at) 
              VALUES (?, ?, ?, ?, ?, 'Present', ?)";
    
    try {
        $stmt = $db->run($query, array(
            $data->employee_id, 
            $tanggal_hari_ini, 
            $waktu_sekarang, 
            $data->latitude, 
            $data->longitude,
            $waktu_sekarang
        ));
        
        http_response_code(201);
        echo json_encode([
            "status" => "success",
            "message" => "Absen berhasil disimpan",
            "waktu" => $waktu_sekarang
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Gagal menyimpan absen: " . $e->getMessage()
        ]);
    }
} else {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Data absen tidak lengkap"
    ]);
}
?>