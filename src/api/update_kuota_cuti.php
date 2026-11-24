<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

$data = json_decode(file_get_contents("php://input"));

// Menerima array employee_ids dan jumlah tambahan
if (!empty($data->employee_ids) && isset($data->tambahan_hari)) {
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=hris;charset=utf8mb4", "HRIS", "Admin@2026");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $tambahan = (int)$data->tambahan_hari;
        $ids = $data->employee_ids; // Array of IDs

        foreach ($ids as $id) {
            $emp_id = (int)$id;
            
            // Cek apakah data kuota sudah ada
            $stmtCek = $pdo->prepare("SELECT id FROM employee_leaves WHERE employee_id = ?");
            $stmtCek->execute([$emp_id]);
            
            if ($stmtCek->fetch()) {
                // Jika sudah ada, TAMBAHKAN kuota (bukan timpa)
                $query = "UPDATE employee_leaves 
                          SET total_leave_yarly = total_leave_yarly + ?, 
                              remaining_leav = remaining_leav + ? 
                          WHERE employee_id = ?";
                $pdo->prepare($query)->execute([$tambahan, $tambahan, $emp_id]);
            } else {
                // Jika belum ada, buat baru
                $query = "INSERT INTO employee_leaves (employee_id, total_leave_yarly, dedicated_leave, remaining_leav) 
                          VALUES (?, ?, 0, ?)";
                $pdo->prepare($query)->execute([$emp_id, $tambahan, $tambahan]);
            }
        }

        echo json_encode(["status" => "success", "message" => "Kuota cuti berhasil ditambahkan"]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Data tidak lengkap"]);
}
?>