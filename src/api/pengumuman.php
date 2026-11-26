<?php

require_once __DIR__ . '/../common/DBConnection.php';
require_once __DIR__ . '/../common/Auth.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Endpoint baca pengumuman bersifat publik (informasi siaran untuk seluruh
// karyawan). Flutter membaca daftar pengumuman tanpa Bearer Token, sehingga
// autentikasi tidak diterapkan di sini agar tidak memutus fitur yang sudah
// berjalan. Endpoint tulis (tambah/edit/hapus) telah dilindungi HR.

$user = Auth::requireApi();
$companyId = Auth::companyId();

try {
    $pdo = DBConnection::pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Kolom attachment opsional: tetap kompatibel jika migrasi belum dijalankan.
    $hasAttachment = false;
    try {
        $col = $pdo->query("SHOW COLUMNS FROM notices LIKE 'attachment'");
        $hasAttachment = $col && $col->fetch() !== false;
        if (!$hasAttachment) {
            $pdo->exec("ALTER TABLE notices ADD COLUMN attachment LONGTEXT NULL");
            $hasAttachment = true;
        }
    } catch (PDOException $e) {
        $hasAttachment = false;
    }

    $select = $hasAttachment
        ? "SELECT id, subject AS title, description AS message, notice_date AS created_at, attachment FROM notices ORDER BY id DESC"
        : "SELECT id, subject AS title, description AS message, notice_date AS created_at FROM notices ORDER BY id DESC";

    $params = array();
    if ($companyId !== null) {
        $select = str_replace(' FROM notices ORDER BY', ' WHERE company_id = ? OR company_id IS NULL ORDER BY', $select);
        $params[] = (int)$companyId;
    } else {
        $select = str_replace(' FROM notices ORDER BY', ' WHERE company_id IS NULL ORDER BY', $select);
    }
    $stmt = $pdo->prepare($select);
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
