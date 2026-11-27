<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, PUT, PATCH, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../common/DBConnection.php';
require_once __DIR__ . '/../service/EmployeeAccountService.php';

Auth::requireApiHr();

$data = Json::readRequest();
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($data['action']) ? strtolower(trim((string)$data['action'])) : '';
$employeeId = isset($data['employee_id'])
    ? (int)$data['employee_id']
    : (isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0);
if ($employeeId <= 0) {
    Json::error('employee_id wajib diisi', 400);
}

try {
    $companyId = Auth::companyId();
    if ($companyId !== null) {
        $pdo = DBConnection::pdo();
        $check = $pdo->prepare('SELECT id FROM employees WHERE id = ? AND company_id = ? LIMIT 1');
        $check->execute(array($employeeId, $companyId));
        if (!$check->fetch()) {
            Json::error('Karyawan tidak ditemukan', 404);
        }
    }
    $service = new EmployeeAccountService();
    if ($action === 'deactivate') {
        $service->setActive($employeeId, 0);
        Json::ok('Karyawan dinonaktifkan dan akun login dinonaktifkan');
    }
    if ($method !== 'PUT' && $method !== 'PATCH' && $action !== 'update') {
        Json::error('Aksi tidak dikenal', 400);
    }

    foreach (array_keys($data) as $key) {
        $lowerKey = strtolower((string)$key);
        if (strpos($lowerKey, 'password') !== false || in_array($lowerKey, array('pass', 'pwd'), true)) {
            unset($data[$key]);
        }
    }

    $payload = array();
    $fieldMap = array(
        'full_name' => 'full_name',
        'first_name' => 'first_name',
        'last_name' => 'last_name',
        'username' => 'username',
        'user_name' => 'username',
        'email' => 'email',
        'phone' => 'mobile_no',
        'contact' => 'mobile_no',
        'mobile_no' => 'mobile_no',
        'gender' => 'gender',
        'department_id' => 'department_id',
        'designation_id' => 'designation_id',
        'is_active' => 'status',
        'status' => 'status'
    );
    foreach ($fieldMap as $source => $target) {
        if (array_key_exists($source, $data)) {
            $payload[$target] = $data[$source];
        }
    }
    if (!$payload) {
        Json::error('Tidak ada data yang diubah', 400);
    }
    $service->update($employeeId, $payload);
    Json::ok('Data karyawan dan akun login berhasil disinkronkan', array(
        'data' => array('employee_id' => $employeeId, 'password_berubah' => false)
    ));
} catch (InvalidArgumentException $e) {
    Json::error($e->getMessage(), (int)$e->getCode() === 404 ? 404 : 422);
} catch (RuntimeException $e) {
    Json::error($e->getMessage(), (int)$e->getCode() === 409 ? 409 : 500);
} catch (Exception $e) {
    Json::error('Gagal menyinkronkan data karyawan', 500);
}
