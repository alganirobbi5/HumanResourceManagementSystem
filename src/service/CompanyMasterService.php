<?php

require_once __DIR__ . '/../common/DBConnection.php';

class CompanyMasterService
{
    private $pdo;

    public function __construct(PDO $pdo = null)
    {
        $this->pdo = $pdo ?: DBConnection::pdo();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->ensureSchema();
    }

    private function hasColumn($table, $column)
    {
        return (bool)$this->pdo->query("SHOW COLUMNS FROM " . $table . " LIKE '" . $column . "'")->fetch();
    }

    private function hasTable($table)
    {
        return (bool)$this->pdo->query("SHOW TABLES LIKE '" . $table . "'")->fetchColumn();
    }

    private function ensureSchema()
    {
        foreach (array('office_locations', 'pengaturan_jam', 'departments', 'employee_designations') as $table) {
            if (!$this->hasTable($table)) {
                continue;
            }
            if (!$this->hasColumn($table, 'company_id')) {
                $this->pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN company_id INT(11) NULL DEFAULT NULL');
            }
            $this->pdo->exec('UPDATE ' . $table . ' SET company_id = 1 WHERE company_id IS NULL');
        }
    }

    private function companyValue($companyId)
    {
        return $companyId === null ? null : (int)$companyId;
    }

    private function whereCompany($companyId, $prefix = '')
    {
        $column = $prefix . 'company_id';
        if ($companyId === null) {
            return array('', array());
        }
        return array(' WHERE ' . $column . ' = ?', array((int)$companyId));
    }

    private function seedJam($companyId)
    {
        if (!$this->hasTable('pengaturan_jam')) {
            return;
        }
        $row = $this->pdo->query('SELECT jam_masuk, jam_keluar FROM pengaturan_jam WHERE company_id = 1 ORDER BY id LIMIT 1')
            ->fetch(PDO::FETCH_ASSOC);
        $jamMasuk = $row && $row['jam_masuk'] ? $row['jam_masuk'] : '08:00:00';
        $jamKeluar = $row && $row['jam_keluar'] ? $row['jam_keluar'] : '17:00:00';
        $stmt = $this->pdo->prepare(
            'INSERT INTO pengaturan_jam (company_id, jam_masuk, jam_keluar) VALUES (?, ?, ?)'
        );
        $stmt->execute(array((int)$companyId, $jamMasuk, $jamKeluar));
    }

    private function seedMasters($companyId, $table, $column)
    {
        if (!$this->hasTable($table)) {
            return;
        }
        $defaults = $this->pdo->query(
            'SELECT ' . $column . ' FROM ' . $table . ' WHERE company_id = 1 AND status = 1 ORDER BY id LIMIT 2'
        )->fetchAll(PDO::FETCH_COLUMN);
        if (!$defaults) {
            $defaults = $table === 'departments' ? array('IT', 'Coaching') : array('Teacher', 'Programmer');
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO ' . $table . ' (' . $column . ', company_id, status) VALUES (?, ?, 1)'
        );
        foreach ($defaults as $value) {
            $stmt->execute(array($value, (int)$companyId));
        }
    }

    public function initialize($companyId)
    {
        $companyId = $this->companyValue($companyId);
        if ($companyId === null) {
            return;
        }
        if ($this->hasTable('pengaturan_jam')) {
            $check = $this->pdo->prepare('SELECT id FROM pengaturan_jam WHERE company_id = ? LIMIT 1');
            $check->execute(array((int)$companyId));
            if (!$check->fetch()) {
                $this->seedJam($companyId);
            }
        }
        foreach (array('departments' => 'name', 'employee_designations' => 'title') as $table => $column) {
            $check = $this->pdo->prepare('SELECT id FROM ' . $table . ' WHERE company_id = ? LIMIT 1');
            $check->execute(array((int)$companyId));
            if (!$check->fetch()) {
                $this->seedMasters($companyId, $table, $column);
            }
        }
    }

