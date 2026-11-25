<?php

require_once __DIR__ . '/../common/DBConnection.php';
require_once __DIR__ . '/../service/LeaveLeaderService.php';

class IzinKeluarService
{
    private $pdo;
    private $leaders;

    public function __construct(PDO $pdo = null)
    {
        $this->pdo = $pdo ?: DBConnection::get();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->ensureTable();
        $this->leaders = new LeaveLeaderService($this->pdo);
    }

    /** Pastikan tabel dan kolom tersedia walau migrasi belum dijalankan manual. */
    private function ensureTable()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS izin_keluar (
            id INT(11) NOT NULL AUTO_INCREMENT,
            employee_id INT(11) NOT NULL,
            tanggal DATE NOT NULL,
            jam_keluar TIME NOT NULL,
            jam_kembali TIME NULL DEFAULT NULL,
            kembali_ke_kantor TINYINT(1) NOT NULL DEFAULT 1,
            kategori VARCHAR(100) NOT NULL DEFAULT "Keperluan Pribadi",
            alasan TEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT "pending",
            approved_by INT(11) NULL DEFAULT NULL,
            catatan_hr TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_izin_keluar_employee (employee_id),
            KEY idx_izin_keluar_status (status),
            KEY idx_izin_keluar_tanggal (tanggal)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
        try {
            $exists = $this->pdo->query("SHOW TABLES LIKE 'izin_keluar'");
            if (!$exists || !$exists->fetch()) {
                $this->pdo->exec($sql);
            }
            $columns = array(
                'leader_user_id' => 'INT(11) NULL DEFAULT NULL',
                'approved_by_user_id' => 'INT(11) NULL DEFAULT NULL',
                'approved_by_name' => 'VARCHAR(150) NULL DEFAULT NULL',
                'catatan_leader' => 'TEXT NULL',
                'decided_at' => 'DATETIME NULL DEFAULT NULL',
            );
            foreach ($columns as $name => $definition) {
                if (!$this->pdo->query("SHOW COLUMNS FROM izin_keluar LIKE '" . $name . "'")->fetch()) {
                    $this->pdo->exec('ALTER TABLE izin_keluar ADD COLUMN ' . $name . ' ' . $definition);
                }
            }
        } catch (PDOException $e) {
            // Biarkan method yang dipakai yang melaporkan kegagalan.
        }
    }

