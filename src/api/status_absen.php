<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../common/DBConnection.php';

$employee_id = isset($_GET['employee_id']) ? $_GET['employee_id'] : null;
$tanggal = date('Y-m-d'); 

if ($employee_id) {
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=hris;charset=utf8mb4", "HRIS", "Admin@2026");
        $stmt = $pdo->prepare("SELECT clock_in, clock_out FROM attendances WHERE employee_id = ? AND date = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$employee_id, $tanggal]);
        $absen = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($absen) {
            echo json_encode([
                "status" => "success", 
                "sudah_masuk" => true, 
                "jam_masuk" => $absen['clock_in'],
                "jam_pulang" => ($absen['clock_out'] == '00:00:00' || $absen['clock_out'] == null) ? null : $absen['clock_out'],
                "sudah_pulang" => ($absen['clock_out'] != '00:00:00' && $absen['clock_out'] != null)
            ]);
        } else {
            echo json_encode([
                "status" => "success", 
                "sudah_masuk" => false, 
                "jam_masuk" => null,
                "jam_pulang" => null,
                "sudah_pulang" => false
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}
?>