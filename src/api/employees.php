<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../service/CompanyMasterService.php';

$user = Auth::requireApi();

$db = new DBConnection();

if (isset($_GET['meta']) && (string)$_GET['meta'] === '1') {
    $master = new CompanyMasterService($db->getPdo());
    $companyId = Auth::companyId();
    $departments = array();
    foreach ($master->departments($companyId) as $row) {
        $departments[] = array('id' => (int)$row['id'], 'name' => (string)$row['name']);
    }
    $designations = array();
    foreach ($master->designations($companyId) as $row) {
        $designations[] = array('id' => (int)$row['id'], 'name' => (string)$row['name']);
    }
    Json::ok('OK', array(
        'data' => array('departments' => $departments, 'designations' => $designations),
        'departments' => $departments,
        'designations' => $designations
    ));
}

$companyId = Auth::companyId();
$employeeSql = 'SELECT id, first_name, last_name, user_name AS nik, department_id, designation_id
                FROM employees WHERE status = 1';
$employeeParams = array();
if ($companyId !== null) {
    $employeeSql .= ' AND company_id = ?';
    $employeeParams[] = $companyId;
}
$employeeSql .= ' ORDER BY first_name, last_name';
$rows = $db->selectAll($employeeSql, $employeeParams);

$data = array();
foreach ($rows as $row) {
    if ((int)$row['id'] === (int)$user['employee_id']) {
        continue; // diri sendiri tidak boleh jadi pengganti
    }
    $data[] = array(
        'id' => (int)$row['id'],
        'name' => trim($row['first_name'] . ' ' . $row['last_name']),
        'nik' => $row['nik']
    );
}

Json::ok('OK', array('data' => $data));
