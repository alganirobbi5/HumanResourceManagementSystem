<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../common/DBConnection.php';
require_once __DIR__ . '/../service/CompanyMasterService.php';
require_once __DIR__ . '/../service/EmployeeAccountService.php';

$user = Auth::requireApi();
$method = $_SERVER['REQUEST_METHOD'];
$companyId = Auth::companyId();
$db = new DBConnection();
$master = new CompanyMasterService($db->getPdo());

function employeesPathId()
{
    $path = isset($_SERVER['PATH_INFO']) ? trim((string)$_SERVER['PATH_INFO'], '/') : '';
    if ($path === '') {
        return 0;
    }
    $parts = explode('/', $path);
    $last = end($parts);
    return ctype_digit($last) ? (int)$last : 0;
}

function employeesMapRow($row)
{
    $isLeader = (int)$row['is_leader'] === 1;
    $userRole = strtolower(trim((string)$row['user_role']));
    if ($isLeader) {
        $role = 'leader';
    } elseif (in_array($userRole, array('hr', 'admin', 'super_admin'), true)) {
        $role = 'hr';
    } else {
        $role = 'employee';
    }
    return array(
        'user_id' => $row['user_id'] !== null ? (int)$row['user_id'] : null,
        'employee_id' => (int)$row['employee_id'],
        'id' => (int)$row['employee_id'],
        'username' => (string)$row['username'],
        'full_name' => (string)$row['full_name'],
        'name' => (string)$row['full_name'],
        'nik' => (string)$row['nik'],
        'email' => (string)$row['email'],
        'phone' => (string)$row['phone'],
        'no_hp' => (string)$row['phone'],
        'whatsapp' => (string)$row['phone'],
        'company_name' => (string)$row['company_name'],
        'company_code' => (string)$row['company_code'],
        'is_leader' => $isLeader ? 1 : 0,
        'role' => $role,
        'role_label' => $role,
        'department_id' => (int)$row['department_id'],
        'department_name' => (string)$row['department_name'],
        'designation_id' => (int)$row['designation_id'],
        'designation_name' => (string)$row['designation_name'],
        'is_active' => (int)$row['is_active']
    );
}

function employeesFetchRow($db, $employeeId, $companyId)
{
    $sql = 'SELECT u.id AS user_id, e.id AS employee_id, u.role AS user_role,
                   COALESCE(NULLIF(u.username, ""), e.user_name) AS username,
                   TRIM(CONCAT(e.first_name, " ", e.last_name)) AS full_name,
                   e.email AS email, e.mobile_no AS phone,
                   c.company_name, c.company_code,
                   CASE WHEN ll.id IS NOT NULL THEN 1 ELSE 0 END AS is_leader,
                   e.department_id, d.name AS department_name,
                   e.designation_id, dg.title AS designation_name,
                   COALESCE(u.is_active, e.status) AS is_active,
                   e.first_name, e.last_name, e.user_name AS nik
            FROM employees e
            LEFT JOIN users u ON u.employee_id = e.id
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN employee_designations dg ON dg.id = e.designation_id
            LEFT JOIN companies c ON c.id = COALESCE(e.company_id, u.company_id)
            LEFT JOIN leave_leaders ll ON ll.user_id = u.id
            WHERE e.id = ?';
    $params = array((int)$employeeId);
    if ($companyId !== null) {
        $sql .= ' AND e.company_id = ?';
        $params[] = (int)$companyId;
    }
    $row = $db->selectOne($sql . ' LIMIT 1', $params);
    return $row ? employeesMapRow($row) : null;
}

$data = in_array($method, array('POST', 'PUT', 'PATCH', 'DELETE'), true)
    ? Json::readRequest()
    : array();
