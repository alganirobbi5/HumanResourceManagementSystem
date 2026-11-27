<?php

require_once __DIR__ . '/../common/DBConnection.php';
require_once __DIR__ . '/../common/Config.php';
require_once __DIR__ . '/NotificationService.php';
require_once __DIR__ . '/AuditLog.php';
require_once __DIR__ . '/LeaveLeaderService.php';

class LeaveService
{
    private $db;
    private $notifications;
    private $audit;
    private $leaders;
    private $holidays;

    public function __construct(DBConnection $db = null)
    {
        $this->db = $db ?: new DBConnection();
        $this->ensureTables();
        $this->notifications = new NotificationService($this->db);
        $this->audit = new AuditLog($this->db);
        $this->leaders = new LeaveLeaderService($this->db->getPdo());
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
    public function getBalance($employeeId, $year = null, $companyId = null)
    {
        $year = $year ?: (int)Config::companyNow()->format('Y');
        $balanceSql = 'SELECT * FROM leave_balances WHERE employee_id = ? AND leave_type = ? AND entitlement_year = ?';
        $balanceParams = array((int)$employeeId, Config::LEAVE_TYPE_DEFAULT, $year);
        if ($companyId !== null) {
            $balanceSql .= ' AND company_id = ?';
            $balanceParams[] = (int)$companyId;
        }
        $row = $this->db->selectOne($balanceSql, $balanceParams);
        if (!$row) {
            $insertColumns = 'employee_id, leave_type, entitlement_year, entitled_days, used_days';
            $insertValues = array((int)$employeeId, Config::LEAVE_TYPE_DEFAULT, $year, Config::LEAVE_ENTITLEMENT_DEFAULT, 0);
            if ($this->db->getPdo()->query("SHOW COLUMNS FROM leave_balances LIKE 'company_id'")->fetch()) {
                $insertColumns .= ', company_id';
                $insertValues[] = $companyId;
            }
            $this->db->insert(
                'INSERT INTO leave_balances (' . $insertColumns . ') VALUES (' .
                implode(', ', array_fill(0, count($insertValues), '?')) . ')',
                $insertValues
            );
            $row = $this->db->selectOne($balanceSql, $balanceParams);
        }
        $row['remaining_days'] = (float)$row['entitled_days'] - (float)$row['used_days'];
        return $row;
    }

    /** Tambah jatah kuota cuti karyawan oleh HR. */
    public function addEntitlement($employeeId, $days, $year = null, $companyId = null)
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

        $employeeSql = 'SELECT id, first_name, last_name FROM employees WHERE id = ? AND status = 1';
        $employeeParams = array($employeeId);
        if ($companyId !== null) {
            $employeeSql .= ' AND company_id = ?';
            $employeeParams[] = (int)$companyId;
        }
        $employee = $this->db->selectOne($employeeSql, $employeeParams);
        if (!$employee) {
            throw new InvalidArgumentException('Karyawan tidak ditemukan atau tidak aktif');
        }

        $balance = $this->getBalance($employeeId, $year, $companyId);
        $updateBalanceSql = 'UPDATE leave_balances SET entitled_days = entitled_days + ?, updated_at = NOW() WHERE id = ?';
        $updateBalanceParams = array($days, (int)$balance['id']);
        if ($companyId !== null) {
            $updateBalanceSql .= ' AND company_id = ?';
            $updateBalanceParams[] = (int)$companyId;
        }
        $this->db->run($updateBalanceSql, $updateBalanceParams);

        return $this->getBalance($employeeId, $year, $companyId);
    }

