<?php

require_once __DIR__ . '/../common/DBConnection.php';
require_once __DIR__ . '/../common/Config.php';

class LeaveQuotaService
{
    private $pdo;
    private $schema;

    public function __construct(PDO $pdo = null)
    {
        $this->pdo = $pdo ?: DBConnection::get();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    private function hasTable($table)
    {
        return (bool)$this->pdo->query("SHOW TABLES LIKE '" . $table . "'")->fetchColumn();
    }

    private function hasColumn($table, $column)
    {
        $stmt = $this->pdo->query("SHOW COLUMNS FROM " . $table . " LIKE '" . $column . "'");
        return $stmt && $stmt->fetch() !== false;
    }

    public function schema()
    {
        if ($this->schema !== null) {
            return $this->schema;
        }
        foreach (array('employee_leaves', 'employee_leavs') as $table) {
            if (!$this->hasTable($table)) {
                continue;
            }
            $remaining = null;
            foreach (array('remaining_leave', 'remaining_leav') as $column) {
                if ($this->hasColumn($table, $column)) {
                    $remaining = $column;
                    break;
                }
            }
            $total = null;
            foreach (array('total_leave_yearly', 'total_leave_yarly') as $column) {
                if ($this->hasColumn($table, $column)) {
                    $total = $column;
                    break;
                }
            }
            if ($remaining !== null && $total !== null) {
                $this->schema = array('table' => $table, 'remaining' => $remaining, 'total' => $total);
                return $this->schema;
            }
        }
        $this->schema = false;
        return false;
    }

    private function requiredDefaults($table)
    {
        $stmt = $this->pdo->prepare(
            "SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ?
               AND IS_NULLABLE = 'NO' AND COLUMN_DEFAULT IS NULL
               AND EXTRA NOT LIKE '%auto_increment%'"
        );
        $stmt->execute([$table]);
        $defaults = array();
        $numeric = array('int', 'integer', 'bigint', 'smallint', 'tinyint', 'decimal', 'float', 'double', 'numeric');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $defaults[$column['COLUMN_NAME']] = in_array(strtolower($column['DATA_TYPE']), $numeric, true) ? 0 : '';
        }
        return $defaults;
    }

    private function insertQuotaRow($schema, $employeeId, $days, $totalDays = null)
    {
        $columns = array('employee_id', $schema['total'], $schema['remaining']);
        $totalDays = $totalDays === null ? (float)$days : (float)$totalDays;
        $values = array((int)$employeeId, $totalDays, $totalDays);
        if ($this->hasColumn($schema['table'], 'dedicated_leave')) {
            $columns[] = 'dedicated_leave';
            $values[] = 0;
        }
        foreach ($this->requiredDefaults($schema['table']) as $column => $value) {
            if (!in_array($column, $columns, true)) {
                $columns[] = $column;
                $values[] = $value;
            }
        }
        $this->pdo->prepare('INSERT INTO ' . $schema['table'] . ' (' . implode(', ', $columns) . ') VALUES (' .
            implode(', ', array_fill(0, count($values), '?')) . ')')->execute($values);
    }

