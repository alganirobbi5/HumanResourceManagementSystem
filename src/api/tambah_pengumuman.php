<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->title) && !empty($data->message)) {
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=hris;charset=utf8mb4", "HRIS", "Admin@2026");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $tanggal = date('Y-m-d');
        $waktu = date('H:i:s');
        $attachment = isset($data->attachment) ? (string)$data->attachment : '';

        $hasAttachment = false;
        try {
            $col = $pdo->query("SHOW COLUMNS FROM notices LIKE 'attachment'");
            $hasAttachment = $col && $col->fetch() !== false;
        } catch (PDOException $e) {
            $hasAttachment = false;
        }

        if ($hasAttachment) {
            $query = "INSERT INTO notices (subject, description, notice_date, notice_time, status, insert_by, insert_date, update_by, update_date, attachment)
                      VALUES (?, ?, ?, ?, 1, 'HR', NOW(), 'HR', NOW(), ?)";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$data->title, $data->message, $tanggal, $waktu, $attachment]);
        } else {
            $query = "INSERT INTO notices (subject, description, notice_date, notice_time, status, insert_by, insert_date, update_by, update_date)
                      VALUES (?, ?, ?, ?, 1, 'HR', NOW(), 'HR', NOW())";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$data->title, $data->message, $tanggal, $waktu]);
        }

        http_response_code(201);
        echo json_encode(["status" => "success", "message" => "Pengumuman berhasil dibuat"]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "MYSQL ERROR: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Judul dan pesan tidak boleh kosong"]);
}
?>