    /**
     * Hitung jumlah hari cuti di sisi server (aturan via Config::LEAVE_DAY_COUNT_RULE).
     */
    public function calculateDays($startDate, $endDate, $companyId = null)
    {
        $start = DateTimeImmutable::createFromFormat('Y-m-d', $startDate);
        $end = DateTimeImmutable::createFromFormat('Y-m-d', $endDate);
        if (!$start || !$end || $start->format('Y-m-d') !== $startDate || $end->format('Y-m-d') !== $endDate) {
            throw new InvalidArgumentException('Format tanggal tidak valid');
        }
        if ($end < $start) {
            throw new InvalidArgumentException('Tanggal selesai tidak boleh lebih awal dari tanggal mulai');
        }

        $holidayDates = array();
        if (!$this->holidays) {
            require_once __DIR__ . '/HolidayService.php';
            $this->holidays = new HolidayService($this->db->getPdo());
        }
        $holidayDates = array_flip($this->holidays->datesFor($companyId));

        $days = 0;
        $cursor = $start;
        while ($cursor <= $end) {
            $isHoliday = isset($holidayDates[$cursor->format('Y-m-d')]);
            $isWorkday = (int)$cursor->format('N') <= 5;
            if (!$isHoliday && (Config::LEAVE_DAY_COUNT_RULE !== 'kerja' || $isWorkday)) {
                $days++;
            }
            $cursor = $cursor->modify('+1 day');
        }
        return $days;
    }

    private function hasLeaveRequestColumn($column)
    {
        $stmt = $this->db->getPdo()->query("SHOW COLUMNS FROM leave_requests LIKE '" . $column . "'");
        return $stmt && $stmt->fetch() !== false;
    }

    private function hasLeaveColumn($column)
    {
        $stmt = $this->db->getPdo()->query("SHOW COLUMNS FROM leaves LIKE '" . $column . "'");
        return $stmt && $stmt->fetch() !== false;
    }

    public function createRequest($employeeId, $reason, $startDate, $endDate, $replacementEmployeeId, $leaderUserId = null, $companyId = null, $currentUserId = null)
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
        $leaderUserId = (int)$leaderUserId;
        if ($currentUserId !== null && $leaderUserId === (int)$currentUserId) {
            throw new InvalidArgumentException('Tidak dapat memilih diri sendiri sebagai Leader persetujuan', 422);
        }
        if ($leaderUserId <= 0 || !$this->leaders->isLeader($leaderUserId, $companyId)) {
            throw new InvalidArgumentException('Leader persetujuan cuti tidak valid', 422);
        }

        $totalDays = $this->calculateDays($startDate, $endDate, $companyId); // validasi tanggal + hitung ulang
        if ($totalDays <= 0) {
            throw new InvalidArgumentException('Jumlah hari cuti harus lebih dari nol');
        }

        if (!Config::ALLOW_PAST_START_DATE && $startDate < Config::companyToday()) {
            throw new InvalidArgumentException('Tanggal mulai tidak boleh di masa lampau');
        }

        // Pengganti harus karyawan aktif dan bukan diri sendiri
        $replacementSql = 'SELECT id, first_name, last_name FROM employees WHERE id = ? AND status = 1';
        $replacementParams = array((int)$replacementEmployeeId);
        if ($companyId !== null) {
            $replacementSql .= ' AND company_id = ?';
            $replacementParams[] = (int)$companyId;
        }
        $replacement = $this->db->selectOne($replacementSql, $replacementParams);
        if (!$replacement) {
            throw new InvalidArgumentException('Karyawan pengganti tidak ditemukan atau tidak aktif');
        }
        if ((int)$replacement['id'] === (int)$employeeId) {
            throw new InvalidArgumentException('Karyawan pengganti tidak boleh diri sendiri');
        }

        // Cek tumpang tindih dengan pengajuan yang sudah disetujui
        if (Config::PREVENT_LEAVE_OVERLAP) {
            $overlapSql = "SELECT id FROM leave_requests
                           WHERE employee_id = ? AND status = 'approved'
                             AND start_date <= ? AND end_date >= ?";
            $overlapParams = array((int)$employeeId, $endDate, $startDate);
            if ($companyId !== null) {
                $overlapSql .= ' AND company_id = ?';
                $overlapParams[] = (int)$companyId;
            }
            $overlap = $this->db->selectOne($overlapSql . ' LIMIT 1', $overlapParams);
            if ($overlap) {
                throw new InvalidArgumentException('Rentang tanggal cuti bertabrakan dengan pengajuan yang sudah disetujui');
            }
        }

