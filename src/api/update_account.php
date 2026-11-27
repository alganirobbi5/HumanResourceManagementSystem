<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../common/DBConnection.php';

$user = Auth::requireApi();
$data = Json::readRequest();

$hasUsername = array_key_exists('username', $data);
$hasPassword = array_key_exists('password', $data);
$username = $hasUsername ? trim((string)$data['username']) : null;
$password = $hasPassword ? (string)$data['password'] : null;
$currentPassword = isset($data['current_password']) ? (string)$data['current_password'] : '';

$phone = null;
foreach (array('phone', 'mobile_no', 'contact', 'no_hp', 'whatsapp', 'number') as $phoneKey) {
    if (array_key_exists($phoneKey, $data)) {
        $phone = trim((string)$data[$phoneKey]);
        break;
    }
}

if ((!$hasUsername && !$hasPassword && $phone === null)
    || ($hasUsername && $username === '')
    || ($hasPassword && $password === '')) {
    Json::error('username, password, atau phone wajib diisi', 422);
}
if ($phone !== null && strlen($phone) > 15) {
    Json::error('Nomor HP maksimal 15 karakter', 422);
}
if ($phone !== null && !$user['employee_id']) {
    Json::error('Akun ini tidak terhubung ke data karyawan', 422);
}
if ($username !== null && strlen($username) > 50) {
    Json::error('Username maksimal 50 karakter', 422);
}
if ($username !== null && $user['employee_id'] && strlen($username) > 30) {
    Json::error('Username maksimal 30 karakter untuk akun karyawan', 422);
}

try {
    $pdo = DBConnection::pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($username !== null) {
        $taken = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
        $taken->execute(array($username, (int)$user['id']));
        if ($taken->fetch()) {
            Json::error('Username sudah digunakan', 409);
        }
        if ($user['employee_id']) {
            $employeeTaken = $pdo->prepare('SELECT id FROM employees WHERE user_name = ? AND id <> ? LIMIT 1');
            $employeeTaken->execute(array($username, (int)$user['employee_id']));
            if ($employeeTaken->fetch()) {
                Json::error('Username sudah digunakan', 409);
            }
        }
    }

    $newHash = null;
    if ($password !== null) {
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
        $stmt->execute(array((int)$user['id']));
        $hash = (string)$stmt->fetchColumn();
        if ($hash === '' || !password_verify($currentPassword, $hash)) {
            Json::error('Password saat ini tidak sesuai', 422);
        }
        $newHash = password_hash($password, PASSWORD_BCRYPT);
    }

    $updates = array();
    $params = array();
    if ($username !== null) {
        $updates[] = 'username = ?';
        $params[] = $username;
    }
    if ($newHash !== null) {
        $updates[] = 'password_hash = ?';
        $params[] = $newHash;
        if ((bool)$pdo->query("SHOW COLUMNS FROM users LIKE 'Password'")->fetch()) {
            $updates[] = '`Password` = ?';
            $params[] = $password;
        }
    }

    $pdo->beginTransaction();
    try {
        if ($updates) {
            $params[] = (int)$user['id'];
            $pdo->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?')->execute($params);
        }
        if ($username !== null && $user['employee_id']) {
            $pdo->prepare('UPDATE employees SET user_name = ? WHERE id = ?')
                ->execute(array($username, (int)$user['employee_id']));
        }
        if ($phone !== null && $user['employee_id']) {
            $phoneColumn = 'mobile_no';
            foreach (array('mobile_no', 'phone', 'contact', 'no_hp') as $candidate) {
                $column = $pdo->query("SHOW COLUMNS FROM employees LIKE '" . $candidate . "'");
                if ($column && $column->fetch()) {
                    $phoneColumn = $candidate;
                    break;
                }
            }
            $pdo->prepare('UPDATE employees SET ' . $phoneColumn . ' = ? WHERE id = ?')
                ->execute(array($phone, (int)$user['employee_id']));
        }
        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ((string)$e->getCode() === '23000') {
            Json::error('Username sudah digunakan', 409);
        }
        throw $e;
    }

    Json::ok('Akun berhasil diperbarui', array(
        'username' => $username !== null ? $username : $user['username'],
        'username_berubah' => $username !== null,
        'password_berubah' => $newHash !== null,
        'phone' => $phone !== null ? $phone : (isset($user['phone']) ? (string)$user['phone'] : ''),
        'phone_berubah' => $phone !== null,
    ));
} catch (PDOException $e) {
    Json::error('Gagal memperbarui akun', 500);
}
