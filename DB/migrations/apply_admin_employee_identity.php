<?php
/**
 * Membuat identitas karyawan untuk Admin HR dari data AKTUAL yang disediakan
 * di DB/admin_employee_identity.local.php.
 *
 * TIDAK dijalankan otomatis. Tidak ada nilai fiktif. Tidak memakai admins.id
 * sebagai employee_id. Aman dijalankan ulang (idempoten).
 */

// SUPERSEDED — JANGAN DIJALANKAN.
// Arsitektur terbaru: Admin HR tidak boleh memiliki employee record.
// Gunakan DB/migrations/20261120_admin_api_identity.sql (users.admin_id +
// admin_attendances) sebagai gantinya.
echo "SUPERSEDED: gunakan DB/migrations/20261120_admin_api_identity.sql\n";
exit(0);

require_once __DIR__ . '/../../src/common/DBConnection.php';

$dataFile = __DIR__ . '/../admin_employee_identity.local.php';

if (!file_exists($dataFile)) {
    echo "BLOCKED: file data aktual belum ada: DB/admin_employee_identity.local.php\n";
    echo "Salin DB/admin_employee_identity.local.example.php dan isi dengan data resmi.\n";
    exit(1);
}

$identity = require $dataFile;
if (!is_array($identity)) {
    echo "BLOCKED: format file data tidak valid\n";
    exit(1);
}

$required = array('first_name', 'last_name', 'gender', 'email', 'department_id', 'designation_id', 'mobile_no');
$missing = array();
foreach ($required as $field) {
    $value = isset($identity[$field]) ? trim((string)$identity[$field]) : '';
    if ($value === '' || $value === '0') {
        $missing[] = $field;
    }
}
if (!empty($missing)) {
    echo "BLOCKED: field data aktual belum diisi: " . implode(', ', $missing) . "\n";
    echo "Tidak ada nilai fiktif yang dibuat.\n";
    exit(1);
}

$pdo = DBConnection::pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$admin = $pdo->query("SELECT id, name, password FROM admins LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$admin) {
    echo "BLOCKED: tidak ada akun admin di tabel admins\n";
    exit(1);
}

// Idempoten: hentikan jika pemetaan sudah ada
$s = $pdo->prepare("SELECT id, employee_id FROM users WHERE username = ? LIMIT 1");
$s->execute(array($admin['name']));
$existing = $s->fetch(PDO::FETCH_ASSOC);
if ($existing) {
    echo "SKIPPED: pemetaan users sudah ada (employee_id=" .
        ($existing['employee_id'] !== null ? (int)$existing['employee_id'] : 'NULL') . ")\n";
    exit(0);
}

// Validasi FK aktual
foreach (array('department_id' => 'departments', 'designation_id' => 'employee_designations') as $field => $table) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE id = ?");
    $s->execute(array((int)$identity[$field]));
    if ($s->fetchColumn() == 0) {
        echo "BLOCKED: $field tidak ditemukan di tabel $table\n";
        exit(1);
    }
}

try {
    $pdo->beginTransaction();

    // employee_id berasal dari AUTO_INCREMENT employees, BUKAN admins.id.
    $stmt = $pdo->prepare(
        "INSERT INTO employees
         (first_name, last_name, user_name, password, gender, email, department_id, designation_id, mobile_no, status, create_by, created_date)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'migration', NOW())"
    );
    $stmt->execute(array(
        $identity['first_name'],
        $identity['last_name'],
        $admin['name'], // user_name aktual dari admins
        $admin['password'], // password aktual dari admins
        $identity['gender'],
        $identity['email'],
        (int)$identity['department_id'],
        (int)$identity['designation_id'],
        $identity['mobile_no'],
    ));
    $employeeId = (int)$pdo->lastInsertId();

    // Akun users untuk autentikasi API. password_hash acak karena login admin
    // tetap diverifikasi lewat tabel admins; Password dicerminkan dari admins.
    $hasPasswordColumn = (bool)$pdo->query("SHOW COLUMNS FROM users LIKE 'Password'")->fetch();
    if ($hasPasswordColumn) {
        $stmt2 = $pdo->prepare(
            "INSERT INTO users (username, email, password_hash, `Password`, role, employee_id, is_active)
             VALUES (?, ?, UUID(), ?, 'hr', ?, 1)"
        );
        $stmt2->execute(array($admin['name'], $identity['email'], $admin['password'], $employeeId));
    } else {
        $stmt2 = $pdo->prepare(
            "INSERT INTO users (username, email, password_hash, role, employee_id, is_active)
             VALUES (?, ?, UUID(), 'hr', ?, 1)"
        );
        $stmt2->execute(array($admin['name'], $identity['email'], $employeeId));
    }
    $userId = (int)$pdo->lastInsertId();

    $pdo->commit();

    echo "OK: employee_id=$employeeId user_id=$userId admin_id=" . (int)$admin['id'] . "\n";
    echo "employee_id tidak.diambil dari admins.id\n";
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "FAILED: migrasi dibatalkan (" . get_class($e) . ")\n";
    exit(1);
}
