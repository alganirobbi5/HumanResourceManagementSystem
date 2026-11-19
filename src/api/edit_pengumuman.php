<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$data = json_decode(file_get_contents("php://input"));

$id = isset($data->id) ? (int)$data->id : 0;
$title = isset($data->title) ? trim((string)$data->title) : '';
$message = isset($data->message) ? trim((string)$data->message) : '';

if ($id <= 0 || $title === '' || $message === '') {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "id, judul, dan pesan wajib diisi"]);
    exit;
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=hris;charset=utf8mb4", "HRIS", "Admin@2026");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $hasAttachment = false;
    try {
        $col = $pdo->query("SHOW COLUMNS FROM notices LIKE 'attachment'");
        $hasAttachment = $col && $col->fetch() !== false;
    } catch (PDOException $e) {
        $hasAttachment = false;
    }

    if ($hasAttachment && isset($data->attachment)) {
        $stmt = $pdo->prepare(
            "UPDATE notices SET subject = ?, description = ?, attachment = ?, update_by = 'HR', update_date = NOW() WHERE id = ?"
        );
        $stmt->execute([$title, $message, (string)$data->attachment, $id]);
    } else {
        $stmt = $pdo->prepare(
            "UPDATE notices SET subject = ?, description = ?, update_by = 'HR', update_date = NOW() WHERE id = ?"
        );
        $stmt->execute([$title, $message, $id]);
    }

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Pengumuman tidak ditemukan"]);
        exit;
    }

    echo json_encode(["status" => "success", "message" => "Pengumuman berhasil diperbarui"]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "MYSQL ERROR: " . $e->getMessage()]);
}
?>
