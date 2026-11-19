<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

require_once __DIR__ . '/../common/DBConnection.php';

$employee_id = isset($_GET['employee_id']) ? $_GET['employee_id'] : null;

if ($employee_id) {
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=hris;charset=utf8mb4", "HRIS", "Admin@2026");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Kolom reason opsional (judul tersimpan di `type`).
        $hasReason = false;
        try {
            $col = $pdo->query("SHOW COLUMNS FROM leaves LIKE 'reason'");
            $hasReason = $col && $col->fetch() !== false;
        } catch (PDOException $e) {
            $hasReason = false;
        }

        $select = $hasReason
            ? "SELECT id, type, reason, leave_from, leave_to, status FROM leaves WHERE employee_id = ? ORDER BY id DESC"
            : "SELECT id, type, leave_from, leave_to, status FROM leaves WHERE employee_id = ? ORDER BY id DESC";

        $stmt = $pdo->prepare($select);
        $stmt->execute([$employee_id]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "data" => $data
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Gagal: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "employee_id tidak ditemukan"]);
}
?>