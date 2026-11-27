<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

require_once __DIR__ . '/../common/DBConnection.php';
require_once __DIR__ . '/../service/LeaveLeaderService.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * employee_id hanya boleh diambil dari relasi karyawan yang nyata.
 * Jangan pernah mengarang atau menebak ID karyawan.
 */
function genuineEmployeeId($pdo, $candidate, $companyId = null)
{
    if ($candidate === null || $candidate === '') {
        return null;
    }
    $sql = "SELECT id FROM employees WHERE id = ?";
    $params = array((int)$candidate);
    if ($companyId !== null) {
        $sql .= " AND company_id = ?";
        $params[] = (int)$companyId;
    }
    $stmt = $pdo->prepare($sql . " LIMIT 1");
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (int)$row['id'] : null;
}

/**
 * Cari atau buat identitas API untuk akun admin legacy.
 * Relasi eksplisit memakai users.admin_id, BUKAN nama dan BUKAN admins.id
 * sebagai employee_id. Tidak membuat data kepegawaian.
 */
function findOrCreateAdminApiIdentity($pdo, array $admin, $companyId = null)
{
    try {
        $col = $pdo->query("SHOW COLUMNS FROM users LIKE 'admin_id'");
        if (!$col || !$col->fetch()) {
            return null; // migrasi belum dijalankan
        }

        $adminId = (int)$admin['id'];
        $preferredUsername = '';
        foreach (array('username', 'name') as $field) {
            if (isset($admin[$field]) && trim((string)$admin[$field]) !== '') {
                $preferredUsername = trim((string)$admin[$field]);
                break;
            }
        }
        $pdo->beginTransaction();

        // Kunci baris/kunci unik agar login bersamaan tidak membuat dua identitas.
        $stmt = $pdo->prepare("SELECT id, username, is_active FROM users WHERE admin_id = ? FOR UPDATE");
        $stmt->execute([$adminId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            // Jangan menimpa username yang sudah valid; perbaiki hanya username
            // otomatis lama yang berawalan admin_ memakai nama asli dari admins.
            $currentUsername = (string)$existing['username'];
            if ($preferredUsername !== '' && $currentUsername !== $preferredUsername
                && stripos($currentUsername, 'admin_') === 0) {
                $taken = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
                $taken->execute(array($preferredUsername, (int)$existing['id']));
                if (!$taken->fetch()) {
                    $pdo->prepare('UPDATE users SET username = ? WHERE id = ?')
                        ->execute(array($preferredUsername, (int)$existing['id']));
                }
            }
            $pdo->commit();
            return (int)$existing['is_active'] === 1 ? (int)$existing['id'] : null;
        }

        $username = $preferredUsername;
        if ($username === '') {
            $username = 'admin_' . $adminId . '_' . bin2hex(random_bytes(6));
        } else {
            $taken = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
            $taken->execute(array($username));
            if ($taken->fetch()) {
                $username = 'admin_' . $adminId . '_' . bin2hex(random_bytes(6));
            }
        }
        // Kolom email sengaja tidak dipakai: skema VPS users tidak memilikinya.
        $columns = 'username, password_hash, role, employee_id, admin_id, company_id, is_active';
        $placeholders = '?, UUID(), "hr", NULL, ?, ?, 1';
        $values = array($username, $adminId, $companyId !== null ? (int)$companyId : null);
        if ((bool)$pdo->query("SHOW COLUMNS FROM users LIKE 'Password'")->fetch()) {
            $columns = 'username, password_hash, `Password`, role, employee_id, admin_id, company_id, is_active';
            $placeholders = '?, UUID(), ?, "hr", NULL, ?, ?, 1';
            array_splice($values, 2, 0, array(isset($admin['password']) ? (string)$admin['password'] : null));
        }
        $pdo->prepare(
            'INSERT INTO users (' . $columns . ') VALUES (' . $placeholders . ')'
        )->execute($values);

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

/**
 * Pastikan karyawan lama yang belum punya akun API bisa login lewat
 * employees.user_name + employees.password, lalu dibuatkan identitas users.
 * Kredensial diverifikasi dari employees, employee_id tidak pernah ditebak.
 */
function findOrCreateEmployeeApiIdentity($pdo, $username, $password, $companyId = null)
{
    try {
        $col = $pdo->query("SHOW COLUMNS FROM employees LIKE 'status'");
        $statusColumn = $col && $col->fetch() ? ', status' : '';
        $companyFilter = $companyId !== null ? ' AND company_id = ?' : '';

        $stmt = $pdo->prepare("SELECT id, user_name, password$statusColumn FROM employees WHERE user_name = ?$companyFilter LIMIT 1");
        $stmt->execute($companyId !== null ? array($username, (int)$companyId) : array($username));
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$employee) {
            return null;
        }
        if ($statusColumn !== '' && isset($employee['status']) && (int)$employee['status'] !== 1) {
            return null;
        }

        $storedPassword = (string)$employee['password'];
        $passwordOk = preg_match('/^\$2[aby]\$/', $storedPassword) === 1
            ? password_verify($password, $storedPassword)
            : hash_equals($storedPassword, (string)$password);
        if (!$passwordOk) {
            return null;
        }

        $existing = $pdo->prepare('SELECT id FROM users WHERE employee_id = ? LIMIT 1');
        $existing->execute([(int)$employee['id']]);
        if ($existing->fetch()) {
            return null;
        }

        $taken = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $taken->execute([$username]);
        if ($taken->fetch()) {
            return null;
        }

        $columns = 'employee_id, username, password_hash, role, company_id, is_active';
        $placeholders = "?, ?, ?, 'employee', ?, 1";
        $values = array(
            (int)$employee['id'],
            $username,
            password_hash($password, PASSWORD_BCRYPT),
            $companyId !== null ? (int)$companyId : null
        );
        if ((bool)$pdo->query("SHOW COLUMNS FROM users LIKE 'Password'")->fetch()) {
            $columns .= ', `Password`';
            $placeholders .= ', ?';
            $values[] = isset($employee['password']) ? (string)$employee['password'] : (string)$password;
        }
        $pdo->prepare(
            'INSERT INTO users (' . $columns . ') VALUES (' . $placeholders . ')'
        )->execute($values);
        return (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        return null;
    }
}

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->username) && !empty($data->password)) {
    $username = $data->username;
    $password = $data->password;
    $companyCode = isset($data->company_code) ? trim((string)$data->company_code) : '';

    try {
        $pdo = DBConnection::pdo();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $roleStmt = $pdo->prepare('SELECT role, company_id FROM users WHERE username = ? LIMIT 1');
        $roleStmt->execute([$username]);
        $existingUser = $roleStmt->fetch(PDO::FETCH_ASSOC);
        $isSuperLogin = $existingUser && $existingUser['role'] === 'super_admin';

        $company = null;
        if (!$isSuperLogin) {
            if ($companyCode === '') {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'company_code wajib diisi']);
                exit;
            }
            $companyStmt = $pdo->prepare(
                'SELECT id, company_code, company_name FROM companies WHERE company_code = ? AND status = 1 LIMIT 1'
            );
            $companyStmt->execute([$companyCode]);
            $company = $companyStmt->fetch(PDO::FETCH_ASSOC);
            if (!$company) {
                http_response_code(422);
                echo json_encode(['success' => false, 'message' => 'company_code tidak valid']);
                exit;
            }
            if ($existingUser && $existingUser['company_id'] !== null
                && (int)$existingUser['company_id'] !== (int)$company['id']) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Akun tidak sesuai dengan perusahaan']);
                exit;
            }
        }
        $companyId = $company ? (int)$company['id'] : null;

        if (!$isSuperLogin) {
            findOrCreateEmployeeApiIdentity($pdo, $username, $password, $companyId);
        }

        // 1. AKUN MODERN (tabel users)
        $stmt = $pdo->prepare("SELECT u.*, e.id AS employee_row_id, e.first_name, e.last_name
                               FROM users u
                               LEFT JOIN employees e ON e.id = u.employee_id
                               WHERE u.username = ? AND u.is_active = 1 LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $passwordOk = false;
        if ($user) {
            if (isset($user['Password']) && (string)$user['Password'] !== '') {
                $passwordOk = hash_equals((string)$user['Password'], (string)$password);
            }
            if (!$passwordOk && password_verify($password, (string)$user['password_hash'])) {
                $passwordOk = true;
            }
        }

        if ($user && $passwordOk) {
            $employeeId = genuineEmployeeId($pdo, $user['employee_id'], $isSuperLogin ? null : $companyId);
            $responseCompanyId = $user['company_id'] !== null ? (int)$user['company_id'] : null;
            $responseCompanyCode = $company ? $company['company_code'] : null;
            $responseCompanyName = $company ? $company['company_name'] : null;
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
            $_SESSION['company_id'] = $responseCompanyId;
            $_SESSION['company_code'] = $responseCompanyCode;
            $_SESSION['company_name'] = $responseCompanyName;
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
                        "company_id" => $responseCompanyId,
                        "company_code" => $responseCompanyCode,
                        "company_name" => $responseCompanyName,
                        "nama_lengkap" => $displayName,
                        "is_leave_leader" => (new LeaveLeaderService())->isLeader((int)$user['id'])
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
            $userId = findOrCreateAdminApiIdentity($pdo, $admin, $companyId);
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
            $_SESSION['company_id'] = $companyId;
            $_SESSION['company_code'] = $company ? $company['company_code'] : null;
            $_SESSION['company_name'] = $company ? $company['company_name'] : null;
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
                        "company_id" => $companyId,
                        "company_code" => $company ? $company['company_code'] : null,
                        "company_name" => $company ? $company['company_name'] : null,
                        "nama_lengkap" => $admin['name'],
                        "is_leave_leader" => (new LeaveLeaderService())->isLeader($userId)
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
