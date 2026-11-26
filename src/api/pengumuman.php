<?php

require_once __DIR__ . '/../common/DBConnection.php';
require_once __DIR__ . '/../common/Auth.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Endpoint baca pengumuman bersifat publik (siaran untuk seluruh karyawan).
// Identitas bersifat opsional: bila token valid, daftar difilter per company;
// bila token tidak ada/kedaluwarsa, tetap tampilkan pengumuman global agar
// halaman tidak gagal memuat hanya karena sesi lokal yang basi.
// Endpoint tulis (tambah/edit/hapus) tetap dilindungi HR di file masing-masing.

header('Cache-Control: no-store');

$user = Auth::identify();
$companyId = $user ? Auth::companyId() : null;
$isSuperAdmin = $user && Auth::isSuperAdmin();

try {
    $pdo = DBConnection::pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $fields = 'n.id, n.subject AS title, n.description AS message, n.notice_date AS created_at,
               COALESCE(c.company_name, "Semua Perusahaan") AS company_name';
    $col = $pdo->query("SHOW COLUMNS FROM notices LIKE 'attachment'");
    if ($col && $col->fetch()) {
        $fields .= ', n.attachment';
    }
    $sql = 'SELECT ' . $fields . ' FROM notices n
            LEFT JOIN companies c ON c.id = n.company_id';
    $params = array();
    if (!$isSuperAdmin) {
        if ($companyId !== null) {
            $sql .= ' WHERE (n.company_id = ? OR n.company_id IS NULL)';
            $params[] = (int)$companyId;
        } else {
            $sql .= ' WHERE n.company_id IS NULL';
        }
    }
    $sql .= ' ORDER BY n.id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "data" => $data
    ]);
    exit;
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Gagal memuat pengumuman"
    ]);
    exit;
}
?>