        // Kuota harus mencukupi saat pengajuan dibuat (saldo tahun pada tanggal mulai cuti)
        $balance = $this->getBalance($employeeId, (int)date('Y', strtotime($startDate)), $companyId);
        if ((float)$balance['remaining_days'] < $totalDays) {
            throw new InvalidArgumentException(
                'Sisa kuota cuti tidak mencukupi (sisa ' . $balance['remaining_days'] . ' hari, diajukan ' . $totalDays . ' hari)'
            );
        }

        $now = Config::companyNow()->format('Y-m-d H:i:s');
        $columns = array('employee_id', 'reason', 'start_date', 'end_date', 'total_days',
            'replacement_employee_id', 'status', 'submitted_at', 'created_at', 'updated_at');
        $values = array(
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
        );
        if ($this->hasLeaveRequestColumn('leader_user_id')) {
            $columns[] = 'leader_user_id';
            $values[] = $leaderUserId;
        }
        if ($companyId !== null && $this->hasLeaveRequestColumn('company_id')) {
            $columns[] = 'company_id';
            $values[] = (int)$companyId;
        }
        $id = $this->db->insert(
            'INSERT INTO leave_requests (' . implode(', ', $columns) . ') VALUES (' .
            implode(', ', array_fill(0, count($values), '?')) . ')',
            $values
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

        return $this->detail($id, $companyId);
    }

    private function baseSelect($extraJoin = '')
    {
        $leaderName = 'NULL AS target_leader_name';
        $leaderJoin = '';
        if ($this->hasLeaveRequestColumn('leader_user_id')) {
            $leaderName = 'CONCAT_WS(" ", le.first_name, le.last_name) AS target_leader_name';
            $leaderJoin = ' LEFT JOIN users lu ON lu.id = lr.leader_user_id
                            LEFT JOIN employees le ON le.id = lu.employee_id';
        }
        return 'SELECT lr.*,
                        e.first_name, e.last_name, e.user_name AS employee_nik,
                        e.mobile_no AS phone,
                        CONCAT_WS(" ", e.first_name, e.last_name) AS employee_name,
                       e.department_id, e.designation_id,
                       d.name AS department_name, dg.title AS designation_name,
                       r.first_name AS replacement_first_name, r.last_name AS replacement_last_name,
                       a.first_name AS reviewer_first_name, a.last_name AS reviewer_last_name,
                       ' . $leaderName . '
                FROM leave_requests lr
                LEFT JOIN employees e ON e.id = lr.employee_id
                LEFT JOIN departments d ON d.id = e.department_id
                LEFT JOIN employee_designations dg ON dg.id = e.designation_id
                LEFT JOIN employees r ON r.id = lr.replacement_employee_id
                LEFT JOIN users au ON au.id = lr.reviewed_by
                LEFT JOIN employees a ON a.id = au.employee_id'
                . $leaderJoin . $extraJoin;
    }

