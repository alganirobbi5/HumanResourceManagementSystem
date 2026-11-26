<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../common/DBConnection.php';
require_once __DIR__ . '/../service/CompanyProfileService.php';
require_once __DIR__ . '/../service/CompanyMasterService.php';

Auth::requireApiSuperAdmin();

$method = $_SERVER['REQUEST_METHOD'];
$pdo = DBConnection::pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$profiles = new CompanyProfileService($pdo);
$master = new CompanyMasterService($pdo);

try {
    if ($method === 'GET') {
        $rows = $pdo->query(
            'SELECT u.id, u.username, u.role, u.company_id, u.is_active,
                    c.company_code,
                    COALESCE(NULLIF(cp.nama_perusahaan, ""), c.company_name) AS company_name,
                    e.first_name, e.last_name, e.email
             FROM users u
             LEFT JOIN companies c ON c.id = u.company_id
             LEFT JOIN company_profile cp ON cp.company_id = u.company_id
             LEFT JOIN employees e ON e.id = u.employee_id
             WHERE u.role = "hr"
             ORDER BY u.id DESC'
        )->fetchAll(PDO::FETCH_ASSOC);
        Json::ok('OK', array('data' => $rows));
    }

    if ($method === 'POST') {
        $data = Json::readRequest();
        $companyId = isset($data['company_id']) ? (int)$data['company_id'] : 0;
        $firstName = isset($data['first_name']) ? trim((string)$data['first_name']) : '';
        $lastName = isset($data['last_name']) ? trim((string)$data['last_name']) : '';
        $username = isset($data['user_name']) ? trim((string)$data['user_name']) : '';
        $email = isset($data['email']) ? trim((string)$data['email']) : '';
        $password = isset($data['password']) ? (string)$data['password'] : '';

        if ($companyId <= 0 || $firstName === '' || $lastName === '' || $username === ''
            || $email === '' || $password === '') {
            Json::error('company_id, first_name, last_name, user_name, email, dan password wajib diisi', 422);
        }
        if (strlen($firstName) > 30 || strlen($lastName) > 30 || strlen($username) > 30
            || strlen($email) > 30 || strlen($password) > 20) {
            Json::error('Panjang field melebihi batas kolom employees', 422);
        }
        $company = $pdo->prepare('SELECT id FROM companies WHERE id = ? AND status = 1 LIMIT 1');
        $company->execute(array($companyId));
        if (!$company->fetch()) {
            Json::error('Perusahaan tidak ditemukan atau tidak aktif', 422);
        }
        $userCheck = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $userCheck->execute(array($username));
        if ($userCheck->fetch()) {
            Json::error('Username sudah digunakan', 409);
        }
        $employeeCheck = $pdo->prepare('SELECT id FROM employees WHERE user_name = ? LIMIT 1');
        $employeeCheck->execute(array($username));
        if ($employeeCheck->fetch()) {
            Json::error('Username sudah digunakan', 409);
        }

        $department = $master->firstDepartment($companyId);
        $designation = $master->firstDesignation($companyId);

        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO employees
                 (first_name, last_name, user_name, password, gender, email, department_id,
                  designation_id, mobile_no, status, create_by, created_date, company_id)
                 VALUES (?, ?, ?, ?, "Male", ?, ?, ?, "", 1, ?, NOW(), ?)'
            )->execute(array(
                $firstName, $lastName, $username, $password, $email,
                (int)$department, (int)$designation, 'superadmin', $companyId
            ));
            $employeeId = (int)$pdo->lastInsertId();

            $pdo->prepare(
                'INSERT INTO users (employee_id, username, password_hash, role, company_id, is_active)
                 VALUES (?, ?, ?, "hr", ?, 1)'
            )->execute(array(
                $employeeId,
                $username,
                password_hash($password, PASSWORD_BCRYPT),
                $companyId
            ));
            $userId = (int)$pdo->lastInsertId();
            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        Json::ok('Akun HR berhasil ditambahkan', array('data' => array(
            'user_id' => $userId,
            'employee_id' => $employeeId,
            'company_id' => $companyId
        )));
    }
} catch (PDOException $e) {
    Json::error('Gagal mengelola akun HR', 500);
}

Json::error('Method tidak diizinkan', 405);
