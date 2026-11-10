<?php

require_once __DIR__ . '/../common/Auth.php';

Auth::start();

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if ($userId > 0) {
    try {
        $dbConnection = new DBConnection();
        $dbConnection->run('UPDATE users SET api_token = NULL WHERE id = ?', array($userId));
    } catch (Exception $e) {
        // abaikan, sesi tetap dihancurkan
    }
}

unset($_SESSION['admin_name']);
unset($_SESSION['password']);
unset($_SESSION['admin_password']);
unset($_SESSION['user_id']);
unset($_SESSION['user_role']);
unset($_SESSION['user_name']);
unset($_SESSION['employee_id']);
unset($_SESSION['display_name']);
unset($_SESSION['api_token']);

//unset($_SESSION);

session_destroy();

header("Location:../../index.php?");

class Logout
{

}
