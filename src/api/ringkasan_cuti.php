<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

try {
    $pdo = new PDO("mysql:host=localhost;dbname=hris;charset=utf8mb4", "HRIS", "Admin@2026");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Bulan dan tahun saat ini
    $bulan_ini = date('m');
    $tahun_ini = date('Y');

    // 1. Total Pengajuan Bulan Ini
    $stmtTotal = $pdo->prepare("SELECT COUNT(*) as total FROM leaves WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?");
    $stmtTotal->execute([$bulan_ini, $tahun_ini]);
    $total = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'];

    // 2. Menunggu Persetujuan (status = 1)
    $stmtPending = $pdo->prepare("SELECT COUNT(*) as pending FROM leaves WHERE status = 1 AND MONTH(created_at) = ? AND YEAR(created_at) = ?");
    $stmtPending->execute([$bulan_ini, $tahun_ini]);
    $pending = $stmtPending->fetch(PDO::FETCH_ASSOC)['pending'];

    // 3. Disetujui (status = 2)
    $stmtApproved = $pdo->prepare("SELECT COUNT(*) as approved FROM leaves WHERE status = 2 AND MONTH(created_at) = ? AND YEAR(created_at) = ?");
    $stmtApproved->execute([$bulan_ini, $tahun_ini]);
    $approved = $stmtApproved->fetch(PDO::FETCH_ASSOC)['approved'];

    // 4. Ditolak (status = 3)
    $stmtRejected = $pdo->prepare("SELECT COUNT(*) as rejected FROM leaves WHERE status = 3 AND MONTH(created_at) = ? AND YEAR($tahun_ini) = ?");
    $stmtRejected->execute([$bulan_ini, $tahun_ini]);
    $rejected = $stmtRejected->fetch(PDO::FETCH_ASSOC)['rejected'];

    echo json_encode([
        "status" => "success",
        "data" => [
            "bulan" => date('F Y'),
            "total" => (int)$total,
            "pending" => (int)$pending,
            "approved" => (int)$approved,
            "rejected" => (int)$rejected
        ]
    ]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>