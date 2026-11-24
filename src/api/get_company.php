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

try {
    $pdo = DBConnection::pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $check = $pdo->query("SHOW TABLES LIKE 'company_profile'");
    if (!$check || !$check->fetch()) {
        $pdo->exec("CREATE TABLE company_profile (
            id INT(11) NOT NULL AUTO_INCREMENT,
            nama_perusahaan VARCHAR(150) NOT NULL,
            kode_perusahaan VARCHAR(50) NOT NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    $stmt = $pdo->query("SELECT nama_perusahaan, kode_perusahaan FROM company_profile ORDER BY id LIMIT 1");
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($data)) {
        $data = array(
            'nama_perusahaan' => 'PT Contoh Maju Sejahtera',
            'kode_perusahaan' => 'CMJ-2026',
        );
    }
    Json::ok('OK', array('data' => $data));
} catch (PDOException $e) {
    Json::error('Gagal memuat profil perusahaan', 500);
}
