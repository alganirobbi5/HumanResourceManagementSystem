<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once __DIR__ . '/../common/DBConnection.php';

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->username) && !empty($data->password)) {
    $username = $data->username;
    $password = $data->password;

    try {
        $pdo = DBConnection::pdo();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 1. CEK KE TABEL USERS (KARYAWAN / HR BARU)
        $stmt = $pdo->prepare("SELECT u.*, e.first_name, e.last_name 
                               FROM users u 
                               LEFT JOIN employees e ON e.id = u.employee_id
                               WHERE u.username = ? AND u.is_active = 1 LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            // Terbitkan token dan simpan ke session
            $token = bin2hex(random_bytes(32));
            $stmt2 = $pdo->prepare('UPDATE users SET api_token = ? WHERE id = ?');
            $stmt2->execute([$token, (int)$user['id']]);

            $_SESSION = array();
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['username'];
            $_SESSION['employee_id'] = $user['employee_id'] !== null ? (int)$user['employee_id'] : null;
            $_SESSION['display_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
            $_SESSION['api_token'] = $token;

            // Kompatibilitas dengan halaman admin lama
            if ($user['role'] === 'hr') {
                $_SESSION['admin_name'] = $_SESSION['display_name'];
            } else {
                unset($_SESSION['admin_name'], $_SESSION['admin_password']);
            }

            http_response_code(200);
            echo json_encode([
                "success" => true,
                "message" => "Login berhasil",
                "data" => [
                    "token" => $token,
                    "user" => [
                        "user_id" => $user['id'],
                        "employee_id" => $user['employee_id'],
                        "username" => $user['username'],
                        "role" => $user['role'],
                        "nama_lengkap" => $_SESSION['display_name']
                    ]
                ]
            ]);
            exit;
        }

        // 2. CEK KE TABEL ADMINS (HR LAMA / RAZU) - fallback
        $stmtAdmin = $pdo->prepare("SELECT * FROM admins WHERE name = ? LIMIT 1");
        $stmtAdmin->execute([$username]);
        $admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

        // Karena di tabel admins passwordnya teks biasa, cek langsung
        if ($admin && $password == $admin['password']) {
            // Terbitkan token untuk legacy admin
            $token = bin2hex(random_bytes(32));
            $stmt3 = $pdo->prepare('UPDATE users SET api_token = ? WHERE id = ?');
            $stmt3->execute([$token, (int)$admin['id']]);

            $_SESSION = array();
            $_SESSION['user_id'] = (int)$admin['id'];
            $_SESSION['user_role'] = 'hr';
            $_SESSION['user_name'] = $admin['name'];
            $_SESSION['employee_id'] = null;
            $_SESSION['display_name'] = $admin['name'];
            $_SESSION['api_token'] = $token;
            $_SESSION['admin_name'] = $admin['name'];

            http_response_code(200);
            echo json_encode([
                "success" => true,
                "message" => "Login berhasil",
                "data" => [
                    "token" => $token,
                    "user" => [
                        "user_id" => $admin['id'],
                        "employee_id" => null,
                        "username" => $admin['name'],
                        "role" => "hr",
                        "nama_lengkap" => $admin['name']
                    ]
                ]
            ]);
            exit;
        }

        // Jika tidak ketemu di dua tabel
        http_response_code(401);
        echo json_encode([
            "success" => false,
            "message" => "Username atau password salah"
        ]);
        exit;

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Terjadi kesalahan server"
        ]);
        exit;
    }
} else {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Data tidak lengkap"
    ]);
    exit;
}
?>