    /**
     * Daftar pengajuan pada tabel leaves (alur mobile) dengan filter scope:
     * leader (leader_user_id), mine (employee_id), atau all (tanpa filter).
     */
    public function listLeaves($scope, $userId = null, $companyId = null)
    {
        $fields = 'l.id, l.employee_id, l.type, l.leave_from, l.leave_to, l.status,
                   FROM_UNIXTIME(l.created_at) AS created_at,
                    e.first_name, e.last_name, e.user_name, e.mobile_no AS phone,
                    CONCAT_WS(" ", e.first_name, e.last_name) AS nama_lengkap';
        foreach (array('reason', 'comment', 'catatan_leader', 'approved_by_name', 'decided_at') as $column) {
            if ($this->hasLeaveColumn($column)) {
                $fields .= ', l.' . $column;
            }
        }
        foreach (array('leader_user_id', 'approved_by_user_id') as $column) {
            if ($this->hasLeaveColumn($column)) {
                $fields .= ', l.' . $column;
            }
        }

        $leaderJoin = '';
        if ($this->hasLeaveColumn('leader_user_id')) {
            $fields .= ', CONCAT_WS(" ", le.first_name, le.last_name) AS target_leader_name';
            $leaderJoin = ' LEFT JOIN users lu ON lu.id = l.leader_user_id
                            LEFT JOIN employees le ON le.id = lu.employee_id';
        }

        $where = array();
        $params = array();
        if ($this->hasLeaveColumn('deleted_at')) {
            $where[] = 'l.deleted_at IS NULL';
        }
        if ($scope === 'leader') {
            $where[] = 'l.leader_user_id = ?';
            $params[] = (int)$userId;
        } elseif ($scope === 'mine') {
            $where[] = 'l.employee_id = ?';
            $params[] = (int)$userId;
        }
        if ($companyId !== null) {
            $where[] = 'l.company_id = ?';
            $params[] = (int)$companyId;
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $stmt = $this->db->getPdo()->prepare(
            'SELECT ' . $fields . ' FROM leaves l
             LEFT JOIN employees e ON e.id = l.employee_id'
            . $leaderJoin . $whereSql . ' ORDER BY l.id DESC'
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function detail($id, $companyId = null)
    {
        $where = ' WHERE lr.id = ?';
        $params = array((int)$id);
        if ($companyId !== null) {
            $where .= ' AND lr.company_id = ?';
            $params[] = (int)$companyId;
        }
        return $this->db->selectOne($this->baseSelect($where), $params);
    }

    public function forLeader($leaderUserId, $page = 1, $perPage = 10, $companyId = null)
    {
        $page = max(1, (int)$page);
        $perPage = min(50, max(1, (int)$perPage));
        $offset = ($page - 1) * $perPage;

        $totalSql = 'SELECT COUNT(id) AS total FROM leave_requests WHERE leader_user_id = ?';
        $params = array((int)$leaderUserId);
        $where = ' WHERE lr.leader_user_id = ?';
        if ($companyId !== null) {
            $totalSql .= ' AND company_id = ?';
            $where .= ' AND lr.company_id = ?';
            $params[] = (int)$companyId;
        }
        $totalRow = $this->db->selectOne($totalSql, $params);
        $rows = $this->db->selectAll(
            $this->baseSelect($where)
            . ' ORDER BY lr.submitted_at DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset,
            $params
        );

        return array(
            'items' => $rows,
            'total' => $totalRow ? (int)$totalRow['total'] : 0,
            'page' => $page,
            'per_page' => $perPage
        );
    }

    public function mine($employeeId, $page = 1, $perPage = 10, $companyId = null)
    {
        $page = max(1, (int)$page);
        $perPage = min(50, max(1, (int)$perPage));
        $offset = ($page - 1) * $perPage;

        $totalSql = 'SELECT COUNT(id) AS total FROM leave_requests WHERE employee_id = ?';
        $params = array((int)$employeeId);
        $where = ' WHERE lr.employee_id = ?';
        if ($companyId !== null) {
            $totalSql .= ' AND company_id = ?';
            $where .= ' AND lr.company_id = ?';
            $params[] = (int)$companyId;
        }
        $totalRow = $this->db->selectOne($totalSql, $params);
        $rows = $this->db->selectAll(
            $this->baseSelect($where)
            . ' ORDER BY lr.submitted_at DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset,
            $params
        );

        return array(
            'items' => $rows,
            'total' => $totalRow ? (int)$totalRow['total'] : 0,
            'page' => $page,
            'per_page' => $perPage
        );
    }

    public function pending($page = 1, $perPage = 10, $leaderUserId = null, $companyId = null)
    {
        $page = max(1, (int)$page);
        $perPage = min(50, max(1, (int)$perPage));
        $offset = ($page - 1) * $perPage;

        $leaderFilter = $leaderUserId ? ' AND leader_user_id = ?' : '';
        $leaderWhere = $leaderUserId ? ' AND lr.leader_user_id = ?' : '';
        $params = $leaderUserId ? array((int)$leaderUserId) : array();
        if ($companyId !== null) {
            $leaderFilter .= ' AND company_id = ?';
            $leaderWhere .= ' AND lr.company_id = ?';
            $params[] = (int)$companyId;
        }
        $totalRow = $this->db->selectOne(
            "SELECT COUNT(id) AS total FROM leave_requests WHERE status = 'pending'" . $leaderFilter,
            $params
        );
        $rows = $this->db->selectAll(
            $this->baseSelect(" WHERE lr.status = 'pending'" . $leaderWhere)
            . ' ORDER BY lr.submitted_at ASC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset,
            $params
        );

        return array(
            'items' => $rows,
            'total' => $totalRow ? (int)$totalRow['total'] : 0,
            'page' => $page,
            'per_page' => $perPage
        );
    }

    public function all($filters = array(), $page = 1, $perPage = 10, $leaderUserId = null, $companyId = null)
    {
        $page = max(1, (int)$page);
        $perPage = min(50, max(1, (int)$perPage));
        $offset = ($page - 1) * $perPage;

        $where = array();
        $params = array();
        if ($leaderUserId) {
            $where[] = 'lr.leader_user_id = ?';
            $params[] = (int)$leaderUserId;
        }
        if ($companyId !== null) {
            $where[] = 'lr.company_id = ?';
            $params[] = (int)$companyId;
        }
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

    const NOT_LEADER_MESSAGE = 'Hanya user yang terdaftar sebagai Leader persetujuan cuti yang dapat menyetujui atau menolak pengajuan.';

    private function assertLeaderCanDecide($row, $approverUserId, $companyId = null)
    {
        if (!$this->leaders->isLeader($approverUserId, $companyId)) {
            throw new RuntimeException(self::NOT_LEADER_MESSAGE, 403);
        }
        if (!empty($row['leader_user_id']) && (int)$row['leader_user_id'] !== (int)$approverUserId) {
            throw new RuntimeException('Pengajuan cuti ini ditujukan kepada Leader lain', 403);
        }
    }

    private function appendLeaderDecision(array &$set, array &$params, $approverUserId, $catatanLeader, $now)
    {
        if ($this->hasLeaveRequestColumn('approved_by_user_id')) {
            $set[] = 'approved_by_user_id = ?';
            $params[] = (int)$approverUserId;
        }
        if ($this->hasLeaveRequestColumn('approved_by_name')) {
            $set[] = 'approved_by_name = ?';
            $params[] = $this->leaders->leaderName($approverUserId);
        }
        if ($catatanLeader !== null && $this->hasLeaveRequestColumn('catatan_leader')) {
            $set[] = 'catatan_leader = ?';
            $params[] = $catatanLeader;
        }
        if ($this->hasLeaveRequestColumn('decided_at')) {
            $set[] = 'decided_at = ?';
            $params[] = $now;
        }
    }

    public function approve($id, $approverUserId, $catatanLeader = null, $companyId = null)
    {
        $this->db->beginTransaction();
        try {
            // Kunci baris agar approve bersamaan tidak membuat kuota negatif
            $rowSql = 'SELECT * FROM leave_requests WHERE id = ?';
            $rowParams = array((int)$id);
            if ($companyId !== null) {
                $rowSql .= ' AND company_id = ?';
                $rowParams[] = (int)$companyId;
            }
            $row = $this->db->selectOne($rowSql . ' FOR UPDATE', $rowParams);
            if (!$row) {
                throw new RuntimeException('Pengajuan cuti tidak ditemukan');
            }
            if ($row['status'] !== 'pending') {
                throw new RuntimeException('Pengajuan sudah diproses sebelumnya');
            }
            $this->assertLeaderCanDecide($row, $approverUserId, $companyId);

            $balanceSql = 'SELECT * FROM leave_balances WHERE employee_id = ? AND leave_type = ? AND entitlement_year = ?';
            $balanceParams = array((int)$row['employee_id'], Config::LEAVE_TYPE_DEFAULT, (int)date('Y', strtotime($row['start_date'])));
            if ($companyId !== null) {
                $balanceSql .= ' AND company_id = ?';
                $balanceParams[] = (int)$companyId;
            }
            $balance = $this->db->selectOne($balanceSql . ' FOR UPDATE', $balanceParams);
            if (!$balance) {
                throw new RuntimeException('Saldo cuti karyawan tidak ditemukan');
            }

            $remaining = (float)$balance['entitled_days'] - (float)$balance['used_days'];
            $days = (float)$row['total_days'];
            if ($remaining < $days) {
                throw new RuntimeException('Sisa kuota cuti tidak mencukupi untuk menyetujui pengajuan ini');
            }

            $now = Config::companyNow()->format('Y-m-d H:i:s');
            $set = array("status = 'approved'", 'reviewed_by = ?', 'reviewed_at = ?',
                'rejection_reason = NULL', 'updated_at = ?');
            $params = array((int)$approverUserId, $now, $now);
            $this->appendLeaderDecision($set, $params, $approverUserId, $catatanLeader, $now);
            $params[] = (int)$id;
            $updateSql = 'UPDATE leave_requests SET ' . implode(', ', $set) . ' WHERE id = ? AND status = "pending"';
            if ($companyId !== null) {
                $updateSql .= ' AND company_id = ?';
                $params[] = (int)$companyId;
            }
            $this->db->run($updateSql, $params);
            $balanceUpdateSql = 'UPDATE leave_balances SET used_days = used_days + ?, updated_at = ? WHERE id = ?';
            $balanceUpdateParams = array($days, $now, (int)$balance['id']);
            if ($companyId !== null) {
                $balanceUpdateSql .= ' AND company_id = ?';
                $balanceUpdateParams[] = (int)$companyId;
            }
            $this->db->run($balanceUpdateSql, $balanceUpdateParams);

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }

        $detail = $this->detail($id, $companyId);
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

        return $this->detail($id, $companyId);
    }

    public function reject($id, $approverUserId, $rejectionReason, $catatanLeader = null, $companyId = null)
    {
        $rejectionReason = trim((string)$rejectionReason);
        if ($rejectionReason === '') {
            throw new InvalidArgumentException('Alasan penolakan wajib diisi');
        }

        $this->db->beginTransaction();
        try {
            $rowSql = 'SELECT * FROM leave_requests WHERE id = ?';
            $rowParams = array((int)$id);
            if ($companyId !== null) {
                $rowSql .= ' AND company_id = ?';
                $rowParams[] = (int)$companyId;
            }
            $row = $this->db->selectOne($rowSql . ' FOR UPDATE', $rowParams);
            if (!$row) {
                throw new RuntimeException('Pengajuan cuti tidak ditemukan');
            }
            if ($row['status'] !== 'pending') {
                throw new RuntimeException('Pengajuan sudah diproses sebelumnya');
            }
            $this->assertLeaderCanDecide($row, $approverUserId, $companyId);

            $now = Config::companyNow()->format('Y-m-d H:i:s');
            $set = array("status = 'rejected'", 'reviewed_by = ?', 'reviewed_at = ?',
                'rejection_reason = ?', 'updated_at = ?');
            $params = array((int)$approverUserId, $now, $rejectionReason, $now);
            $this->appendLeaderDecision(
                $set,
                $params,
                $approverUserId,
                $catatanLeader !== null ? $catatanLeader : $rejectionReason,
                $now
            );
            $params[] = (int)$id;
            $updateSql = 'UPDATE leave_requests SET ' . implode(', ', $set) . ' WHERE id = ? AND status = "pending"';
            if ($companyId !== null) {
                $updateSql .= ' AND company_id = ?';
                $params[] = (int)$companyId;
            }
            $this->db->run($updateSql, $params);
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

        return $this->detail($id, $companyId);
    }
}
