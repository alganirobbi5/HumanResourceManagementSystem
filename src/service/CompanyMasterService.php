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
        $defaults = $table === 'departments'
            ? array('Operasional', 'HR & Umum', 'IT & Sistem', 'Keuangan')
            : array('Staff', 'Supervisor', 'Manager', 'Admin');

        $creatorColumn = $table === 'departments' ? 'create_by' : 'created_by';
        $columns = $column . ', company_id, status';
        $placeholders = '?, ?, 1';
        $extra = array();
        if ($this->hasColumn($table, $creatorColumn)) {
            $columns .= ', ' . $creatorColumn;
            $placeholders .= ', ?';
            $extra[] = 'system';
        }
        if ($this->hasColumn($table, 'create_date')) {
            $columns .= ', create_date';
            $placeholders .= ', NOW()';
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO ' . $table . ' (' . $columns . ') VALUES (' . $placeholders . ')'
        );
        foreach ($defaults as $value) {
            $stmt->execute(array_merge(array($value, (int)$companyId), $extra));
        }
    }

    private function hasActiveMasters($table, $companyId)
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM ' . $table . ' WHERE company_id = ? AND status = 1 LIMIT 1'
        );
        $stmt->execute(array((int)$companyId));
        return $stmt->fetch() !== false;
    }

    private function designationTable()
    {
        return $this->hasTable('employee_designations') ? 'employee_designations' : 'designations';
    }

    private function addMaster($table, $column, $companyId, $value, $creatorColumn, $creator)
    {
        $value = trim((string)$value);
        if ($value === '' || strlen($value) > 30) {
            throw new InvalidArgumentException($column . ' wajib diisi (maksimal 30 karakter)');
        }
        if ($companyId === null) {
            throw new InvalidArgumentException('company_id tidak tersedia');
        }
        $duplicate = $this->pdo->prepare(
            'SELECT id FROM ' . $table . '
             WHERE company_id = ? AND status = 1 AND LOWER(' . $column . ') = LOWER(?) LIMIT 1'
        );
        $duplicate->execute(array((int)$companyId, $value));
        if ($duplicate->fetch()) {
            throw new RuntimeException('Data sudah terdaftar', 409);
        }

        $columns = $column . ', company_id, status';
        $placeholders = '?, ?, 1';
        $values = array($value, (int)$companyId);
        if ($this->hasColumn($table, $creatorColumn)) {
            $columns .= ', ' . $creatorColumn;
            $placeholders .= ', ?';
            $values[] = $creator !== null ? trim((string)$creator) : 'system';
        }
        if ($this->hasColumn($table, 'create_date')) {
            $columns .= ', create_date';
            $placeholders .= ', NOW()';
        }
        $this->pdo->prepare(
            'INSERT INTO ' . $table . ' (' . $columns . ') VALUES (' . $placeholders . ')'
        )->execute($values);
        return (int)$this->pdo->lastInsertId();
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
        $this->departments($companyId);
        $this->designations($companyId);
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

    public function officeLocation($id, $companyId = null)
    {
        $sql = 'SELECT * FROM office_locations WHERE id = ?';
        $params = array((int)$id);
        if ($companyId !== null) {
            $sql .= ' AND company_id = ?';
            $params[] = (int)$companyId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateOfficeLocation($id, $nama, $latitude, $longitude, $radius, $companyId = null)
    {
        $sql = 'UPDATE office_locations SET nama_lokasi = ?, latitude = ?, longitude = ?, radius_meter = ? WHERE id = ?';
        $params = array($nama, $latitude, $longitude, $radius, (int)$id);
        if ($companyId !== null) {
            $sql .= ' AND company_id = ?';
            $params[] = (int)$companyId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function removeOfficeLocation($id, $companyId = null)
    {
        $sql = 'DELETE FROM office_locations WHERE id = ?';
        $params = array((int)$id);
        if ($companyId !== null) {
            $sql .= ' AND company_id = ?';
            $params[] = (int)$companyId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function formatLocation($row)
    {
        if (!$row) {
            return null;
        }
        return array(
            'id' => (int)$row['id'],
            'company_id' => $row['company_id'] !== null ? (int)$row['company_id'] : null,
            'name' => (string)$row['nama_lokasi'],
            'nama_lokasi' => (string)$row['nama_lokasi'],
            'latitude' => (string)$row['latitude'],
            'longitude' => (string)$row['longitude'],
            'radius' => (string)$row['radius_meter'],
            'radius_meter' => (string)$row['radius_meter'],
        );
    }

    public function departments($companyId)
    {
        $companyId = $this->companyValue($companyId);
        if ($companyId !== null) {
            if (!$this->hasActiveMasters('departments', $companyId)) {
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
        $table = $this->designationTable();
        $column = $table === 'designations' ? 'name' : 'title';
        if ($companyId !== null) {
            if (!$this->hasActiveMasters($table, $companyId)) {
                $this->seedMasters($companyId, $table, $column);
            }
            $stmt = $this->pdo->prepare(
                'SELECT id, ' . $column . ' AS title, ' . $column . ' AS name FROM ' . $table . '
                 WHERE status = 1 AND company_id = ? ORDER BY ' . $column . ', id'
            );
            $stmt->execute(array((int)$companyId));
        } else {
            $stmt = $this->pdo->query(
                'SELECT id, ' . $column . ' AS title, ' . $column . ' AS name FROM ' . $table . '
                 WHERE status = 1 ORDER BY ' . $column . ', id'
            );
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addDepartment($companyId, $name, $createBy = null)
    {
        return $this->addMaster('departments', 'name', $companyId, $name, 'create_by', $createBy);
    }

    public function addDesignation($companyId, $title, $createdBy = null)
    {
        $table = $this->designationTable();
        $column = $table === 'designations' ? 'name' : 'title';
        return $this->addMaster($table, $column, $companyId, $title, 'created_by', $createdBy);
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
