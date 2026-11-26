<?php

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../common/Config.php';

$user = Auth::requireApi();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$companyId = Auth::companyId();
if (!$id) {
    Json::error('Absensi tidak valid', 422);
}

$type = isset($_GET['type']) ? strtolower(trim((string)$_GET['type'])) : 'in';
if ($type !== 'in' && $type !== 'out') {
    $type = 'in';
}
$scope = isset($_GET['scope']) ? strtolower(trim((string)$_GET['scope'])) : 'employee';
if ($scope !== 'admin') {
    $scope = 'employee';
}

$db = new DBConnection();

if ($scope === 'admin') {
    if (!isset($user['role']) || $user['role'] !== 'hr') {
        Json::error('Anda tidak berwenang membuka foto ini', 403);
    }
    $adminId = Auth::adminId();
    if ($adminId === null) {
        Json::error('Identitas Admin HR tidak tersedia', 403);
    }

    $pathColumn = $type === 'out' ? 'selfie_pulang' : 'selfie_masuk';
    $mimeColumn = $type === 'out' ? 'selfie_pulang_mime' : 'selfie_masuk_mime';
    if (!$db->selectOne("SHOW COLUMNS FROM admin_attendances LIKE '" . $pathColumn . "'")) {
        Json::error('Foto absensi tidak ditemukan', 404);
    }
    $mimeSelect = '';
    if ($db->selectOne("SHOW COLUMNS FROM admin_attendances LIKE '" . $mimeColumn . "'")) {
        $mimeSelect = ', ' . $mimeColumn . ' AS selfie_mime_type';
    }
    $adminSql = 'SELECT id, admin_id, ' . $pathColumn . ' AS selfie_path' . $mimeSelect . '
                 FROM admin_attendances WHERE id = ?';
    $adminParams = array($id);
    if ($companyId !== null) {
        $adminSql .= ' AND company_id = ?';
        $adminParams[] = (int)$companyId;
    }
    $row = $db->selectOne($adminSql, $adminParams);
    if (!$row || empty($row['selfie_path'])) {
        Json::error('Foto absensi tidak ditemukan', 404);
    }
    if ((int)$row['admin_id'] !== (int)$adminId) {
        Json::error('Anda tidak berwenang membuka foto ini', 403);
    }
} else {
    $pathColumn = $type === 'out' ? 'selfie_out_path' : 'selfie_path';
    $mimeColumn = $type === 'out' ? 'selfie_out_mime_type' : 'selfie_mime_type';
    if (!$db->selectOne("SHOW COLUMNS FROM attendances LIKE '" . $pathColumn . "'")) {
        Json::error('Foto absensi tidak ditemukan', 404);
    }
    $mimeSelect = '';
    if ($db->selectOne("SHOW COLUMNS FROM attendances LIKE '" . $mimeColumn . "'")) {
        $mimeSelect = ', ' . $mimeColumn . ' AS selfie_mime_type';
    }
    $filterDeleted = '';
    $colDeleted = $db->selectOne("SHOW COLUMNS FROM attendances LIKE 'deleted_at'");
    if ($colDeleted) {
        $filterDeleted = ' AND deleted_at IS NULL';
    }
    $employeeSql = 'SELECT id, employee_id, company_id, ' . $pathColumn . ' AS selfie_path' . $mimeSelect . '
                    FROM attendances WHERE id = ?' . $filterDeleted;
    $employeeParams = array($id);
    if ($companyId !== null) {
        $employeeSql .= ' AND company_id = ?';
        $employeeParams[] = (int)$companyId;
    }
    $row = $db->selectOne($employeeSql, $employeeParams);
    if (!$row || empty($row['selfie_path'])) {
        Json::error('Foto absensi tidak ditemukan', 404);
    }
    $isOwner = $row['employee_id'] == $user['employee_id'];
    $isSameCompany = $companyId === null || (isset($row['company_id']) && (int)$row['company_id'] === $companyId);
    if (!$isOwner && ($user['role'] !== 'hr' || !$isSameCompany)) {
        Json::error('Anda tidak berwenang membuka foto ini', 403);
    }
}

$base = realpath(Config::SELFIE_STORAGE_DIR);
$path = realpath(Config::SELFIE_STORAGE_DIR . '/' . str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $row['selfie_path']));
if ($base === false || $path === false || strpos($path, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) {
    Json::error('File foto tidak ditemukan', 404);
}

$mimeType = !empty($row['selfie_mime_type']) ? $row['selfie_mime_type'] : 'image/jpeg';
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
readfile($path);
exit;
