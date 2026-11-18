<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->title) && !empty($data->message)) {
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=hris;charset=utf8mb4", "HRIS", "Admin@2026");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Sesuaikan nama kolom dengan tabel 'notices' di database VPS
        $query = "INSERT INTO notices (title, message, created_at) VALUES (?, ?, NOW())";
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            $data->title,
            $data->message
        ]);

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