<?php

require_once __DIR__ . '/../common/DBConnection.php';
require_once __DIR__ . '/../common/Config.php';
require_once __DIR__ . '/NotificationService.php';
require_once __DIR__ . '/AuditLog.php';

class LeaveService
{
    private $db;
    private $notifications;
    private $audit;

    public function __construct(DBConnection $db = null)
    {
        $this->db = $db ?: new DBConnection();
        $this->ensureTables();
        $this->notifications = new NotificationService($this->db);
        $this->audit = new AuditLog($this->db);
    }

    /** Pastikan tabel fitur cuti tersedia walau migrasi belum dijalankan di produksi. */
    private function ensureTables()
    {
        $tables = array(
            'leave_balances' => 'CREATE TABLE IF NOT EXISTS leave_balances (
                id INT(11) NOT NULL AUTO_INCREMENT,
                employee_id INT(11) NOT NULL,
                leave_type VARCHAR(30) NOT NULL DEFAULT "annual",
                entitlement_year YEAR NOT NULL,
                entitled_days DECIMAL(5,1) NOT NULL DEFAULT 0,
                used_days DECIMAL(5,1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_leave_balance (employee_id, leave_type, entitlement_year)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'leave_requests' => 'CREATE TABLE IF NOT EXISTS leave_requests (
                id INT(11) NOT NULL AUTO_INCREMENT,
                employee_id INT(11) NOT NULL,
                reason TEXT NOT NULL,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                total_days DECIMAL(5,1) NOT NULL,
                replacement_employee_id INT(11) DEFAULT NULL,
                status ENUM("pending","approved","rejected") NOT NULL DEFAULT "pending",
                submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                reviewed_by INT(11) DEFAULT NULL,
                reviewed_at TIMESTAMP NULL DEFAULT NULL,
                rejection_reason TEXT,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_leave_requests_employee (employee_id),
                KEY idx_leave_requests_status (status),
                KEY idx_leave_requests_dates (start_date, end_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'notifications' => 'CREATE TABLE IF NOT EXISTS notifications (
                id INT(11) NOT NULL AUTO_INCREMENT,
                recipient_user_id INT(11) NOT NULL,
                title VARCHAR(150) NOT NULL,
                message TEXT NOT NULL,
                type VARCHAR(50) NOT NULL DEFAULT "general",
                related_entity_type VARCHAR(50) DEFAULT NULL,
                related_entity_id INT(11) DEFAULT NULL,
                read_at TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_notifications_recipient (recipient_user_id, read_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'audit_logs' => 'CREATE TABLE IF NOT EXISTS audit_logs (
                id INT(11) NOT NULL AUTO_INCREMENT,
                actor_user_id INT(11) DEFAULT NULL,
                action VARCHAR(50) NOT NULL,
                entity_type VARCHAR(50) NOT NULL,
                entity_id INT(11) DEFAULT NULL,
                before_data TEXT,
                after_data TEXT,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_audit_entity (entity_type, entity_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        );

        foreach ($tables as $name => $sql) {
            try {
                $check = $this->db->getPdo();
                if (!$check) {
                    continue;
                }
                $exists = $check->query("SHOW TABLES LIKE '" . $name . "'");
                if (!$exists || !$exists->fetch()) {
                    $check->exec($sql);
                }
            } catch (PDOException $e) {
                // Biarkan endpoint lain yang melaporkan error bila memang gagal.
            }
        }
    }

    /** Saldo cuti karyawan; otomatis dibuat dengan jatah default bila belum ada. */
    public function getBalance($employeeId, $year = null)
    {
        $year = $year ?: (int)Config::companyNow()->format('Y');
        $row = $this->db->selectOne(
            'SELECT * FROM leave_balances WHERE employee_id = ? AND leave_type = ? AND entitlement_year = ?',
            array((int)$employeeId, Config::LEAVE_TYPE_DEFAULT, $year)
        );
        if (!$row) {
            $this->db->insert(
                'INSERT INTO leave_balances (employee_id, leave_type, entitlement_year, entitled_days, used_days)
                 VALUES (?, ?, ?, ?, 0)',
                array((int)$employeeId, Config::LEAVE_TYPE_DEFAULT, $year, Config::LEAVE_ENTITLEMENT_DEFAULT)
            );
            $row = $this->db->selectOne(
                'SELECT * FROM leave_balances WHERE employee_id = ? AND leave_type = ? AND entitlement_year = ?',
                array((int)$employeeId, Config::LEAVE_TYPE_DEFAULT, $year)
            );
        }
        $row['remaining_days'] = (float)$row['entitled_days'] - (float)$row['used_days'];
        return $row;
    }

    /** Tambah jatah kuota cuti karyawan oleh HR. */
    public function addEntitlement($employeeId, $days, $year = null)
    {
        $employeeId = (int)$employeeId;
        $days = (float)$days;
        $year = $year ?: (int)Config::companyNow()->format('Y');

        if ($employeeId <= 0) {
            throw new InvalidArgumentException('Karyawan wajib dipilih');
        }
        if ($days <= 0 || $days > 90) {
            throw new InvalidArgumentException('Jumlah hari harus antara 0,5 sampai 90');
        }

        $employee = $this->db->selectOne(
            'SELECT id, first_name, last_name FROM employees WHERE id = ? AND status = 1',
            array($employeeId)
        );
        if (!$employee) {
            throw new InvalidArgumentException('Karyawan tidak ditemukan atau tidak aktif');
        }

        $balance = $this->getBalance($employeeId, $year);
        $this->db->run(
            'UPDATE leave_balances SET entitled_days = entitled_days + ?, updated_at = NOW() WHERE id = ?',
            array($days, (int)$balance['id'])
        );

        return $this->getBalance($employeeId, $year);
    }

    /**
     * Hitung jumlah hari cuti di sisi server (aturan via Config::LEAVE_DAY_COUNT_RULE).
     */
    public function calculateDays($startDate, $endDate)
    {
        $start = DateTimeImmutable::createFromFormat('Y-m-d', $startDate);
        $end = DateTimeImmutable::createFromFormat('Y-m-d', $endDate);
        if (!$start || !$end || $start->format('Y-m-d') !== $startDate || $end->format('Y-m-d') !== $endDate) {
            throw new InvalidArgumentException('Format tanggal tidak valid');
        }
        if ($end < $start) {
            throw new InvalidArgumentException('Tanggal selesai tidak boleh lebih awal dari tanggal mulai');
        }

        if (Config::LEAVE_DAY_COUNT_RULE === 'kerja') {
            $days = 0;
            $cursor = $start;
            while ($cursor <= $end) {
                $dow = (int)$cursor->format('N');
                if ($dow <= 5) {
                    $days++;
                }
                $cursor = $cursor->modify('+1 day');
            }
            return $days;
        }

        // Hari kalender (default, konsisten sampai kebijakan perusahaan ditetapkan)
        return (int)$start->diff($end)->days + 1;
    }

    public function createRequest($employeeId, $reason, $startDate, $endDate, $replacementEmployeeId)
    {
        $reason = trim((string)$reason);
        if ($reason === '') {
            throw new InvalidArgumentException('Alasan cuti wajib diisi');
        }
        if (!$startDate || !$endDate) {
            throw new InvalidArgumentException('Tanggal mulai dan tanggal selesai wajib diisi');
        }
        if (!$replacementEmployeeId) {
            throw new InvalidArgumentException('Nama pengganti wajib dipilih');
        }

        $totalDays = $this->calculateDays($startDate, $endDate); // validasi tanggal + hitung ulang
        if ($totalDays <= 0) {
            throw new InvalidArgumentException('Jumlah hari cuti harus lebih dari nol');
        }

        if (!Config::ALLOW_PAST_START_DATE && $startDate < Config::companyToday()) {
            throw new InvalidArgumentException('Tanggal mulai tidak boleh di masa lampau');
        }

        // Pengganti harus karyawan aktif dan bukan diri sendiri
        $replacement = $this->db->selectOne(
            'SELECT id, first_name, last_name FROM employees WHERE id = ? AND status = 1',
            array((int)$replacementEmployeeId)
        );
        if (!$replacement) {
            throw new InvalidArgumentException('Karyawan pengganti tidak ditemukan atau tidak aktif');
        }
        if ((int)$replacement['id'] === (int)$employeeId) {
            throw new InvalidArgumentException('Karyawan pengganti tidak boleh diri sendiri');
        }

        // Cek tumpang tindih dengan pengajuan yang sudah disetujui
        if (Config::PREVENT_LEAVE_OVERLAP) {
            $overlap = $this->db->selectOne(
                "SELECT id FROM leave_requests
                 WHERE employee_id = ? AND status = 'approved'
                   AND start_date <= ? AND end_date >= ?
                 LIMIT 1",
                array((int)$employeeId, $endDate, $startDate)
            );
            if ($overlap) {
                throw new InvalidArgumentException('Rentang tanggal cuti bertabrakan dengan pengajuan yang sudah disetujui');
            }
        }

        // Kuota harus mencukupi saat pengajuan dibuat (saldo tahun pada tanggal mulai cuti)
        $balance = $this->getBalance($employeeId, (int)date('Y', strtotime($startDate)));
        if ((float)$balance['remaining_days'] < $totalDays) {
            throw new InvalidArgumentException(
                'Sisa kuota cuti tidak mencukupi (sisa ' . $balance['remaining_days'] . ' hari, diajukan ' . $totalDays . ' hari)'
            );
        }

        $now = Config::companyNow()->format('Y-m-d H:i:s');
        $id = $this->db->insert(
            'INSERT INTO leave_requests
                (employee_id, reason, start_date, end_date, total_days, replacement_employee_id,
                 status, submitted_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array(
                (int)$employeeId,
                $reason,
                $startDate,
                $endDate,
                $totalDays,
                (int)$replacementEmployeeId,
                'pending',
                $now,
                $now,
                $now
            )
        );

        $this->notifications->notifyHr(
            'Pengajuan Cuti Baru',
            'Terdapat pengajuan cuti baru menunggu persetujuan Anda.',
            'leave_request',
            'leave_request',
            $id
        );
        $this->audit->record(null, 'leave_request.create', 'leave_request', $id, null, array(
            'employee_id' => (int)$employeeId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_days' => $totalDays
        ));

        return $this->detail($id);
    }

    private function baseSelect($extraJoin = '')
    {
        return 'SELECT lr.*,
                       e.first_name, e.last_name, e.user_name AS employee_nik,
                       e.department_id, e.designation_id,
                       d.name AS department_name, dg.title AS designation_name,
                       r.first_name AS replacement_first_name, r.last_name AS replacement_last_name,
                       a.first_name AS reviewer_first_name, a.last_name AS reviewer_last_name
                FROM leave_requests lr
                LEFT JOIN employees e ON e.id = lr.employee_id
                LEFT JOIN departments d ON d.id = e.department_id
                LEFT JOIN employee_designations dg ON dg.id = e.designation_id
                LEFT JOIN employees r ON r.id = lr.replacement_employee_id
                LEFT JOIN users au ON au.id = lr.reviewed_by
                LEFT JOIN employees a ON a.id = au.employee_id'
                . $extraJoin;
    }

    public function detail($id)
    {
        return $this->db->selectOne($this->baseSelect(' WHERE lr.id = ?'), array((int)$id));
    }

    public function mine($employeeId, $page = 1, $perPage = 10)
    {
        $page = max(1, (int)$page);
        $perPage = min(50, max(1, (int)$perPage));
        $offset = ($page - 1) * $perPage;

        $totalRow = $this->db->selectOne(
            'SELECT COUNT(id) AS total FROM leave_requests WHERE employee_id = ?',
            array((int)$employeeId)
        );
        $rows = $this->db->selectAll(
            $this->baseSelect(' WHERE lr.employee_id = ?')
            . ' ORDER BY lr.submitted_at DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset,
            array((int)$employeeId)
        );

        return array(
            'items' => $rows,
            'total' => $totalRow ? (int)$totalRow['total'] : 0,
            'page' => $page,
            'per_page' => $perPage
        );
    }

    public function pending($page = 1, $perPage = 10)
    {
        $page = max(1, (int)$page);
        $perPage = min(50, max(1, (int)$perPage));
        $offset = ($page - 1) * $perPage;

        $totalRow = $this->db->selectOne("SELECT COUNT(id) AS total FROM leave_requests WHERE status = 'pending'");
        $rows = $this->db->selectAll(
            $this->baseSelect(" WHERE lr.status = 'pending'")
            . ' ORDER BY lr.submitted_at ASC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset
        );

        return array(
            'items' => $rows,
            'total' => $totalRow ? (int)$totalRow['total'] : 0,
            'page' => $page,
            'per_page' => $perPage
        );
    }

    public function all($filters = array(), $page = 1, $perPage = 10)
    {
        $page = max(1, (int)$page);
        $perPage = min(50, max(1, (int)$perPage));
        $offset = ($page - 1) * $perPage;

        $where = array();
        $params = array();
        if (!empty($filters['status']) && in_array($filters['status'], array('pending', 'approved', 'rejected'), true)) {
            $where[] = 'lr.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['keyword'])) {
            $where[] = '(e.first_name LIKE ? OR e.last_name LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $totalRow = $this->db->selectOne(
            'SELECT COUNT(lr.id) AS total FROM leave_requests lr
             LEFT JOIN employees e ON e.id = lr.employee_id' . $whereSql,
            $params
        );
        $rows = $this->db->selectAll(
            $this->baseSelect($whereSql) . ' ORDER BY lr.submitted_at DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset,
            $params
        );

        return array(
            'items' => $rows,
            'total' => $totalRow ? (int)$totalRow['total'] : 0,
            'page' => $page,
            'per_page' => $perPage
        );
    }

    public function approve($id, $approverUserId)
    {
        $this->db->beginTransaction();
        try {
            // Kunci baris agar approve bersamaan tidak membuat kuota negatif
            $row = $this->db->selectOne(
                'SELECT * FROM leave_requests WHERE id = ? FOR UPDATE',
                array((int)$id)
            );
            if (!$row) {
                throw new RuntimeException('Pengajuan cuti tidak ditemukan');
            }
            if ($row['status'] !== 'pending') {
                throw new RuntimeException('Pengajuan sudah diproses sebelumnya');
            }

            $balance = $this->db->selectOne(
                'SELECT * FROM leave_balances WHERE employee_id = ? AND leave_type = ? AND entitlement_year = ? FOR UPDATE',
                array((int)$row['employee_id'], Config::LEAVE_TYPE_DEFAULT, (int)date('Y', strtotime($row['start_date'])))
            );
            if (!$balance) {
                throw new RuntimeException('Saldo cuti karyawan tidak ditemukan');
            }

            $remaining = (float)$balance['entitled_days'] - (float)$balance['used_days'];
            $days = (float)$row['total_days'];
            if ($remaining < $days) {
                throw new RuntimeException('Sisa kuota cuti tidak mencukupi untuk menyetujui pengajuan ini');
            }

            $now = Config::companyNow()->format('Y-m-d H:i:s');
            $this->db->run(
                "UPDATE leave_requests
                 SET status = 'approved', reviewed_by = ?, reviewed_at = ?, rejection_reason = NULL, updated_at = ?
                 WHERE id = ? AND status = 'pending'",
                array((int)$approverUserId, $now, $now, (int)$id)
            );
            $this->db->run(
                'UPDATE leave_balances SET used_days = used_days + ?, updated_at = ? WHERE id = ?',
                array($days, $now, (int)$balance['id'])
            );

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }

        $detail = $this->detail($id);
        $period = $row['start_date'] . ' s/d ' . $row['end_date'];

        $this->notifications->notifyEmployee(
            $row['employee_id'],
            'Pengajuan Cuti Disetujui',
            'Pengajuan cuti Anda (' . $period . ') telah disetujui.',
            'leave_request',
            'leave_request',
            (int)$id
        );
        if ($row['replacement_employee_id']) {
            $this->notifications->notifyEmployee(
                $row['replacement_employee_id'],
                'Anda Ditunjuk Sebagai Pegawai Pengganti',
                'Anda ditunjuk sebagai pengganti cuti ' . $detail['first_name'] . ' ' . $detail['last_name']
                    . ' pada periode ' . $period . '.',
                'leave_request',
                'leave_request',
                (int)$id
            );
        }
        $this->audit->record($approverUserId, 'leave_request.approve', 'leave_request', (int)$id, $row, array(
            'status' => 'approved',
            'used_days_added' => $days
        ));

        return $this->detail($id);
    }

    public function reject($id, $approverUserId, $rejectionReason)
    {
        $rejectionReason = trim((string)$rejectionReason);
        if ($rejectionReason === '') {
            throw new InvalidArgumentException('Alasan penolakan wajib diisi');
        }

        $this->db->beginTransaction();
        try {
            $row = $this->db->selectOne(
                'SELECT * FROM leave_requests WHERE id = ? FOR UPDATE',
                array((int)$id)
            );
            if (!$row) {
                throw new RuntimeException('Pengajuan cuti tidak ditemukan');
            }
            if ($row['status'] !== 'pending') {
                throw new RuntimeException('Pengajuan sudah diproses sebelumnya');
            }

            $now = Config::companyNow()->format('Y-m-d H:i:s');
            $this->db->run(
                "UPDATE leave_requests
                 SET status = 'rejected', reviewed_by = ?, reviewed_at = ?, rejection_reason = ?, updated_at = ?
                 WHERE id = ? AND status = 'pending'",
                array((int)$approverUserId, $now, $rejectionReason, $now, (int)$id)
            );
            // Penolakan tidak mengurangi kuota
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }

        $this->notifications->notifyEmployee(
            $row['employee_id'],
            'Pengajuan Cuti Ditolak',
            'Pengajuan cuti Anda ditolak. Alasan: ' . $rejectionReason,
            'leave_request',
            'leave_request',
            (int)$id
        );
        $this->audit->record($approverUserId, 'leave_request.reject', 'leave_request', (int)$id, $row, array(
            'status' => 'rejected',
            'rejection_reason' => $rejectionReason
        ));

        return $this->detail($id);
    }
}
