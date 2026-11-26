<?php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../common/DBConnection.php';
require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../common/Config.php';

// Hanya HR yang boleh menghapus data absensi.
$hr = Auth::requireApiHr();
$companyId = Auth::companyId();

$data = json_decode(file_get_contents("php://input"));
$id = isset($data->id) ? (int)$data->id : 0;
$recordType = isset($data->record_type) ? strtolower(trim((string)$data->record_type)) : 'employee';
if ($id <= 0) {
    Json::error('id absensi wajib diisi', 400);
}
if ($recordType !== 'admin') {
    $recordType = 'employee';
}

try {
    $pdo = DBConnection::pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($recordType === 'admin') {
        if ($companyId !== null) {
            $adminCheck = $pdo->prepare('SELECT id FROM admin_attendances WHERE id = ? AND company_id = ? LIMIT 1');
            $adminCheck->execute(array($id, $companyId));
            if (!$adminCheck->fetch()) {
                Json::error('Data absensi HR tidak ditemukan', 404);
            }
        }
        require_once __DIR__ . '/../service/AdminAttendanceService.php';
        $service = new AdminAttendanceService();
        $paths = $service->selfiePaths($id);
        if (!$service->delete($id)) {
            Json::error('Data absensi HR tidak ditemukan', 404);
        }
        hapusFileSelfie($paths);
        Json::ok('Data absensi HR berhasil dihapus');
    }

    $columns = array('id');
    foreach (array('selfie_path', 'selfie_out_path') as $column) {
        $check = $pdo->query("SHOW COLUMNS FROM attendances LIKE '" . $column . "'");
        if ($check && $check->fetch()) {
            $columns[] = $column;
        }
    }
    $selectSql = 'SELECT ' . implode(', ', $columns) . ' FROM attendances WHERE id = ?';
    $selectParams = array($id);
    if ($companyId !== null) {
        $selectSql .= ' AND company_id = ?';
        $selectParams[] = (int)$companyId;
    }
    $stmt = $pdo->prepare($selectSql . ' LIMIT 1');
    $stmt->execute($selectParams);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        Json::error('Data absensi tidak ditemukan', 404);
    }

    $paths = array();
    foreach (array('selfie_path', 'selfie_out_path') as $column) {
        if (!empty($row[$column])) {
            $paths[] = $row[$column];
        }
    }

    $col = $pdo->query("SHOW COLUMNS FROM attendances LIKE 'deleted_at'");
    if ($col && $col->fetch()) {
        $clear = array('deleted_at = NOW()');
        foreach (array('selfie_path', 'selfie_out_path') as $column) {
            $check = $pdo->query("SHOW COLUMNS FROM attendances LIKE '" . $column . "'");
            if ($check && $check->fetch()) {
                $clear[] = $column . ' = NULL';
            }
        }
        $clearSql = "UPDATE attendances SET " . implode(', ', $clear) . " WHERE id = ? AND deleted_at IS NULL";
        $clearParams = array($id);
        if ($companyId !== null) {
            $clearSql .= ' AND company_id = ?';
            $clearParams[] = (int)$companyId;
        }
        $stmt = $pdo->prepare($clearSql);
        $stmt->execute($clearParams);
        if ($stmt->rowCount() === 0) {
            Json::error('Data absensi sudah dihapus', 404);
        }
    } else {
        $deleteSql = 'DELETE FROM attendances WHERE id = ?';
        $deleteParams = array($id);
        if ($companyId !== null) {
            $deleteSql .= ' AND company_id = ?';
            $deleteParams[] = (int)$companyId;
        }
        $stmt = $pdo->prepare($deleteSql);
        $stmt->execute($deleteParams);
    }

    hapusFileSelfie($paths);
    Json::ok('Data absensi berhasil dihapus');
} catch (PDOException $e) {
    Json::error('Gagal menghapus data absensi', 500);
} catch (Exception $e) {
    Json::error('Gagal menghapus data absensi', 500);
}

/**
 * Hapus file foto selfie dari storage. Path divalidasi agar tidak keluar
 * dari direktori Config::SELFIE_STORAGE_DIR.
 */
function hapusFileSelfie(array $paths)
{
    $base = realpath(Config::SELFIE_STORAGE_DIR);
    if ($base === false) {
        return;
    }
    foreach ($paths as $relative) {
        $relative = (string)$relative;
        if ($relative === '') {
            continue;
        }
        $target = realpath(Config::SELFIE_STORAGE_DIR . '/' .
            str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $relative));
        if ($target !== false && strpos($target, $base . DIRECTORY_SEPARATOR) === 0 && is_file($target)) {
            @unlink($target);
        }
    }
}
