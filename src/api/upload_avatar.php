<?php

require_once __DIR__ . '/../common/DBConnection.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = isset($_POST['employee_id']) ? trim((string)$_POST['employee_id']) : '';
    $username = isset($_POST['username']) ? trim((string)$_POST['username']) : '';

    if (($employee_id === '' && $username === '') || !isset($_FILES['avatar'])) {
        echo json_encode(["status" => "error", "message" => "Data tidak lengkap"]);
        exit;
    }

    try {
        $pdo = DBConnection::pdo();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $identity = $username !== '' ? $username : 'emp' . $employee_id;
        $safeIdentity = preg_replace('/[^a-zA-Z0-9_-]/', '_', $identity);
        $file_name = "avatar_{$safeIdentity}_" . time() . ".jpg";

        $target_dir = __DIR__ . '/../../uploads/';
        $savedToDisk = false;

        if (is_uploaded_file($_FILES['avatar']['tmp_name'])) {
            if (!is_dir($target_dir)) {
                @mkdir($target_dir, 0755, true);
            }
            if (is_dir($target_dir) && is_writable($target_dir)) {
                $savedToDisk = move_uploaded_file(
                    $_FILES['avatar']['tmp_name'],
                    $target_dir . $file_name
                );
            }
        }

        // Simpan avatar sebagai data URL di database agar tetap tersedia
        // walaupun folder uploads tidak writable.
        $avatarValue = $file_name;
        if (!$savedToDisk) {
            $raw = @file_get_contents($_FILES['avatar']['tmp_name']);
            if ($raw !== false && $raw !== '') {
                $mime = function_exists('mime_content_type')
                    ? (string)mime_content_type($_FILES['avatar']['tmp_name'])
                    : 'image/jpeg';
                if ($mime === '') $mime = 'image/jpeg';
                $avatarValue = 'data:' . $mime . ';base64,' . base64_encode($raw);
            }
        }

        if ($employee_id !== '') {
            $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE employee_id = ?");
            $stmt->execute([$avatarValue, $employee_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE username = ?");
            $stmt->execute([$avatarValue, $username]);
        }

        echo json_encode([
            "status" => "success",
            "message" => "Avatar berhasil disimpan",
            "filename" => $file_name,
            "avatar" => $avatarValue,
        ]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}
?>
