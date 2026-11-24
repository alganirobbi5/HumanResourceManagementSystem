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

$user = Auth::requireApi();

$db = new DBConnection();
$rows = $db->selectAll(
    'SELECT id, first_name, last_name, user_name AS nik, department_id, designation_id
     FROM employees
     WHERE status = 1
     ORDER BY first_name, last_name'
);

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
