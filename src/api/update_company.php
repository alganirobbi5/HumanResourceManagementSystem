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

$user = Auth::requireApiHr();

$data = json_decode(file_get_contents("php://input"));
$nama = isset($data->nama_perusahaan) ? trim((string)$data->nama_perusahaan) : '';
$kode = isset($data->kode_perusahaan) ? trim((string)$data->kode_perusahaan) : '';

if ($nama === '' || $kode === '') {
    Json::error('Nama dan kode perusahaan wajib diisi', 400);
}

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

    $row = $pdo->query("SELECT id FROM company_profile ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $stmt = $pdo->prepare("UPDATE company_profile SET nama_perusahaan = ?, kode_perusahaan = ? WHERE id = ?");
        $stmt->execute(array($nama, $kode, (int)$row['id']));
    } else {
        $stmt = $pdo->prepare("INSERT INTO company_profile (nama_perusahaan, kode_perusahaan) VALUES (?, ?)");
        $stmt->execute(array($nama, $kode));
    }

    Json::ok('Profil perusahaan berhasil diubah', array(
        'data' => array(
            'nama_perusahaan' => $nama,
            'kode_perusahaan' => $kode,
        ),
    ));
} catch (PDOException $e) {
    Json::error('Gagal menyimpan profil perusahaan', 500);
}
