<?php

require_once __DIR__ . '/../common/DBConnection.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';

// Hanya HR yang boleh membuat pengumuman. Identitas berasal dari token/session,
// bukan dari payload request.
$user = Auth::requireApi();
$isSuperAdmin = Auth::isSuperAdmin();
if (!$isSuperAdmin && !Auth::isHr()) {
    Json::error('Anda tidak memiliki izin membuat pengumuman', 403);
}

$data = json_decode(file_get_contents("php://input"));

$title = isset($data->title) ? trim((string)$data->title) : '';
$message = isset($data->message) ? trim((string)$data->message) : '';

if ($title === '' || $message === '') {
    Json::error('Judul dan pesan tidak boleh kosong', 400);
}

// Validasi lampiran: format data URL, MIME sebenarnya, ekstensi nama file, dan ukuran.
$attachment = '';
if (isset($data->attachment) && (string)$data->attachment !== '') {
    $check = validateAttachment($data->attachment);
    if (!$check['ok']) {
        Json::error($check['message'], 422);
    }
    $attachment = $check['value'];
}

try {
    $pdo = DBConnection::pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $tanggal = date('Y-m-d');
    $waktu = date('H:i:s');
    $insertBy = mb_substr((string)$user['username'], 0, 30);

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

    $columns = 'subject, description, notice_date, notice_time, status, insert_by, insert_date, update_by, update_date';
    $values = array($title, $message, $tanggal, $waktu, $insertBy, $insertBy);
    $placeholders = '?, ?, ?, ?, 1, ?, NOW(), ?, NOW()';
    if ($hasAttachment) {
        $columns .= ', attachment';
        $placeholders .= ', ?';
        $values[] = $attachment;
    }
    // Super Admin membuat pengumuman global (company_id = NULL).
    $companyId = $isSuperAdmin ? null : Auth::companyId();
    if ($companyId !== null) {
        $columns .= ', company_id';
        $placeholders .= ', ?';
        $values[] = (int)$companyId;
    }
    $stmt = $pdo->prepare('INSERT INTO notices (' . $columns . ') VALUES (' . $placeholders . ')');
    $stmt->execute($values);
    $noticeId = (int)$pdo->lastInsertId();

    try {
        require_once __DIR__ . '/../service/NotificationService.php';
        (new NotificationService())->notifyAllExceptSuperAdmin(
            'Pengumuman Baru',
            $title,
            'announcement',
            'notice',
            $noticeId,
            $companyId
        );
    } catch (Exception $e) {
        error_log('[notif] notifikasi pengumuman gagal: ' . $e->getMessage());
    }

    http_response_code(201);
    echo json_encode([
        "success" => true,
        "message" => "Pengumuman berhasil dibuat"
    ]);
    exit;
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Gagal membuat pengumuman"
    ]);
    exit;
}

/**
 * Lampiran dikirim Flutter sebagai JSON string berisi dataUrl, namaFile, mime.
 * Format penyimpanan tidak diubah; hanya divalidasi sebelum disimpan.
 */
function validateAttachment($value)
{
    $raw = (string)$value;
    if (strlen($raw) > 14000000) {
        return array('ok' => false, 'message' => 'Lampiran terlalu besar');
    }

    $decoded = json_decode($raw, true);
    $dataUrl = $raw;
    $namaFile = '';
    if (is_array($decoded)) {
        $dataUrl = isset($decoded['dataUrl']) ? (string)$decoded['dataUrl'] : '';
        $namaFile = isset($decoded['namaFile']) ? (string)$decoded['namaFile'] : '';
    }

    $pos = strpos($dataUrl, ';base64,');
    if (strpos($dataUrl, 'data:') !== 0 || $pos === false) {
        return array('ok' => false, 'message' => 'Format lampiran tidak didukung');
    }
    $payload = preg_replace('/\s+/', '', substr($dataUrl, $pos + 8));
    if ($payload === '' || !preg_match('/^[A-Za-z0-9+\/=]+$/', $payload)) {
        return array('ok' => false, 'message' => 'Format lampiran tidak didukung');
    }

    $bytes = base64_decode($payload, true);
    if ($bytes === false || strlen($bytes) > 10 * 1024 * 1024) {
        return array('ok' => false, 'message' => 'Isi lampiran tidak valid atau terlalu besar');
    }

    return array('ok' => true, 'value' => $raw);
}
?>
