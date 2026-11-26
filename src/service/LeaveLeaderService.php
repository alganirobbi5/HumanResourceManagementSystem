<?php

require_once __DIR__ . '/../common/DBConnection.php';

class LeaveLeaderService
{
    const NOT_LEADER_MESSAGE = 'Hanya user yang terdaftar sebagai Leader persetujuan cuti yang dapat menyetujui atau menolak pengajuan.';

    private $pdo;

    public function __construct(PDO $pdo = null)
    {
        $this->pdo = $pdo ?: DBConnection::get();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->ensureSchema();
    }

    /** Pengaman runtime bila migrasi belum dijalankan manual. */
    private function ensureSchema()
    {
        try {
            $exists = $this->pdo->query("SHOW TABLES LIKE 'leave_leaders'")->fetchColumn();
            if (!$exists) {
                $this->pdo->exec('CREATE TABLE IF NOT EXISTS leave_leaders (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    user_id INT(11) NOT NULL UNIQUE,
                    employee_id INT(11) NULL DEFAULT NULL,
                    keterangan VARCHAR(100) NULL DEFAULT NULL,
                    jabatan VARCHAR(150) NULL DEFAULT NULL,
                    added_by INT(11) NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_leave_leaders_employee (employee_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            }
            if ($this->pdo->query("SHOW TABLES LIKE 'leave_leaders'")->fetchColumn()
                && !$this->pdo->query("SHOW COLUMNS FROM leave_leaders LIKE 'jabatan'")->fetch()) {
                $this->pdo->exec('ALTER TABLE leave_leaders ADD COLUMN jabatan VARCHAR(150) NULL DEFAULT NULL AFTER keterangan');
            }
            $columns = array(
                'leader_user_id' => 'INT(11) NULL DEFAULT NULL',
                'approved_by_user_id' => 'INT(11) NULL DEFAULT NULL',
                'approved_by_name' => 'VARCHAR(150) NULL DEFAULT NULL',
                'catatan_leader' => 'TEXT NULL',
                'decided_at' => 'DATETIME NULL DEFAULT NULL',
            );
            foreach (array('leave_requests', 'leaves', 'employee_leavs') as $table) {
                if (!$this->pdo->query("SHOW TABLES LIKE '" . $table . "'")->fetchColumn()) {
                    continue;
                }
                foreach ($columns as $name => $definition) {
                    if (!$this->pdo->query("SHOW COLUMNS FROM " . $table . " LIKE '" . $name . "'")->fetch()) {
                        $this->pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $name . ' ' . $definition);
                    }
                }
            }
        } catch (PDOException $e) {
            // Biarkan method yang memakai tabel yang melaporkan kegagalan.
        }
    }

    public function listLeaders($companyId = null)
    {
        $sql = 'SELECT ll.id, ll.user_id, ll.employee_id, u.username,
                       CONCAT_WS(" ", e.first_name, e.last_name) AS nama_lengkap,
                       ll.keterangan,
                       COALESCE(NULLIF(ll.jabatan, ""), ll.keterangan) AS jabatan,
                       ll.created_at
                FROM leave_leaders ll
                LEFT JOIN users u ON u.id = ll.user_id
                LEFT JOIN employees e ON e.id = COALESCE(ll.employee_id, u.employee_id)';
        $params = array();
        if ($companyId !== null) {
            $sql .= ' WHERE COALESCE(ll.company_id, u.company_id) = ?';
            $params[] = (int)$companyId;
        }
        $stmt = $this->pdo->prepare($sql . ' ORDER BY ll.id DESC');
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listEligibleUsers($companyId = null)
    {
        $sql = 'SELECT u.id AS user_id, u.username, u.employee_id,
                       CONCAT_WS(" ", e.first_name, e.last_name) AS nama_lengkap
                FROM users u
                LEFT JOIN employees e ON e.id = u.employee_id
                LEFT JOIN leave_leaders ll ON ll.user_id = u.id
                WHERE u.is_active = 1 AND u.employee_id IS NOT NULL AND ll.id IS NULL';
        $params = array();
        if ($companyId !== null) {
            $sql .= ' AND u.company_id = ?';
            $params[] = (int)$companyId;
        }
        $stmt = $this->pdo->prepare($sql . ' ORDER BY u.id');
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addLeader($targetUserId, $hrUserId, $keterangan = null, $jabatan = null, $companyId = null)
    {
        $targetUserId = (int)$targetUserId;
        $jabatan = trim((string)$jabatan);
        if ($jabatan === '') {
            throw new InvalidArgumentException('jabatan wajib diisi', 422);
        }
        $userSql = 'SELECT id, employee_id FROM users WHERE id = ? AND is_active = 1';
        $userParams = array($targetUserId);
        if ($companyId !== null) {
            $userSql .= ' AND company_id = ?';
            $userParams[] = (int)$companyId;
        }
        $stmt = $this->pdo->prepare($userSql . ' LIMIT 1');
        $stmt->execute($userParams);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            throw new InvalidArgumentException('User tidak ditemukan atau tidak aktif', 422);
        }
        $stmt = $this->pdo->prepare('SELECT id FROM leave_leaders WHERE user_id = ? LIMIT 1');
        $stmt->execute([$targetUserId]);
        if ($stmt->fetch()) {
            throw new RuntimeException('User sudah terdaftar sebagai Leader', 409);
        }
        $columns = 'user_id, employee_id, keterangan, jabatan, added_by';
        $values = array($targetUserId, $user['employee_id'], $keterangan, $jabatan, (int)$hrUserId);
        if ($this->pdo->query("SHOW COLUMNS FROM leave_leaders LIKE 'company_id'")->fetch()) {
            $columns .= ', company_id';
            $values[] = $companyId;
        }
        $this->pdo->prepare('INSERT INTO leave_leaders (' . $columns . ') VALUES (' .
            implode(', ', array_fill(0, count($values), '?')) . ')')->execute($values);
        return $this->leaderRow($targetUserId, $companyId);
    }

    private function leaderRow($userId, $companyId = null)
    {
        $sql = 'SELECT ll.id, ll.user_id, ll.employee_id, u.username,
                       CONCAT_WS(" ", e.first_name, e.last_name) AS nama_lengkap,
                       ll.keterangan,
                       COALESCE(NULLIF(ll.jabatan, ""), ll.keterangan) AS jabatan,
                       ll.added_by, ll.created_at
                FROM leave_leaders ll
                LEFT JOIN users u ON u.id = ll.user_id
                LEFT JOIN employees e ON e.id = COALESCE(ll.employee_id, u.employee_id)
                WHERE ll.user_id = ?';
        $params = array((int)$userId);
        if ($companyId !== null) {
            $sql .= ' AND COALESCE(ll.company_id, u.company_id) = ?';
            $params[] = (int)$companyId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function removeLeader($leaderIdOrUserId, $companyId = null)
    {
        $value = (int)$leaderIdOrUserId;
        $sql = 'SELECT user_id FROM leave_leaders WHERE id = ?';
        $params = array($value);
        if ($companyId !== null) {
            $sql .= ' AND COALESCE(company_id, (SELECT company_id FROM users WHERE id = user_id)) = ?';
            $params[] = (int)$companyId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $this->pdo->prepare('DELETE FROM leave_leaders WHERE id = ?')->execute([$value]);
            return true;
        }
        $deleteSql = 'DELETE FROM leave_leaders WHERE user_id = ?';
        $deleteParams = array($value);
        if ($companyId !== null) {
            $deleteSql .= ' AND COALESCE(company_id, (SELECT company_id FROM users WHERE id = user_id)) = ?';
            $deleteParams[] = (int)$companyId;
        }
        $stmt = $this->pdo->prepare($deleteSql);
        $stmt->execute($deleteParams);
        return $stmt->rowCount() > 0;
    }

    public function isLeader($userId, $companyId = null)
    {
        if (!$userId) {
            return false;
        }
        try {
            if ($companyId !== null) {
                $stmt = $this->pdo->prepare(
                    'SELECT 1 FROM leave_leaders ll LEFT JOIN users u ON u.id = ll.user_id
                     WHERE ll.user_id = ? AND COALESCE(ll.company_id, u.company_id) = ? LIMIT 1'
                );
                $stmt->execute(array((int)$userId, (int)$companyId));
            } else {
                $stmt = $this->pdo->prepare('SELECT 1 FROM leave_leaders WHERE user_id = ? LIMIT 1');
                $stmt->execute([(int)$userId]);
            }
            return (bool)$stmt->fetchColumn();
        } catch (PDOException $e) {
            return false;
        }
    }

    public function leaderName($userId, $companyId = null)
    {
        $sql = 'SELECT COALESCE(NULLIF(CONCAT_WS(" ", e.first_name, e.last_name), ""), u.username) AS nama
                FROM users u LEFT JOIN employees e ON e.id = u.employee_id
                WHERE u.id = ?';
        $params = array((int)$userId);
        if ($companyId !== null) {
            $sql .= ' AND u.company_id = ?';
            $params[] = (int)$companyId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $name = $stmt->fetchColumn();
        return $name ? (string)$name : null;
    }
}
