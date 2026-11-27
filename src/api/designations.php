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
require_once __DIR__ . '/../service/CompanyMasterService.php';

$user = Auth::requireApi();
$method = $_SERVER['REQUEST_METHOD'];
$companyId = Auth::companyId();
$master = new CompanyMasterService(DBConnection::pdo());

if ($method === 'GET') {
    $data = array();
    foreach ($master->designations($companyId) as $row) {
        $data[] = array('id' => (int)$row['id'], 'title' => (string)$row['title'], 'name' => (string)$row['name']);
    }
    Json::ok('OK', array('data' => $data, 'designations' => $data));
}

if ($method === 'POST') {
    Auth::requireApiHr();
    $data = Json::readRequest();
    $title = isset($data['title']) ? $data['title'] : (isset($data['name']) ? $data['name'] : '');
    try {
        $id = $master->addDesignation($companyId, $title, isset($user['username']) ? $user['username'] : null);
        Json::out(array(
            'success' => true,
            'message' => 'Jabatan berhasil ditambahkan',
            'data' => array('id' => $id, 'title' => trim((string)$title))
        ), 201);
    } catch (InvalidArgumentException $e) {
        Json::error($e->getMessage(), 422);
    } catch (RuntimeException $e) {
        Json::error($e->getMessage(), (int)$e->getCode() === 409 ? 409 : 500);
    } catch (Exception $e) {
        Json::error('Gagal menambahkan jabatan', 500);
    }
}

Json::error('Method tidak diizinkan', 405);
