<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once __DIR__ . '/../common/DBConnection.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * employee_id hanya boleh diambil dari relasi karyawan yang nyata.
 * Jangan pernah mengarang atau menebak ID karyawan.
 */
function genuineEmployeeId($pdo, $candidate)
{
    if ($candidate === null || $candidate === '') {
        return null;
    }
    $stmt = $pdo->prepare("SELECT id FROM employees WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$candidate]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (int)$row['id'] : null;
}

/**
 * Cari atau buat identitas API untuk akun admin legacy.
 * Relasi eksplisit memakai users.admin_id, BUKAN nama dan BUKAN admins.id
 * sebagai employee_id. Tidak membuat data kepegawaian.
 */
function findOrCreateAdminApiIdentity($pdo, array $admin)
{
    try {
        $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'admin_id'");
        if (!$col || !$col->fetch()) {
            return null; // migrasi belum dijalankan
        }

        $adminId = (int)$admin['id'];
        $pdo->beginTransaction();

        // Kunci baris/kunci unik agar login bersamaan tidak membuat dua identitas.
        $stmt = $pdo->prepare("SELECT id, is_active FROM users WHERE admin_id = ? FOR UPDATE");
        $stmt->execute([$adminId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $pdo->commit();
            return (int)$existing['is_active'] === 1 ? (int)$existing['id'] : null;
        }

        // Username acak menghindari tabrakan username saat dua request bersamaan.
        $username = 'admin_' . $adminId . '_' . bin2hex(random_bytes(6));
        // Kolom email sengaja tidak dipakai: skema VPS users tidak memilikinya.
        $pdo->prepare(
            "INSERT INTO users (username, password_hash, role, employee_id, admin_id, is_active)
             VALUES (?, UUID(), 'hr', NULL, ?, 1)"
        )->execute([$username, $adminId]);

        $userId = (int)$pdo->lastInsertId();
        $pdo->commit();
        return $userId;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Request paralel lain mungkin menang; ambil identitas yang sudah dibuat.
        if ($e->getCode() === '23000') {
            try {
                $again = $pdo->prepare("SELECT id, is_active FROM users WHERE admin_id = ? LIMIT 1");
                $again->execute([(int)$admin['id']]);
                $row = $again->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    return (int)$row['is_active'] === 1 ? (int)$row['id'] : null;
                }
            } catch (PDOException $ignore) {
                // biarkan null
            }
        }
        return null;
    }
}

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->username) && !empty($data->password)) {
    $username = $data->username;
    $password = $data->password;

    try {
        $pdo = DBConnection::pdo();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 1. AKUN MODERN (tabel users)
        $stmt = $pdo->prepare("SELECT u.*, e.id AS employee_row_id, e.first_name, e.last_name
                               FROM users u
                               LEFT JOIN employees e ON e.id = u.employee_id
                               WHERE u.username = ? AND u.is_active = 1 LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            $employeeId = genuineEmployeeId($pdo, $user['employee_id']);
            $token = bin2hex(random_bytes(32));
            $stmt2 = $pdo->prepare('UPDATE users SET api_token = ? WHERE id = ?');
            $stmt2->execute([$token, (int)$user['id']]);

            $displayName = trim($user['first_name'] . ' ' . $user['last_name']);
            if ($displayName === '') {
                $displayName = $user['username'];
            }

            $_SESSION = array();
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['username'];
            $_SESSION['employee_id'] = $employeeId;
            $_SESSION['display_name'] = $displayName;
            $_SESSION['api_token'] = $token;

            if ($user['role'] === 'hr') {
                $_SESSION['admin_name'] = $displayName;
            } else {
                unset($_SESSION['admin_name'], $_SESSION['admin_password']);
            }

            http_response_code(200);
            echo json_encode([
                "success" => true,
                "message" => "Login berhasil",
                "data" => [
                    "token" => $token,
                    "employee_id" => $employeeId,
                    "user" => [
                        "user_id" => $user['id'],
                        "employee_id" => $employeeId,
                        "username" => $user['username'],
                        "role" => $user['role'],
                        "nama_lengkap" => $displayName
                    ]
                ]
            ]);
            exit;
        }

        // 2. AKUN ADMIN LEGACY (tabel admins)
        // Kredensial tetap diverifikasi dari admins. Identitas API dibuat/dicari
        // lewat users.admin_id (relasi eksplisit), bukan admins.id dan bukan nama.
        $stmtAdmin = $pdo->prepare("SELECT * FROM admins WHERE name = ? LIMIT 1");
        $stmtAdmin->execute([$username]);
        $admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

        $adminPasswordOk = false;
        if ($admin) {
            $storedAdminPassword = (string)$admin['password'];
            $adminPasswordOk = preg_match('/^\$2[aby]\$/', $storedAdminPassword) === 1
                ? password_verify($password, $storedAdminPassword)
                : hash_equals($storedAdminPassword, (string)$password);
        }

        if ($admin && $adminPasswordOk) {
            $userId = findOrCreateAdminApiIdentity($pdo, $admin);
            $token = null;
            if ($userId !== null) {
                $token = bin2hex(random_bytes(32));
                $stmtToken = $pdo->prepare('UPDATE users SET api_token = ? WHERE id = ?');
                $stmtToken->execute([$token, $userId]);
            }

            // Tidak ada employee_id palsu untuk admin.
            $employeeId = null;

            $_SESSION = array();
            $_SESSION['user_role'] = 'hr';
            $_SESSION['user_name'] = $admin['name'];
            $_SESSION['employee_id'] = null;
            $_SESSION['display_name'] = $admin['name'];
            $_SESSION['admin_name'] = $admin['name'];
            if ($userId !== null) {
                $_SESSION['user_id'] = $userId;
                $_SESSION['api_token'] = $token;
            }

            http_response_code(200);
            echo json_encode([
                "success" => true,
                "message" => $token === null
                    ? 'Login berhasil. Identitas API Admin HR memerlukan migrasi database.'
                    : 'Login berhasil',
                "data" => [
                    "token" => $token,
                    "employee_id" => null,
                    "user" => [
                        "user_id" => $userId,
                        "employee_id" => null,
                        "admin_id" => (int)$admin['id'],
                        "username" => $admin['name'],
                        "role" => "hr",
                        "nama_lengkap" => $admin['name']
                    ]
                ]
            ]);
            exit;
        }

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
