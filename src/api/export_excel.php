<?php
// Panggil koneksi database
require_once __DIR__ . '/../common/DBConnection.php';

try {
    $pdo = new PDO("mysql:host=localhost;dbname=hrm;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ambil data absensi
    $query = "SELECT a.date, a.clock_in, a.clock_out, a.status, 
                     e.first_name, e.last_name 
              FROM attendances a 
              LEFT JOIN employees e ON a.employee_id = e.id 
              ORDER BY a.date DESC";
              
    $stmt = $pdo->query($query);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Set header agar browser mendownload sebagai file CSV (bisa dibuka di Excel)
    $filename = "Laporan_Absensi_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // Buka output stream
    $output = fopen('php://output', 'w');

    // Tulis judul kolom
    fputcsv($output, array('Tanggal', 'Nama Karyawan', 'Jam Masuk', 'Jam Keluar', 'Status'));

    // Tulis data
    foreach ($data as $row) {
        $nama = trim($row['first_name'] . ' ' . $row['last_name']);
        fputcsv($output, array(
            $row['date'],
            $nama,
            $row['clock_in'],
            $row['clock_out'],
            $row['status']
        ));
    }

    fclose($output);
    exit;

} catch (PDOException $e) {
    echo "Gagal membuat laporan: " . $e->getMessage();
}
?>