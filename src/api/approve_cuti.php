<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once __DIR__ . '/../common/DBConnection.php';

$data = json_decode(file_get_contents("php://input"));

// Validasi data yang dikirim
if (!empty($data->leave_id) && isset($data->status)) {
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=hrm;charset=utf8mb4", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Update status cuti (1 = Pending, 2 = Disetujui, 3 = Ditolak)
        // Asumsi: kolom 'staus' di tabel leaves adalah integer
        $query = "UPDATE leaves SET status = ? WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$data->status, $data->leave_id]);
        
        http_response_code(200);
        echo json_encode(["status" => "success", "message" => "Status cuti berhasil diperbarui"]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Gagal: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Data tidak lengkap"]);
}
?>