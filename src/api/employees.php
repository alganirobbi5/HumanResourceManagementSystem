<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../common/DBConnection.php';
require_once __DIR__ . '/../service/CompanyMasterService.php';

$user = Auth::requireApi();
$method = $_SERVER['REQUEST_METHOD'];
$companyId = Auth::companyId();
$db = new DBConnection();
$master = new CompanyMasterService($db->getPdo());

if (isset($_GET['meta']) && (string)$_GET['meta'] === '1') {
    $departments = array();
    foreach ($master->departments($companyId) as $row) {
        $departments[] = array('id' => (int)$row['id'], 'name' => (string)$row['name']);
    }
    $designations = array();
    foreach ($master->designations($companyId) as $row) {
        $designations[] = array(
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'title' => (string)$row['title']
        );
    }
    Json::ok('OK', array(
        'data' => array('departments' => $departments, 'designations' => $designations),
        'departments' => $departments,
        'designations' => $designations
    ));
}

if ($method === 'POST') {
    Auth::requireApiHr();
    $data = Json::readRequest();
    $kind = isset($data['type'])
        ? $data['type']
        : (isset($data['master']) ? $data['master'] : (isset($data['kind']) ? $data['kind'] : ''));
    $kind = strtolower(trim((string)$kind));
    $isDesignation = in_array($kind, array('designation', 'designations', 'jabatan'), true)
        || ($kind === '' && array_key_exists('title', $data) && !array_key_exists('name', $data));
    try {
        if ($isDesignation) {
            $title = isset($data['title']) ? $data['title'] : (isset($data['name']) ? $data['name'] : '');
            $id = $master->addDesignation($companyId, $title, isset($user['username']) ? $user['username'] : null);
            Json::out(array(
                'success' => true,
                'message' => 'Jabatan berhasil ditambahkan',
                'data' => array('id' => $id, 'title' => trim((string)$title))
            ), 201);
        }
        $name = isset($data['name']) ? $data['name'] : (isset($data['department_name']) ? $data['department_name'] : '');
        $id = $master->addDepartment($companyId, $name, isset($user['username']) ? $user['username'] : null);
        Json::out(array(
            'success' => true,
            'message' => 'Departemen berhasil ditambahkan',
            'data' => array('id' => $id, 'name' => trim((string)$name))
        ), 201);
    } catch (InvalidArgumentException $e) {
        Json::error($e->getMessage(), 422);
    } catch (RuntimeException $e) {
        Json::error($e->getMessage(), (int)$e->getCode() === 409 ? 409 : 500);
    } catch (Exception $e) {
        Json::error('Gagal menambahkan data master', 500);
    }
}

$isHr = Auth::isHr();
$sql = 'SELECT u.id AS user_id, e.id AS employee_id,
               COALESCE(NULLIF(u.username, ""), e.user_name) AS username,
               TRIM(CONCAT(e.first_name, " ", e.last_name)) AS full_name,
               COALESCE(NULLIF(u.email, ""), e.email) AS email,
               e.mobile_no AS phone,
               e.department_id, d.name AS department_name,
               e.designation_id, dg.title AS designation_name,
               COALESCE(u.is_active, e.status) AS is_active,
               e.first_name, e.last_name, e.user_name AS nik
        FROM employees e
        LEFT JOIN users u ON u.employee_id = e.id
        LEFT JOIN departments d ON d.id = e.department_id
        LEFT JOIN employee_designations dg ON dg.id = e.designation_id
        WHERE 1 = 1';
$params = array();
if ($companyId !== null) {
    $sql .= ' AND e.company_id = ?';
    $params[] = $companyId;
}
if (!$isHr) {
    $sql .= ' AND e.status = 1';
    if ((int)$user['employee_id'] > 0) {
        $sql .= ' AND e.id <> ?';
        $params[] = (int)$user['employee_id'];
    }
}
$sql .= ' ORDER BY full_name, e.id';
$rows = $db->selectAll($sql, $params);

$data = array();
foreach ($rows as $row) {
    $data[] = array(
        'user_id' => $row['user_id'] !== null ? (int)$row['user_id'] : null,
        'employee_id' => (int)$row['employee_id'],
        'id' => (int)$row['employee_id'],
        'username' => (string)$row['username'],
        'full_name' => (string)$row['full_name'],
        'name' => (string)$row['full_name'],
        'nik' => (string)$row['nik'],
        'email' => (string)$row['email'],
        'phone' => (string)$row['phone'],
        'department_id' => (int)$row['department_id'],
        'department_name' => (string)$row['department_name'],
        'designation_id' => (int)$row['designation_id'],
        'designation_name' => (string)$row['designation_name'],
        'is_active' => (int)$row['is_active']
    );
}

Json::ok('OK', array('data' => $data));
