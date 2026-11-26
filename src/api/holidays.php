<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../service/HolidayService.php';

$user = Auth::requireApi();
$method = $_SERVER['REQUEST_METHOD'];
$service = new HolidayService();
$isSuperAdmin = Auth::isSuperAdmin();
$companyId = Auth::companyId();

try {
    if ($method === 'GET') {
        $year = isset($_GET['year']) && $_GET['year'] !== ''
            ? (int)$_GET['year'] : null;
        Json::ok('OK', array('data' => $service->listing($companyId, $year, $isSuperAdmin)));
    }

    if ($method === 'POST') {
        if ($isSuperAdmin) {
            $targetCompanyId = null;
        } elseif (Auth::isHr()) {
            $targetCompanyId = $companyId;
        } else {
            Json::error('Anda tidak memiliki izin menambah hari libur', 403);
        }
        $data = Json::readRequest();
        $holidayDate = isset($data['holiday_date']) ? trim((string)$data['holiday_date']) : '';
        $title = isset($data['title']) ? trim((string)$data['title']) : '';
        $description = isset($data['description']) ? trim((string)$data['description']) : '';
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $holidayDate, $m)
            || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
            Json::error('Format holiday_date tidak valid (YYYY-MM-DD)', 422);
        }
        if ($title === '' || strlen($title) > 150) {
            Json::error('title wajib diisi (maksimal 150 karakter)', 422);
        }
        $id = $service->create($targetCompanyId, $holidayDate, $title, $description, (int)$user['id']);
        Json::ok('Hari libur berhasil disimpan', array('data' => array('id' => $id)));
    }

    if ($method === 'DELETE') {
        if (!$isSuperAdmin && !Auth::isHr()) {
            Json::error('Anda tidak memiliki izin menghapus hari libur', 403);
        }
        $data = Json::readRequest();
        $id = isset($data['id']) ? (int)$data['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
        if ($id <= 0) {
            Json::error('id hari libur wajib diisi', 400);
        }
        $service->remove($id, $companyId, $isSuperAdmin);
        Json::ok('Hari libur berhasil dihapus');
    }
} catch (RuntimeException $e) {
    $code = (int)$e->getCode();
    Json::error($e->getMessage(), in_array($code, array(403, 404), true) ? $code : 422);
} catch (PDOException $e) {
    Json::error('Gagal memproses hari libur', 500);
}

Json::error('Method tidak diizinkan', 405);