    private function validDate($value)
    {
        if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            return false;
        }
        return checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
    }

    private function validTime($value)
    {
        if (!is_string($value) || !preg_match('/^(\d{2}):(\d{2})$/', $value, $m)) {
            return false;
        }
        return (int)$m[1] >= 0 && (int)$m[1] <= 23 && (int)$m[2] >= 0 && (int)$m[2] <= 59;
    }

    private function baseSelect()
    {
        return 'SELECT i.*, e.first_name, e.last_name, e.user_name, e.department_id,
                CONCAT_WS(" ", lu.first_name, lu.last_name) AS target_leader_name
                FROM izin_keluar i
                LEFT JOIN employees e ON e.id = i.employee_id
                LEFT JOIN users luser ON luser.id = i.leader_user_id
                LEFT JOIN employees lu ON lu.id = luser.employee_id';
    }

    private function find($id)
    {
        $stmt = $this->pdo->prepare($this->baseSelect() . ' WHERE i.id = ? LIMIT 1');
        $stmt->execute([(int)$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createRequest($employeeId, array $input)
    {
        $employeeId = (int)$employeeId;
        if ($employeeId <= 0) {
            throw new InvalidArgumentException('Karyawan tidak valid', 422);
        }

        $leaderUserId = isset($input['leader_user_id']) ? (int)$input['leader_user_id'] : 0;
        if ($leaderUserId <= 0 || !$this->leaders->isLeader($leaderUserId)) {
            throw new InvalidArgumentException('Leader persetujuan izin keluar tidak valid', 422);
        }

        $tanggal = isset($input['tanggal']) ? trim((string)$input['tanggal']) : '';
        $jamKeluar = isset($input['jam_keluar']) ? trim((string)$input['jam_keluar']) : '';
        $jamKembali = isset($input['jam_kembali']) ? trim((string)$input['jam_kembali']) : '';
        $kembali = array_key_exists('kembali_ke_kantor', $input)
            ? ((int)$input['kembali_ke_kantor'] === 1 ? 1 : 0) : 1;
        $kategori = isset($input['kategori']) ? trim((string)$input['kategori']) : '';
        $alasan = isset($input['alasan']) ? trim((string)$input['alasan']) : '';

        if (!$this->validDate($tanggal)) {
            throw new InvalidArgumentException('Format tanggal tidak valid (YYYY-MM-DD)', 422);
        }
        if (!$this->validTime($jamKeluar)) {
            throw new InvalidArgumentException('Format jam_keluar tidak valid (HH:MM)', 422);
        }
        if ($kembali === 1 && $jamKembali === '') {
            throw new InvalidArgumentException('jam_kembali wajib diisi jika kembali ke kantor', 422);
        }
        if ($jamKembali !== '' && !$this->validTime($jamKembali)) {
            throw new InvalidArgumentException('Format jam_kembali tidak valid (HH:MM)', 422);
        }
        if ($kategori === '' || $alasan === '') {
            throw new InvalidArgumentException('Kategori dan alasan wajib diisi', 422);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO izin_keluar
                (employee_id, tanggal, jam_keluar, jam_kembali, kembali_ke_kantor,
                 kategori, alasan, status, leader_user_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, "pending", ?)'
        );
        $stmt->execute(array(
            $employeeId,
            $tanggal,
            $jamKeluar . ':00',
            $jamKembali !== '' ? $jamKembali . ':00' : null,
            $kembali,
            $kategori,
            $alasan,
            $leaderUserId,
        ));
        return $this->find((int)$this->pdo->lastInsertId());
    }

    public function listByEmployee($employeeId, $status = null)
    {
        $sql = $this->baseSelect() . ' WHERE i.employee_id = ?';
        $params = array((int)$employeeId);
        if ($status !== null && $status !== '') {
            $sql .= ' AND i.status = ?';
            $params[] = (string)$status;
        }
        $sql .= ' ORDER BY i.tanggal DESC, i.jam_keluar DESC, i.id DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listForLeader($leaderUserId, $status = null, $tanggal = null)
    {
        $sql = $this->baseSelect() . ' WHERE i.leader_user_id = ?';
        $params = array((int)$leaderUserId);
        if ($status !== null && $status !== '') {
            $sql .= ' AND i.status = ?';
            $params[] = (string)$status;
        }
        if ($tanggal !== null && $tanggal !== '') {
            $sql .= ' AND i.tanggal = ?';
            $params[] = (string)$tanggal;
        }
        $sql .= ' ORDER BY i.tanggal DESC, i.jam_keluar DESC, i.id DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listAll($status = null, $tanggal = null)
    {
        $sql = $this->baseSelect();
        $conditions = array();
        $params = array();
        if ($status !== null && $status !== '') {
            $conditions[] = 'i.status = ?';
            $params[] = (string)$status;
        }
        if ($tanggal !== null && $tanggal !== '') {
            $conditions[] = 'i.tanggal = ?';
            $params[] = (string)$tanggal;
        }
        if ($conditions) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY i.tanggal DESC, i.jam_keluar DESC, i.id DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function decide($id, $decision, $leaderUserId, $catatanLeader = null)
    {
        $decision = strtolower(trim((string)$decision));
        if (!in_array($decision, array('approved', 'rejected'), true)) {
            throw new InvalidArgumentException('Keputusan harus approved atau rejected', 422);
        }
        $leaderUserId = (int)$leaderUserId;
        if (!$this->leaders->isLeader($leaderUserId)) {
            throw new RuntimeException(LeaveLeaderService::NOT_LEADER_MESSAGE, 403);
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT id, status, leader_user_id FROM izin_keluar WHERE id = ? FOR UPDATE');
            $stmt->execute([(int)$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $this->pdo->rollBack();
                throw new InvalidArgumentException('Pengajuan izin keluar tidak ditemukan', 404);
            }
            if ($row['status'] !== 'pending') {
                $this->pdo->rollBack();
                throw new RuntimeException('Pengajuan sudah diproses sebelumnya', 409);
            }
            if (!empty($row['leader_user_id']) && (int)$row['leader_user_id'] !== $leaderUserId) {
                $this->pdo->rollBack();
                throw new RuntimeException('Pengajuan izin keluar ini ditujukan kepada Leader lain', 403);
            }

            $leaderName = $this->leaders->leaderName($leaderUserId);
            $this->pdo->prepare(
                'UPDATE izin_keluar
                 SET status = ?, approved_by = ?, catatan_hr = ?,
                     approved_by_user_id = ?, approved_by_name = ?, catatan_leader = ?,
                     decided_at = NOW()
                 WHERE id = ? AND status = "pending"'
            )->execute(array(
                $decision,
                $leaderUserId,
                $catatanLeader,
                $leaderUserId,
                $leaderName,
                $catatanLeader,
                (int)$id,
            ));
            $this->pdo->commit();
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
        return $this->find((int)$id);
    }
}
