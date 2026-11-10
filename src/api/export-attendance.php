<?php

require_once __DIR__ . '/../common/Auth.php';
require_once __DIR__ . '/../common/Json.php';
require_once __DIR__ . '/../common/Config.php';
require_once __DIR__ . '/../service/AttendanceService.php';
require_once __DIR__ . '/../service/XlsxWriter.php';

$user = Auth::requireApiHr();

$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

if ($startDate === '' || $endDate === '') {
    Json::error('Tanggal mulai dan tanggal akhir wajib dipilih', 422);
}

$d1 = DateTimeImmutable::createFromFormat('Y-m-d', $startDate);
$d2 = DateTimeImmutable::createFromFormat('Y-m-d', $endDate);
if (!$d1 || !$d2 || $d1->format('Y-m-d') !== $startDate || $d2->format('Y-m-d') !== $endDate) {
    Json::error('Format tanggal tidak valid', 422);
}
if ($d2 < $d1) {
    Json::error('Tanggal akhir tidak boleh lebih awal daripada tanggal mulai', 422);
}

$rangeDays = (int)$d1->diff($d2)->days + 1;
if ($rangeDays > Config::MAX_EXPORT_RANGE_DAYS) {
    Json::error('Rentang tanggal maksimal ' . Config::MAX_EXPORT_RANGE_DAYS . ' hari', 422);
}

try {
    $service = new AttendanceService();
    $rows = $service->forExport($startDate, $endDate);

    // Kelompokkan data per tanggal
    $byDate = array();
    foreach ($rows as $row) {
        $byDate[$row['date']][] = $row;
    }

    $writer = new XlsxWriter();
    $header = array('No.', 'ID Karyawan', 'Nama Karyawan', 'Departemen/Jabatan', 'Tanggal', 'Jam Absensi', 'Latitude', 'Longitude', 'Status');

    $cursor = $d1;
    $end = $d2;
    while ($cursor <= $end) {
        $date = $cursor->format('Y-m-d');
        $sheetRows = array($header);
        if (empty($byDate[$date])) {
            $sheetRows[] = array('', '', 'Tidak ada data absensi', '', '', '', '', '', '');
        } else {
            $no = 1;
            foreach ($byDate[$date] as $att) {
                $name = trim($att['first_name'] . ' ' . $att['last_name']);
                $deptPos = trim(($att['department_name'] ?: '') . ' / ' . ($att['designation_name'] ?: ''), ' /');
                $sheetRows[] = array(
                    $no++,
                    $att['employee_nik'] !== null && $att['employee_nik'] !== '' ? $att['employee_nik'] : (string)$att['employee_id'],
                    $name,
                    $deptPos,
                    $att['date'],
                    $att['clock_in'],
                    $att['latitude'],
                    $att['longitude'],
                    $att['status']
                );
            }
        }
        $writer->addSheet($date, $sheetRows);
        $cursor = $cursor->modify('+1 day');
    }

    $fileName = 'laporan-absensi-' . $startDate . '_sampai_' . $endDate . '.xlsx';
    $tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('export_', true) . '.xlsx';
    $writer->save($tmpFile);

    $size = filesize($tmpFile);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . $size);
    header('X-Content-Type-Options: nosniff');
    readfile($tmpFile);
    @unlink($tmpFile);
    exit;
} catch (Exception $e) {
    Json::error('Export gagal: ' . $e->getMessage(), 500);
}
