<?php

require_once __DIR__ . '/../service/CompanyMasterService.php';

class AdminLeaveIdentityService
{
    /**
     * Pastikan akun HR/Admin memiliki employee_id untuk keperluan pengajuan
     * cuti dan izin keluar. Identitas diambil dari data yang sudah ada;
     * tidak mengarang nama atau jabatan.
     */
    public static function ensureEmployeeId(PDO $pdo, array $user, $companyId = null)
    {
        if (!empty($user['employee_id'])) {
            $cek = $pdo->prepare('SELECT id FROM employees WHERE id = ? LIMIT 1');
            $cek->execute(array((int)$user['employee_id']));
            if ($cek->fetch()) {
                return (int)$user['employee_id'];
            }
        }

        $username = trim((string)(isset($user['username']) ? $user['username'] : ''));
        if ($username === '') {
            return null;
        }

        $cek = $pdo->prepare('SELECT id FROM employees WHERE user_name = ? ORDER BY id LIMIT 1');
        $cek->execute(array($username));
        $linked = $cek->fetch(PDO::FETCH_ASSOC);
        if ($linked) {
            self::linkUser($pdo, (int)$user['id'], (int)$linked['id']);
            return (int)$linked['id'];
        }

        $role = isset($user['role']) ? (string)$user['role'] : '';
        $adminId = !empty($user['admin_id']) ? (int)$user['admin_id'] : null;
        if ($role !== 'hr' && $adminId === null) {
            return null;
        }

        $master = new CompanyMasterService($pdo);
        $department = $companyId !== null ? $master->firstDepartment((int)$companyId) : 0;
        $designation = $companyId !== null ? $master->firstDesignation((int)$companyId) : 0;
        if ((int)$department <= 0 || (int)$designation <= 0) {
            return null;
        }

        $nama = trim((string)(isset($user['display_name']) ? $user['display_name'] : $username));
        if ($nama === '') {
            $nama = $username;
        }
        $bagian = preg_split('/\s+/', $nama, 2);
        $firstName = isset($bagian[0]) ? substr($bagian[0], 0, 30) : $username;
        $lastName = isset($bagian[1]) ? substr($bagian[1], 0, 30) : '-';

        $password = '';
        if ($adminId !== null) {
            $stmt = $pdo->prepare('SELECT password FROM admins WHERE id = ? LIMIT 1');
            $stmt->execute(array($adminId));
            $password = (string)$stmt->fetchColumn();
        }
        if ($password === '') {
            $password = bin2hex(random_bytes(8));
        }

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                'INSERT INTO employees
                 (first_name, last_name, user_name, password, gender, email,
                  department_id, designation_id, mobile_no, status, create_by, created_date, company_id)
                 VALUES (?, ?, ?, ?, "Male", ?, ?, ?, "", 1, "system", NOW(), ?)'
            );
            $stmt->execute(array(
                $firstName,
                $lastName,
                substr($username, 0, 30),
                substr($password, 0, 20),
                substr($username, 0, 30),
                (int)$department,
                (int)$designation,
                $companyId !== null ? (int)$companyId : null
            ));
            $employeeId = (int)$pdo->lastInsertId();
            self::linkUser($pdo, (int)$user['id'], $employeeId);
            $pdo->commit();
            return $employeeId;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return null;
        }
    }

    private static function linkUser(PDO $pdo, $userId, $employeeId)
    {
        if ($userId <= 0) {
            return;
        }
        $pdo->prepare('UPDATE users SET employee_id = ? WHERE id = ? AND (employee_id IS NULL OR employee_id = 0)')
            ->execute(array($employeeId, $userId));
    }
}
