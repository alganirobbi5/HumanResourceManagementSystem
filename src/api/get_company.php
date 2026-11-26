<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../common/DBConnection.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../service/CompanyProfileService.php';

try {
    $pdo = DBConnection::pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $companyId = Auth::companyId();
    if ($companyId === null) {
        $companyId = 1;
    }
    $profiles = new CompanyProfileService($pdo);
    $data = $profiles->rowForCompany($companyId);
    if (!is_array($data)) {
        $stmt = $pdo->query("SELECT nama_perusahaan, kode_perusahaan FROM company_profile ORDER BY id LIMIT 1");
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if (!is_array($data)) {
        $data = array(
            'nama_perusahaan' => 'PT Contoh Maju Sejahtera',
            'kode_perusahaan' => 'CMJ-2026',
        );
    }
    if (!isset($data['alamat'])) {
        $data['alamat'] = null;
    }
    Json::ok('OK', array('data' => $data));
} catch (PDOException $e) {
    Json::error('Gagal memuat profil perusahaan', 500);
}
