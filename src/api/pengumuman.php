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

    $fields = 'n.id, n.subject AS title, n.description AS message, n.notice_date AS created_at,
               COALESCE(c.company_name, "Semua Perusahaan") AS company_name';
    if ($hasAttachment) {
        $fields .= ', n.attachment';
    }
    $sql = 'SELECT ' . $fields . ' FROM notices n
            LEFT JOIN companies c ON c.id = n.company_id';
    $params = array();
    if (!Auth::isSuperAdmin()) {
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
