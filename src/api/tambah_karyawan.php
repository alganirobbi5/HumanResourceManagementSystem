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
require_once __DIR__ . '/../service/EmployeeAccountService.php';

$hr = Auth::requireApiHr();

$data = Json::readRequest();
$username = isset($data['user_name']) ? $data['user_name'] : (isset($data['username']) ? $data['username'] : '');
$payload = array(
    'first_name' => isset($data['first_name']) ? $data['first_name'] : (isset($data['firstName']) ? $data['firstName'] : ''),
    'last_name' => isset($data['last_name']) ? $data['last_name'] : (isset($data['lastName']) ? $data['lastName'] : ''),
    'user_name' => $username,
    'username' => $username,
    'password' => isset($data['password']) ? $data['password'] : '',
    'email' => isset($data['email']) ? $data['email'] : (isset($data['confirm_email']) ? $data['confirm_email'] : ''),
    'mobile_no' => isset($data['mobile_no']) ? $data['mobile_no'] : (isset($data['number']) ? $data['number'] : ''),
    'gender' => isset($data['gender']) ? $data['gender'] : '',
    'department_id' => isset($data['department_id']) ? $data['department_id'] : (isset($data['department']) ? $data['department'] : 0),
    'designation_id' => isset($data['designation_id']) ? $data['designation_id'] : (isset($data['designation']) ? $data['designation'] : 0),
    'status' => isset($data['status']) ? $data['status'] : 1,
    'create_by' => isset($hr['username']) ? $hr['username'] : null,
);

try {
    $service = new EmployeeAccountService();
    $employeeId = $service->create($payload);
    Json::out(array(
        'success' => true,
        'message' => 'Karyawan berhasil ditambahkan',
        'data' => array('employee_id' => $employeeId)
    ), 201);
} catch (InvalidArgumentException $e) {
    Json::error($e->getMessage(), 422);
} catch (RuntimeException $e) {
    Json::error($e->getMessage(), (int)$e->getCode() === 409 ? 409 : 500);
} catch (Exception $e) {
    Json::error('Gagal menambahkan karyawan', 500);
}
