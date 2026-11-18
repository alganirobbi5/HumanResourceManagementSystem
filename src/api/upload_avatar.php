<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = isset($_POST['employee_id']) ? $_POST['employee_id'] : null;

    if (!empty($employee_id) && isset($_FILES['avatar'])) {
        $target_dir = "../../uploads/";
        $file_name = "avatar_" . $employee_id . "_" . time() . ".jpg";
        $target_file = $target_dir . $file_name;

        if (move_uploaded_file($_FILES["avatar"]["tmp_name"], $target_file)) {
            try {
                $pdo = new PDO("mysql:host=localhost;dbname=hris;charset=utf8mb4", "HRIS", "Admin@2026");
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Update kolom avatar di tabel users (pastikan kolom 'avatar' ada di database)
                $query = "UPDATE users SET avatar = ? WHERE employee_id = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$file_name, $employee_id]);

                echo json_encode(["status" => "success", "message" => "Avatar berhasil diupload", "filename" => $file_name]);
            } catch (PDOException $e) {
                echo json_encode(["status" => "error", "message" => $e->getMessage()]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Gagal mengupload file"]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Data tidak lengkap"]);
    }
}
?>