$override = isset($data['_method']) ? strtoupper(trim((string)$data['_method'])) : '';
if ($method === 'POST' && in_array($override, array('PUT', 'PATCH'), true)) {
    $method = $override;
}
$action = isset($data['action']) ? strtolower(trim((string)$data['action'])) : '';
$employeeId = 0;
foreach (array('employee_id', 'id') as $idKey) {
    if (!empty($data[$idKey])) {
        $employeeId = (int)$data[$idKey];
        break;
    }
}
if ($employeeId <= 0 && !empty($data['user_id'])) {
    $userRow = $db->selectOne('SELECT employee_id FROM users WHERE id = ? LIMIT 1', array((int)$data['user_id']));
    if ($userRow && $userRow['employee_id'] !== null) {
        $employeeId = (int)$userRow['employee_id'];
    }
}
if ($employeeId <= 0) {
    $employeeId = employeesPathId();
}
if ($employeeId <= 0 && !empty($_GET['id'])) {
    $employeeId = (int)$_GET['id'];
}
if ($employeeId <= 0 && !empty($_GET['employee_id'])) {
    $employeeId = (int)$_GET['employee_id'];
}
$isUpdate = in_array($method, array('PUT', 'PATCH'), true)
    || ($method === 'POST' && ($action === 'update' || ($employeeId > 0 && $action !== 'create')));

if ($isUpdate) {
    if (!Auth::isHr() && !Auth::isSuperAdmin()) {
        Json::error('Anda tidak memiliki izin mengedit karyawan', 403);
    }
    if ($employeeId <= 0) {
        Json::error('employee_id wajib diisi', 422);
    }
    $existing = employeesFetchRow($db, $employeeId, $companyId);
    if (!$existing) {
        Json::error('Karyawan tidak ditemukan', 404);
    }
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
        'no_hp' => 'mobile_no',
        'whatsapp' => 'mobile_no',
        'number' => 'mobile_no',
        'gender' => 'gender',
        'department_id' => 'department_id',
        'designation_id' => 'designation_id',
        'is_active' => 'status',
        'status' => 'status',
        'password' => 'password'
    );
    $payload = array();
    foreach ($fieldMap as $source => $target) {
        if (array_key_exists($source, $data)) {
            $payload[$target] = $data[$source];
        }
    }
    if (!$payload) {
        Json::error('Tidak ada data yang diubah', 400);
    }
    try {
        $service = new EmployeeAccountService();
        $service->update($employeeId, $payload);
        $updated = employeesFetchRow($db, $employeeId, $companyId);
        Json::ok('Data karyawan berhasil diperbarui', array(
            'data' => $updated,
            'id' => $employeeId,
            'employee_id' => $employeeId
        ));
    } catch (InvalidArgumentException $e) {
        Json::error($e->getMessage(), (int)$e->getCode() === 404 ? 404 : 422);
    } catch (RuntimeException $e) {
        Json::error($e->getMessage(), (int)$e->getCode() === 409 ? 409 : 500);
    } catch (Exception $e) {
        Json::error('Gagal memperbarui data karyawan', 500);
    }
}

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
$sql = 'SELECT u.id AS user_id, e.id AS employee_id, u.role AS user_role,
               COALESCE(NULLIF(u.username, ""), e.user_name) AS username,
               TRIM(CONCAT(e.first_name, " ", e.last_name)) AS full_name,
               e.email AS email,
               e.mobile_no AS phone,
               c.company_name, c.company_code,
               CASE WHEN ll.id IS NOT NULL THEN 1 ELSE 0 END AS is_leader,
               e.department_id, d.name AS department_name,
               e.designation_id, dg.title AS designation_name,
               COALESCE(u.is_active, e.status) AS is_active,
               e.first_name, e.last_name, e.user_name AS nik
        FROM employees e
        LEFT JOIN users u ON u.employee_id = e.id
        LEFT JOIN departments d ON d.id = e.department_id
        LEFT JOIN employee_designations dg ON dg.id = e.designation_id
        LEFT JOIN companies c ON c.id = COALESCE(e.company_id, u.company_id)
        LEFT JOIN leave_leaders ll ON ll.user_id = u.id';
$params = array();
if ($companyId !== null) {
    $sql .= ' AND COALESCE(ll.company_id, u.company_id) = ?';
    $params[] = $companyId;
}
$sql .= ' WHERE 1 = 1';
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
    $data[] = employeesMapRow($row);
}

Json::ok('OK', array('data' => $data));
