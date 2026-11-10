<?php

require_once __DIR__ . '/../common/Auth.php';

Auth::start();

if (!isset($_POST['login'])) {
    header("Location:../../index.php");
    exit;
}

$user_name = trim($_POST['userName']);
$user_password = trim($_POST['password']);

$dbConnection = new DBConnection();

// Jalur baru: akun karyawan / HR pada tabel users (password ter-hash).
$user = $dbConnection->selectOne(
    'SELECT u.*, e.first_name, e.last_name
     FROM users u
     LEFT JOIN employees e ON e.id = u.employee_id
     WHERE u.username = ? AND u.is_active = 1
     LIMIT 1',
    array($user_name)
);

if ($user && password_verify($user_password, $user['password_hash'])) {
    $user['display_name'] = trim($user['first_name'] . ' ' . $user['last_name']) !== ''
        ? trim($user['first_name'] . ' ' . $user['last_name'])
        : $user['username'];

    Auth::login($user);

    if ($user['role'] === 'hr') {
        header("Location:../../views/admin/dashboard.php");
    } else {
        header("Location:../../views/attendance/absensiSaya.php");
    }
    exit;
}

// Jalur lama: tabel admins (perilaku login sebelumnya).
$restlt = $dbConnection->getAll('SELECT * FROM admins');

$admin = "";
foreach ($restlt as $row) {
    $admin = $row;
}

if ($user_name == $admin['name'] && $user_password == $admin['password']) {
    $_SESSION['admin_name'] = $admin['name'];
    $_SESSION['admin_password'] = $admin['password'];
    header("Location:../../views/admin/dashboard.php");
} else {
    header("Location:../../index.php?msg=User name or password is invalid");
}

class Login
{

}
