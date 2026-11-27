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
require_once __DIR__ . '/../service/CompanyProfileService.php';

$user = Auth::requireApiHr();

$data = json_decode(file_get_contents("php://input"));
$nama = isset($data->nama_perusahaan) ? trim((string)$data->nama_perusahaan) : '';
$kode = isset($data->kode_perusahaan) ? trim((string)$data->kode_perusahaan) : '';
$logo = isset($data->logo) ? trim((string)$data->logo) : null;
if ($logo !== null && !preg_match('/^data:image\//', $logo)) {
    $logo = null;
}

if ($nama === '' || $kode === '') {
    Json::error('Nama dan kode perusahaan wajib diisi', 400);
}

try {
    $pdo = DBConnection::pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $companyId = Auth::companyId();
    if ($companyId === null) {
        $companyId = 1;
    }
    $profiles = new CompanyProfileService($pdo);
    $profiles->upsert($companyId, $nama, $kode, null, $logo);
    $profiles->syncCompany($companyId, $nama, $kode);
    $tersimpan = $profiles->rowForCompany($companyId);

    Json::ok('Profil perusahaan berhasil diubah', array(
        'data' => array(
            'nama_perusahaan' => $nama,
            'kode_perusahaan' => $kode,
            'logo' => isset($tersimpan['logo']) ? $tersimpan['logo'] : null,
        ),
    ));
} catch (PDOException $e) {
    Json::error('Gagal menyimpan profil perusahaan', 500);
}
