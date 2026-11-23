<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once __DIR__ . '/../common/DBConnection.php';

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->employee_id) && !empty($data->start_date) && !empty($data->end_date) && !empty($data->reason)) {
    try {
        $pdo = DBConnection::pdo();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $judul = !empty($data->title) ? (string)$data->title : (string)$data->reason;
        $alasan = (string)$data->reason;

        // Kolom reason opsional; tetap kompatibel jika migrasi belum dijalankan.
        $hasReason = false;
        try {
            $col = $pdo->query("SHOW COLUMNS FROM leaves LIKE 'reason'");
            $hasReason = $col && $col->fetch() !== false;
        } catch (PDOException $e) {
            $hasReason = false;
        }

        if ($hasReason) {
            $query = "INSERT INTO leaves (employee_id, type, reason, leave_from, leave_to, total_leave, status, created_at)
              VALUES (?, ?, ?, ?, ?, 1, 1, ?)";
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                $data->employee_id,
                $judul,
                $alasan,
                $data->start_date,
                $data->end_date,
                time(),
            ]);
        } else {
            $query = "INSERT INTO leaves (employee_id, type, leave_from, leave_to, total_leave, status, created_at)
              VALUES (?, ?, ?, ?, 1, 1, ?)";
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                $data->employee_id,
                $judul,
                $data->start_date,
                $data->end_date,
                time(),
            ]);
        }
        
        http_response_code(201);
        echo json_encode(["status" => "success", "message" => "Pengajuan cuti berhasil dikirim"]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error", 
            "message" => "MYSQL ERROR: " . $e->getMessage()
        ]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Data cuti tidak lengkap"]);
}
?>