    public function employeesWithQuota($companyId = null)
    {
        $schema = $this->schema();
        $year = (int)Config::companyNow()->format('Y');
        $useBalances = $this->hasTable('leave_balances');
        if ($useBalances) {
            $totalExpr = 'COALESCE(b.entitled_days, ' . Config::LEAVE_ENTITLEMENT_DEFAULT . ')';
            $remainingExpr = 'COALESCE(b.entitled_days, ' . Config::LEAVE_ENTITLEMENT_DEFAULT . ') - COALESCE(b.used_days, 0)';
            $quotaJoin = 'LEFT JOIN leave_balances b ON b.employee_id = e.id '
                . 'AND b.leave_type = "' . Config::LEAVE_TYPE_DEFAULT . '" AND b.entitlement_year = ' . $year;
        } else {
            $totalExpr = $schema ? 'COALESCE(q.' . $schema['total'] . ', ' . Config::LEAVE_ENTITLEMENT_DEFAULT . ')' : (string)Config::LEAVE_ENTITLEMENT_DEFAULT;
            $remainingExpr = $schema ? 'COALESCE(q.' . $schema['remaining'] . ', ' . Config::LEAVE_ENTITLEMENT_DEFAULT . ')' : (string)Config::LEAVE_ENTITLEMENT_DEFAULT;
            $quotaJoin = $schema ? 'LEFT JOIN ' . $schema['table'] . ' q ON q.employee_id = e.id' : '';
        }
        $hasDepartments = $this->hasTable('departments');
        $departmentExpr = $hasDepartments ? 'd.name AS department' : "'' AS department";
        $departmentJoin = $hasDepartments ? 'LEFT JOIN departments d ON d.id = e.department_id' : '';

        $where = '';
        $params = array();
        if ($companyId !== null) {
            $where = ' WHERE e.company_id = ?';
            $params[] = (int)$companyId;
        }
        $sql = 'SELECT e.id AS employee_id, e.first_name, e.last_name, e.user_name AS username, ' .
            $departmentExpr . ', ' . $totalExpr . ' AS total_kuota, ' . $remainingExpr . ' AS sisa_kuota ' .
            'FROM employees e ' . $departmentJoin . ' ' . $quotaJoin . $where . ' ' .
            'ORDER BY e.first_name, e.last_name';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addDays($employeeId, $days)
    {
        $schema = $this->schema();
        if (!$schema) {
            throw new RuntimeException('Skema kuota cuti tidak tersedia', 500);
        }
        $days = (float)$days;
        if ($employeeId <= 0 || $days <= 0) {
            throw new InvalidArgumentException('Karyawan dan jumlah hari wajib diisi', 422);
        }

        $table = $schema['table'];
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT id FROM ' . $table . ' WHERE employee_id = ? LIMIT 1');
            $stmt->execute([(int)$employeeId]);
            if ($stmt->fetch()) {
                $this->pdo->prepare('UPDATE ' . $table . ' SET ' . $schema['total'] . ' = ' . $schema['total'] . ' + ?, ' .
                    $schema['remaining'] . ' = ' . $schema['remaining'] . ' + ? WHERE employee_id = ?')
                    ->execute(array($days, $days, (int)$employeeId));
            } else {
                $this->insertQuotaRow($schema, $employeeId, $days, Config::LEAVE_ENTITLEMENT_DEFAULT + $days);
            }
            $this->syncLeaveBalance($employeeId, $days);
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Kurangi kuota cuti karyawan. Sisa kuota tidak boleh minus. */
    public function deductDays($employeeId, $days)
    {
        $schema = $this->schema();
        if (!$schema) {
            throw new RuntimeException('Skema kuota cuti tidak tersedia', 500);
        }
        $days = (float)$days;
        if ($employeeId <= 0 || $days <= 0) {
            throw new InvalidArgumentException('Karyawan dan jumlah hari wajib diisi', 422);
        }

        $table = $schema['table'];
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT id FROM ' . $table . ' WHERE employee_id = ? LIMIT 1');
            $stmt->execute([(int)$employeeId]);
            if ($stmt->fetch()) {
                $this->pdo->prepare('UPDATE ' . $table . ' SET ' . $schema['remaining'] .
                    ' = GREATEST(0, ' . $schema['remaining'] . ' - ?) WHERE employee_id = ?')
                    ->execute(array($days, (int)$employeeId));
            }
            $this->syncDeductLeaveBalance($employeeId, $days);
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function syncDeductLeaveBalance($employeeId, $days)
    {
        try {
            if (!$this->hasTable('leave_balances')) {
                return;
            }
            $year = (int)Config::companyNow()->format('Y');
            $stmt = $this->pdo->prepare(
                'SELECT id FROM leave_balances
                 WHERE employee_id = ? AND leave_type = ? AND entitlement_year = ? LIMIT 1'
            );
            $stmt->execute(array((int)$employeeId, Config::LEAVE_TYPE_DEFAULT, $year));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $this->pdo->prepare(
                    'UPDATE leave_balances
                     SET entitled_days = GREATEST(used_days, entitled_days - ?), updated_at = NOW()
                     WHERE id = ?'
                )->execute(array((float)$days, (int)$row['id']));
            }
        } catch (PDOException $e) {
            // Tabel leave_balances opsional; jangan gagalkan update kuota legacy.
        }
    }

    /**
     * Kuota yang dipakai saat pengajuan/disetujui berada di tabel
     * leave_balances. Jadi penambahan dari menu HR harus masuk ke sana juga
     * agar angka yang tampil di aplikasi benar-benar bertambah, bukan hanya
     * mengubah tabel legacy employee_leavs.
     */
    private function syncLeaveBalance($employeeId, $days)
    {
        try {
            if (!$this->hasTable('leave_balances')) {
                return;
            }
            $year = (int)Config::companyNow()->format('Y');
            $stmt = $this->pdo->prepare(
                'SELECT id FROM leave_balances
                 WHERE employee_id = ? AND leave_type = ? AND entitlement_year = ? LIMIT 1'
            );
            $stmt->execute(array((int)$employeeId, Config::LEAVE_TYPE_DEFAULT, $year));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $this->pdo->prepare(
                    'UPDATE leave_balances SET entitled_days = entitled_days + ?, updated_at = NOW() WHERE id = ?'
                )->execute(array((float)$days, (int)$row['id']));
                return;
            }
            $this->pdo->prepare(
                'INSERT INTO leave_balances
                    (employee_id, leave_type, entitlement_year, entitled_days, used_days)
                 VALUES (?, ?, ?, ?, 0)'
            )->execute(array(
                (int)$employeeId,
                Config::LEAVE_TYPE_DEFAULT,
                $year,
                Config::LEAVE_ENTITLEMENT_DEFAULT + (float)$days,
            ));
        } catch (PDOException $e) {
            // Tabel leave_balances opsional; jangan gagalkan update kuota legacy.
        }
    }

    public function ensureForEmployee($employeeId, $days = 12)
    {
        $schema = $this->schema();
        if (!$schema || $employeeId <= 0) {
            return false;
        }
        $stmt = $this->pdo->prepare('SELECT id FROM ' . $schema['table'] . ' WHERE employee_id = ? LIMIT 1');
        $stmt->execute([(int)$employeeId]);
        if ($stmt->fetch()) {
            return false;
        }
        $this->insertQuotaRow($schema, $employeeId, $days);
        return true;
    }
}
