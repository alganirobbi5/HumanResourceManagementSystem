<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../common/DBConnection.php';

// Filter tanggal opsional: /laporan_absensi.php?date=2026-11-19
// Dipakai Flutter untuk menampilkan absensi hari ini sesuai database.
$tanggal = isset($_GET['date']) ? trim((string)$_GET['date']) : '';
if ($tanggal !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Format tanggal tidak valid."]);
    exit;
}

try {
    $pdo = new PDO("mysql:host=localhost;dbname=hris;charset=utf8mb4", "HRIS", "Admin@2026");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Ambil data absensi (Join dengan tabel employees untuk dapat nama)
    $query = "SELECT a.id, a.date, a.clock_in, a.clock_out, a.status, 
                     e.first_name, e.last_name 
              FROM attendances a 
              LEFT JOIN employees e ON a.employee_id = e.id ";
    $params = array();
    if ($tanggal !== '') {
        $query .= "WHERE a.date = ? ";
        $params[] = $tanggal;
    }
    $query .= "ORDER BY a.date DESC, a.clock_in DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    http_response_code(200);
    echo json_encode(["status" => "success", "data" => $data]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Gagal: " . $e->getMessage()]);
}
?>
