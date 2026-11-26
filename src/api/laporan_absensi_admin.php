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

$user = Auth::requireApiAdmin();
$adminId = Auth::adminId();
$companyId = Auth::companyId();

$tanggal = isset($_GET['date']) ? trim((string)$_GET['date']) : '';
if (isset($_GET['tanggal']) && $tanggal === '') {
    $tanggal = trim((string)$_GET['tanggal']);
}
$startDate = isset($_GET['start_date']) ? trim((string)$_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? trim((string)$_GET['end_date']) : '';

if ($tanggal !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
    Json::error('Format tanggal tidak valid.', 400);
}
if ($startDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
    Json::error('Format rentang tanggal tidak valid.', 400);
}
if ($endDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    Json::error('Format rentang tanggal tidak valid.', 400);
}
if ($startDate !== '' && $endDate !== '' && $startDate > $endDate) {
    Json::error('Tanggal akhir tidak boleh sebelum tanggal mulai.', 400);
}

function laporanAdminKolomTersedia($db, $table, $column)
{
    $rows = $db->selectAll("SHOW COLUMNS FROM `$table` LIKE ?", array($column));
    return !empty($rows);
}

try {
    $db = DBConnection::get();

    if (!$db->selectOne("SHOW TABLES LIKE 'admin_attendances'")) {
        Json::ok('Belum ada data absensi Admin HR', array('data' => array()));
    }

    $kolom = array(
        'aa.id',
        'aa.admin_id',
        'aa.tanggal',
        'aa.waktu_masuk',
        'aa.waktu_pulang',
    );
    $kolomSelfieMasuk = laporanAdminKolomTersedia($db, 'admin_attendances', 'selfie_masuk');
    $kolomSelfiePulang = laporanAdminKolomTersedia($db, 'admin_attendances', 'selfie_pulang');
    $kolomLatMasuk = laporanAdminKolomTersedia($db, 'admin_attendances', 'latitude_masuk');
    $kolomLngMasuk = laporanAdminKolomTersedia($db, 'admin_attendances', 'longitude_masuk');

    if ($kolomSelfieMasuk) {
        $kolom[] = 'aa.selfie_masuk';
    }
    if ($kolomSelfiePulang) {
        $kolom[] = 'aa.selfie_pulang';
    }
    if ($kolomLatMasuk) {
        $kolom[] = 'aa.latitude_masuk';
    }
    if ($kolomLngMasuk) {
        $kolom[] = 'aa.longitude_masuk';
    }

    $join = '';
    if ($db->selectOne("SHOW TABLES LIKE 'admins'")) {
        $kolom[] = 'a.name AS admin_name';
        $join .= ' LEFT JOIN admins a ON a.id = aa.admin_id';
    }
    if (laporanAdminKolomTersedia($db, 'users', 'admin_id')) {
        $kolom[] = 'u.username';
        $join .= ' LEFT JOIN users u ON u.admin_id = aa.admin_id';
    }

    $sql = 'SELECT ' . implode(', ', $kolom) . ' FROM admin_attendances aa' . $join;
    $params = array();

    if ($adminId !== null) {
        $sql .= ' WHERE aa.admin_id = ?';
        $params[] = (int)$adminId;
    }
    if ($companyId !== null) {
        $sql .= ($params ? ' AND' : ' WHERE') . ' aa.company_id = ?';
        $params[] = (int)$companyId;
    }
    if ($tanggal !== '') {
        $sql .= ($params ? ' AND' : ' WHERE') . ' aa.tanggal = ?';
        $params[] = $tanggal;
    } else {
        if ($startDate !== '') {
            $sql .= ($params ? ' AND' : ' WHERE') . ' aa.tanggal >= ?';
            $params[] = $startDate;
        }
        if ($endDate !== '') {
            $sql .= ($params ? ' AND' : ' WHERE') . ' aa.tanggal <= ?';
            $params[] = $endDate;
        }
    }
    $sql .= ' ORDER BY aa.tanggal DESC, aa.waktu_masuk DESC, aa.id DESC';

    $rows = $db->selectAll($sql, $params);
    $data = array();

    foreach ($rows as $row) {
        $nama = trim((string)(isset($row['admin_name']) ? $row['admin_name'] : ''));
        if ($nama === '') {
            $nama = trim((string)(isset($row['username']) ? $row['username'] : ''));
        }
        if ($nama === '') {
            $nama = 'HR / Admin';
        }

        $selfieMasuk = $kolomSelfieMasuk && !empty($row['selfie_masuk'])
            ? 'selfie.php?id=' . (int)$row['id'] . '&type=in&scope=admin'
            : null;
        $selfiePulang = $kolomSelfiePulang && !empty($row['selfie_pulang'])
            ? 'selfie.php?id=' . (int)$row['id'] . '&type=out&scope=admin'
            : null;

        $data[] = array(
            'id' => (int)$row['id'],
            'record_type' => 'admin',
            'admin_id' => (int)$row['admin_id'],
            'nama_lengkap' => $nama,
            'nama' => $nama,
            'employee_name' => $nama,
            'first_name' => $nama,
            'last_name' => '',
            'department' => 'HR / Admin',
            'jabatan' => 'HR / Admin',
            'tanggal' => $row['tanggal'],
            'date' => $row['tanggal'],
            'jam_masuk' => $row['waktu_masuk'],
            'clock_in' => $row['waktu_masuk'],
            'jam_keluar' => $row['waktu_pulang'],
            'clock_out' => $row['waktu_pulang'],
            'jam_pulang' => $row['waktu_pulang'],
            'status' => 'recorded',
            'latitude' => $kolomLatMasuk ? $row['latitude_masuk'] : null,
            'longitude' => $kolomLngMasuk ? $row['longitude_masuk'] : null,
            'selfie_in_url' => $selfieMasuk,
            'selfie_out_url' => $selfiePulang,
        );
    }

    Json::ok('Daftar absensi Admin HR', array('data' => $data));
} catch (Exception $e) {
    Json::error('Gagal memuat data absensi Admin HR', 500);
}
