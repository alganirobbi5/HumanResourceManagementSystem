<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once __DIR__ . '/../common/DBConnection.php';

$data = json_decode(file_get_contents("php://input"));

// Validasi data yang dikirim
if (!empty($data->leave_id) && isset($data->status)) {  
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=hris;charset=utf8mb4", "HRIS", "Admin@2026");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $comment = isset($data->comment) ? trim((string)$data->comment) : '';

        // Kolom comment opsional; tetap kompatibel jika migrasi belum dijalankan.
        $hasComment = false;
        try {
            $col = $pdo->query("SHOW COLUMNS FROM leaves LIKE 'comment'");
            $hasComment = $col && $col->fetch() !== false;
        } catch (PDOException $e) {
            $hasComment = false;
        }

        // Update status cuti (1 = Pending, 2 = Disetujui, 3 = Ditolak)
        if ($hasComment) {
            $query = "UPDATE leaves SET status = ?, comment = ? WHERE id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$data->status, $comment, $data->leave_id]);
        } else {
            $query = "UPDATE leaves SET status = ? WHERE id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$data->status, $data->leave_id]);
        }
        
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