    public function jamFor($companyId)
    {
        if (!$this->hasTable('pengaturan_jam')) {
            return null;
        }
        $companyId = $this->companyValue($companyId);
        if ($companyId !== null) {
            $check = $this->pdo->prepare('SELECT id FROM pengaturan_jam WHERE company_id = ? LIMIT 1');
            $check->execute(array((int)$companyId));
            if (!$check->fetch()) {
                $this->seedJam($companyId);
            }
            $stmt = $this->pdo->prepare(
                'SELECT jam_masuk, jam_keluar FROM pengaturan_jam WHERE company_id = ? ORDER BY id LIMIT 1'
            );
            $stmt->execute(array((int)$companyId));
        } else {
            $stmt = $this->pdo->query('SELECT jam_masuk, jam_keluar FROM pengaturan_jam ORDER BY id LIMIT 1');
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function saveJam($companyId, $jamMasuk, $jamKeluar)
    {
        $companyId = $this->companyValue($companyId);
        if ($companyId !== null) {
            $check = $this->pdo->prepare('SELECT id FROM pengaturan_jam WHERE company_id = ? LIMIT 1');
            $check->execute(array((int)$companyId));
            $id = $check->fetchColumn();
            if ($id) {
                $stmt = $this->pdo->prepare(
                    'UPDATE pengaturan_jam SET jam_masuk = ?, jam_keluar = ? WHERE id = ?'
                );
                $stmt->execute(array($jamMasuk, $jamKeluar, (int)$id));
                return;
            }
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO pengaturan_jam (company_id, jam_masuk, jam_keluar) VALUES (?, ?, ?)'
        );
        $stmt->execute(array($companyId, $jamMasuk, $jamKeluar));
    }

    public function officeLocations($companyId)
    {
        list($where, $params) = $this->whereCompany($this->companyValue($companyId), '');
        $stmt = $this->pdo->prepare('SELECT * FROM office_locations' . $where . ' ORDER BY id');
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addOfficeLocation($companyId, $nama, $latitude, $longitude, $radius)
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO office_locations (company_id, nama_lokasi, latitude, longitude, radius_meter)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute(array(
            $this->companyValue($companyId), $nama, $latitude, $longitude, $radius
        ));
        return (int)$this->pdo->lastInsertId();
    }

    public function departments($companyId)
    {
        $companyId = $this->companyValue($companyId);
        if ($companyId !== null) {
            $check = $this->pdo->prepare('SELECT id FROM departments WHERE company_id = ? LIMIT 1');
            $check->execute(array((int)$companyId));
            if (!$check->fetch()) {
                $this->seedMasters($companyId, 'departments', 'name');
            }
            $stmt = $this->pdo->prepare('SELECT id, name FROM departments WHERE status = 1 AND company_id = ? ORDER BY name, id');
            $stmt->execute(array((int)$companyId));
        } else {
            $stmt = $this->pdo->query('SELECT id, name FROM departments WHERE status = 1 ORDER BY name, id');
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function designations($companyId)
    {
        $companyId = $this->companyValue($companyId);
        $table = $this->hasTable('employee_designations') ? 'employee_designations' : 'designations';
        $column = $table === 'designations' ? 'name' : 'title';
        if ($companyId !== null) {
            $check = $this->pdo->prepare('SELECT id FROM ' . $table . ' WHERE company_id = ? LIMIT 1');
            $check->execute(array((int)$companyId));
            if (!$check->fetch()) {
                $this->seedMasters($companyId, $table, $column);
            }
            $stmt = $this->pdo->prepare(
                'SELECT id, ' . $column . ' AS name FROM ' . $table . ' WHERE status = 1 AND company_id = ? ORDER BY ' . $column . ', id'
            );
            $stmt->execute(array((int)$companyId));
        } else {
            $stmt = $this->pdo->query(
                'SELECT id, ' . $column . ' AS name FROM ' . $table . ' WHERE status = 1 ORDER BY ' . $column . ', id'
            );
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function firstDepartment($companyId)
    {
        $rows = $this->departments($companyId);
        return $rows ? (int)$rows[0]['id'] : 0;
    }

    public function firstDesignation($companyId)
    {
        $rows = $this->designations($companyId);
        return $rows ? (int)$rows[0]['id'] : 0;
    }
}
