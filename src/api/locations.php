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

$user = Auth::requireApi();
$isSuperAdmin = Auth::isSuperAdmin();
$isHr = Auth::isHr();
if (!$isSuperAdmin && !$isHr) {
    Json::error('Anda tidak memiliki izin mengelola lokasi kantor', 403);
}

$method = $_SERVER['REQUEST_METHOD'];
$pdo = DBConnection::pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$master = new CompanyMasterService($pdo);
$data = in_array($method, array('POST', 'PUT', 'PATCH', 'DELETE'), true)
    ? Json::readRequest()
    : array();

function targetCompanyId($isSuperAdmin, $data, PDO $pdo)
{
    if (!$isSuperAdmin) {
        $companyId = Auth::companyId();
        if ($companyId === null) {
            Json::error('company_id tidak tersedia untuk akun ini', 403);
        }
        return $companyId;
    }
    $companyId = isset($data['company_id'])
        ? (int)$data['company_id']
        : (isset($_GET['company_id']) ? (int)$_GET['company_id'] : 0);
    if ($companyId <= 0) {
        Json::error('company_id wajib diisi untuk Super Admin', 422);
    }
    $stmt = $pdo->prepare('SELECT id FROM companies WHERE id = ? LIMIT 1');
    $stmt->execute(array($companyId));
    if (!$stmt->fetch()) {
        Json::error('Perusahaan tidak ditemukan', 404);
    }
    return $companyId;
}

function locationPayload($data)
{
    $name = isset($data['name'])
        ? trim((string)$data['name'])
        : (isset($data['nama_lokasi']) ? trim((string)$data['nama_lokasi']) : '');
    $latitude = isset($data['latitude']) ? $data['latitude'] : '';
    $longitude = isset($data['longitude']) ? $data['longitude'] : '';
    $radius = isset($data['radius'])
        ? $data['radius']
        : (isset($data['radius_meter']) ? $data['radius_meter'] : '');

    if ($name === '' || strlen($name) > 100) {
        Json::error('name/nama_lokasi wajib diisi (maksimal 100 karakter)', 422);
    }
    if (!is_numeric($latitude) || (float)$latitude < -90 || (float)$latitude > 90) {
        Json::error('Latitude tidak valid', 422);
    }
    if (!is_numeric($longitude) || (float)$longitude < -180 || (float)$longitude > 180) {
        Json::error('Longitude tidak valid', 422);
    }
    if (!is_numeric($radius) || (float)$radius <= 0) {
        Json::error('Radius tidak valid', 422);
    }
    return array($name, $latitude, $longitude, $radius);
}

try {
    if ($method === 'GET') {
        $companyId = targetCompanyId($isSuperAdmin, $data, $pdo);
        $rows = array();
        foreach ($master->officeLocations($companyId) as $row) {
            $rows[] = $master->formatLocation($row);
        }
        Json::ok('OK', array('data' => $rows, 'company_id' => $companyId));
    }

    if ($method === 'POST') {
        $companyId = targetCompanyId($isSuperAdmin, $data, $pdo);
        list($name, $latitude, $longitude, $radius) = locationPayload($data);
        $id = $master->addOfficeLocation($companyId, $name, $latitude, $longitude, $radius);
        Json::out(array(
            'success' => true,
            'message' => 'Lokasi kantor berhasil ditambahkan',
            'data' => $master->formatLocation($master->officeLocation($id))
        ), 201);
    }

    if ($method === 'PUT' || $method === 'PATCH') {
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        if ($id <= 0) {
            Json::error('id lokasi wajib diisi', 422);
        }
        $scope = $isSuperAdmin ? null : Auth::companyId();
        if (!$master->officeLocation($id, $scope)) {
            Json::error('Lokasi kantor tidak ditemukan', 404);
        }
        list($name, $latitude, $longitude, $radius) = locationPayload($data);
        $master->updateOfficeLocation($id, $name, $latitude, $longitude, $radius, $scope);
        Json::ok('Lokasi kantor berhasil diperbarui', array(
            'data' => $master->formatLocation($master->officeLocation($id))
        ));
    }

    if ($method === 'DELETE') {
        $id = isset($data['id'])
            ? (int)$data['id']
            : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
        if ($id <= 0) {
            Json::error('id lokasi wajib diisi', 422);
        }
        $scope = $isSuperAdmin ? null : Auth::companyId();
        if (!$master->officeLocation($id, $scope)) {
            Json::error('Lokasi kantor tidak ditemukan', 404);
        }
        $master->removeOfficeLocation($id, $scope);
        Json::ok('Lokasi kantor berhasil dihapus');
    }
} catch (PDOException $e) {
    Json::error('Gagal mengelola lokasi kantor', 500);
}

Json::error('Method tidak diizinkan', 405);
