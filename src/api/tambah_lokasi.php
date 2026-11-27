<?php

require_once __DIR__ . '/../common/DBConnection.php';
require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../service/CompanyMasterService.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$user = Auth::requireApi();
$data = Json::readRequest();

$name = isset($data['name'])
    ? trim((string)$data['name'])
    : (isset($data['nama_lokasi']) ? trim((string)$data['nama_lokasi']) : '');
$latitude = isset($data['latitude']) ? $data['latitude'] : '';
$longitude = isset($data['longitude']) ? $data['longitude'] : '';
$radius = isset($data['radius'])
    ? $data['radius']
    : (isset($data['radius_meter']) ? $data['radius_meter'] : '');

if ($name === '' || !is_numeric($latitude) || !is_numeric($longitude)
    || !is_numeric($radius) || (float)$radius <= 0) {
    http_response_code(400);
    echo json_encode(array('status' => 'error', 'message' => 'Data tidak lengkap'));
    exit;
}

try {
    $pdo = DBConnection::pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if (Auth::isSuperAdmin()) {
        $companyId = isset($data['company_id']) ? (int)$data['company_id'] : 0;
        if ($companyId <= 0) {
            http_response_code(422);
            echo json_encode(array('status' => 'error', 'message' => 'company_id wajib diisi'));
            exit;
        }
        $check = $pdo->prepare('SELECT id FROM companies WHERE id = ? LIMIT 1');
        $check->execute(array($companyId));
        if (!$check->fetch()) {
            http_response_code(404);
            echo json_encode(array('status' => 'error', 'message' => 'Perusahaan tidak ditemukan'));
            exit;
        }
    } else {
        $companyId = Auth::companyId();
        if ($companyId === null) {
            http_response_code(403);
            echo json_encode(array('status' => 'error', 'message' => 'company_id tidak tersedia'));
            exit;
        }
    }
    $master = new CompanyMasterService($pdo);
    $id = $master->addOfficeLocation($companyId, $name, $latitude, $longitude, $radius);

    http_response_code(201);
    echo json_encode(array(
        'status' => 'success',
        'message' => 'Lokasi berhasil ditambahkan',
        'id' => $id,
        'company_id' => $companyId
    ));
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(array('status' => 'error', 'message' => 'Gagal menyimpan lokasi'));
}
