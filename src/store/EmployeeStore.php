<?php

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../service/EmployeeAccountService.php';

Auth::requireHr();

$back = '../../views/employee/createEmployee.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $back);
    exit;
}

$data = Json::readRequest();
$payload = array(
    'first_name' => isset($data['firstName']) ? $data['firstName'] : (isset($data['first_name']) ? $data['first_name'] : ''),
    'last_name' => isset($data['lastName']) ? $data['lastName'] : (isset($data['last_name']) ? $data['last_name'] : ''),
    'username' => isset($data['username']) ? $data['username'] : '',
    'password' => isset($data['password']) ? $data['password'] : '',
    'email' => isset($data['confirm_email']) ? $data['confirm_email'] : (isset($data['email']) ? $data['email'] : ''),
    'mobile_no' => isset($data['number']) ? $data['number']
        : (isset($data['mobile_no']) ? $data['mobile_no']
        : (isset($data['phone']) ? $data['phone']
        : (isset($data['contact']) ? $data['contact']
        : (isset($data['no_hp']) ? $data['no_hp']
        : (isset($data['whatsapp']) ? $data['whatsapp'] : ''))))),
    'gender' => isset($data['gender']) ? $data['gender'] : 'L',
    'department_id' => isset($data['department']) ? $data['department'] : (isset($data['department_id']) ? $data['department_id'] : ''),
    'designation_id' => isset($data['designation']) ? $data['designation'] : (isset($data['designation_id']) ? $data['designation_id'] : ''),
    'company_id' => Auth::companyId(),
);

try {
    $service = new EmployeeAccountService();
    $service->create($payload);
    header('Location: ' . $back . '?saved=1');
} catch (Exception $e) {
    header('Location: ' . $back . '?error=' . urlencode($e->getMessage()));
}
exit;
