<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

try {
    $pdo = new PDO("mysql:host=localhost;dbname=hris;charset=utf8mb4", "HRIS", "Admin@2026");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Kolom attachment opsional: tetap kompatibel jika migrasi belum dijalankan.
    $hasAttachment = false;
    try {
        $col = $pdo->query("SHOW COLUMNS FROM notices LIKE 'attachment'");
        $hasAttachment = $col && $col->fetch() !== false;
    } catch (PDOException $e) {
        $hasAttachment = false;
    }

    $select = $hasAttachment
        ? "SELECT id, subject AS title, description AS message, notice_date AS created_at, attachment FROM notices ORDER BY id DESC"
        : "SELECT id, subject AS title, description AS message, notice_date AS created_at FROM notices ORDER BY id DESC";

    $stmt = $pdo->query($select);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(["status" => "success", "data" => $data]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
