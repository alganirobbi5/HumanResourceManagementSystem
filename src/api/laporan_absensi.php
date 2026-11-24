<?php

require_once __DIR__ . '/../common/DBConnection.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';

// Endpoint laporan absensi seluruh karyawan hanya untuk HR.
// Identitas dan role berasal dari token/session, bukan dari request.
$user = Auth::requireApiHr();

// Filter tanggal opsional: /laporan_absensi.php?date=2026-11-19
// Dipakai Flutter untuk menampilkan absensi hari ini sesuai database.
$tanggal = isset($_GET['date']) ? trim((string)$_GET['date']) : '';
if ($tanggal !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Format tanggal tidak valid."]);
    exit;
}

// Filter rentang: /laporan_absensi.php?start_date=2026-11-01&end_date=2026-11-30
$startDate = isset($_GET['start_date']) ? trim((string)$_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? trim((string)$_GET['end_date']) : '';
if (($startDate !== '' || $endDate !== '') &&
    (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) ||
     !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate))) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Format rentang tanggal tidak valid."]);
    exit;
}
if ($startDate !== '' && $endDate !== '' && $startDate > $endDate) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Tanggal akhir tidak boleh sebelum tanggal mulai."]);
    exit;
}

try {
    $pdo = DBConnection::pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ambil data absensi (Join dengan tabel employees untuk dapat nama)
    $selfieColumn = '';
    try {
        foreach (array('selfie_path', 'selfie_out_path') as $column) {
            $col = $pdo->query("SHOW COLUMNS FROM attendances LIKE '" . $column . "'");
            if ($col && $col->fetch()) {
                $selfieColumn .= ', a.' . $column;
            }
        }
    } catch (PDOException $e) {
        $selfieColumn = '';
    }

    // Sembunyikan data yang sudah dihapus HR (soft-delete).
    $whereDeleted = '';
    try {
        $col = $pdo->query("SHOW COLUMNS FROM attendances LIKE 'deleted_at'");
        if ($col && $col->fetch()) {
            $whereDeleted = "a.deleted_at IS NULL";
        }
    } catch (PDOException $e) {
        $whereDeleted = '';
    }

    $query = "SELECT a.id, a.date, a.clock_in, a.clock_out, a.status,
                     a.latitude, a.longitude$selfieColumn,
                     e.first_name, e.last_name
              FROM attendances a
              LEFT JOIN employees e ON a.employee_id = e.id ";
    $conditions = array();
    $params = array();
    if ($whereDeleted !== '') $conditions[] = $whereDeleted;
    if ($startDate !== '' && $endDate !== '') {
        $conditions[] = "a.date BETWEEN ? AND ?";
        $params[] = $startDate;
        $params[] = $endDate;
    } elseif ($tanggal !== '') {
        $conditions[] = "a.date = ?";
        $params[] = $tanggal;
    }
    if (!empty($conditions)) {
        $query .= "WHERE " . implode(" AND ", $conditions) . " ";
    }
    $query .= "ORDER BY a.date DESC, a.clock_in DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($data as &$row) {
        $row['selfie_in_url'] = !empty($row['selfie_path']) ? 'selfie.php?id=' . (int)$row['id'] . '&type=in' : null;
        $row['selfie_out_url'] = !empty($row['selfie_out_path']) ? 'selfie.php?id=' . (int)$row['id'] . '&type=out' : null;
    }
    unset($row);

    // Envelope lama dipertahankan selama masa transisi Flutter.
    http_response_code(200);
    echo json_encode(["status" => "success", "data" => $data]);
    exit;
} catch (PDOException $e) {
    // Jangan pernah mengembalikan detail exception database ke client.
    Json::error('Gagal memuat data absensi', 500);
}
?>
