<?php

require_once __DIR__ . '/../common/DBConnection.php';
require_once __DIR__ . '/../common/Config.php';

class AttendanceService
{
    private $db;

    public function __construct(DBConnection $db = null)
    {
        $this->db = $db ?: new DBConnection();
    }

    private function deletedFilter($alias = '')
    {
        try {
            $col = $this->db->selectOne("SHOW COLUMNS FROM attendances LIKE 'deleted_at'");
            if ($col) {
                return ($alias !== '' ? $alias . '.' : '') . 'deleted_at IS NULL';
            }
        } catch (Exception $e) {
        }
        return null;
    }

    public static function normalizedTime($time)
    {
        if ($time === null || $time === '' || (string)$time === '00:00:00') {
            return null;
        }
        return substr((string)$time, 0, 8);
    }

    public static function workingSeconds($clockIn, $clockOut = null, $tanggal = null)
    {
        $clockIn = self::normalizedTime($clockIn);
        if ($clockIn === null) {
            return 0;
        }
        $tanggal = $tanggal ?: Config::companyToday();
        try {
            $start = new DateTime($tanggal . ' ' . $clockIn);
            $clockOut = self::normalizedTime($clockOut);
            $end = $clockOut !== null ? new DateTime($tanggal . ' ' . $clockOut) : Config::companyNow();
            $seconds = $end->getTimestamp() - $start->getTimestamp();
            return $seconds > 0 ? $seconds : 0;
        } catch (Exception $e) {
            return 0;
        }
    }

    public static function storeSelfie($selfie, $tanggal, $employeeId, $prefix, $scope = '')
    {
        if (!is_string($selfie) ||
            preg_match('#^data:([a-zA-Z0-9.+/-]+);base64,([A-Za-z0-9+/=\r\n]+)$#', $selfie, $m) !== 1) {
            return null;
        }

        $mime = strtolower($m[1]);
        if (!in_array($mime, Config::ALLOWED_SELFIE_MIME, true)) {
            return null;
        }
        $bytes = base64_decode($m[2], true);
        if ($bytes === false || strlen($bytes) > Config::MAX_SELFIE_BYTES) {
            return null;
        }

        $extMap = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp');
        $relativeDir = ($scope !== '' ? rtrim($scope, '/') . '/' : '') . $tanggal . '/' . (int)$employeeId;
        $absoluteDir = rtrim(Config::SELFIE_STORAGE_DIR, '/\\') . '/' .
            str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
        if ((!is_dir($absoluteDir) && !mkdir($absoluteDir, 0755, true)) || !is_dir($absoluteDir)) {
            return null;
        }

        $filename = $prefix . '_' . uniqid('', true) . '.' . $extMap[$mime];
        $absolutePath = $absoluteDir . DIRECTORY_SEPARATOR . $filename;
        if (file_put_contents($absolutePath, $bytes) === false) {
            return null;
        }
        @chmod($absolutePath, 0644);

        return array('path' => $relativeDir . '/' . $filename, 'mime_type' => $mime);
    }

    /**
     * Simpan absensi karyawan. Identitas diambil dari parameter server-side,
     * bukan dari input frontend.
     */
    public function create($employeeId, $latitude, $longitude, $gpsAccuracy, $requestId = null, $companyId = null)
    {
        if (!$employeeId) {
            throw new InvalidArgumentException('Karyawan tidak valid');
        }

        // Validasi koordinat
        if ($latitude === null || $latitude === '' || $longitude === null || $longitude === ''
            || !is_numeric($latitude) || !is_numeric($longitude)) {
            throw new InvalidArgumentException('Koordinat lokasi tidak valid');
        }
        $latitude = (float)$latitude;
        $longitude = (float)$longitude;
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            throw new InvalidArgumentException('Koordinat lokasi di luar rentang yang diizinkan');
        }
        if ($gpsAccuracy !== null && $gpsAccuracy !== '' && !is_numeric($gpsAccuracy)) {
            $gpsAccuracy = null;
        }

        $today = Config::companyToday();
        $now = Config::companyNow();

        // Cegah pengiriman ganda
        if ($requestId) {
            $dup = $this->db->selectOne('SELECT id FROM attendances WHERE request_id = ? LIMIT 1', array($requestId));
            if ($dup) {
                throw new RuntimeException('Absensi ini sudah pernah dikirim sebelumnya');
            }
        }
        if (Config::ONE_ATTENDANCE_PER_DAY) {
            $filter = $this->deletedFilter();
            $dupSql = 'SELECT id FROM attendances WHERE employee_id = ? AND `date` = ?' .
                ($filter ? ' AND ' . $filter : '');
            $dupParams = array((int)$employeeId, $today);
            if ($companyId !== null) {
                $dupSql .= ' AND company_id = ?';
                $dupParams[] = (int)$companyId;
            }
            $dup = $this->db->selectOne($dupSql . ' LIMIT 1', $dupParams);
            if ($dup) {
                throw new RuntimeException('Anda sudah melakukan absensi hari ini');
            }
        }

        $columns = 'employee_id, `date`, clock_in, latitude, longitude, gps_accuracy,
                    status, request_id, created_at, modified_at';
        $values = array(
            (int)$employeeId,
            $today,
            $now->format('H:i:s'),
            $latitude,
            $longitude,
            $gpsAccuracy !== null && $gpsAccuracy !== '' ? (float)$gpsAccuracy : null,
            'recorded',
            $requestId,
            $now->format('Y-m-d H:i:s'),
            $now->format('Y-m-d H:i:s')
        );
        if ($companyId !== null) {
            $columns .= ', company_id';
            $values[] = (int)$companyId;
        }
        $id = $this->db->insert(
            'INSERT INTO attendances (' . $columns . ') VALUES (' .
            implode(', ', array_fill(0, count($values), '?')) . ')',
            $values
        );

        return $this->find($id, $companyId);
    }

    public function find($id, $companyId = null)
    {
        $filter = $this->deletedFilter('a');
        $sql = 'SELECT a.*, e.first_name, e.last_name, e.user_name AS employee_nik,
                        e.mobile_no AS phone,
                        d.name AS department_name, dg.title AS designation_name
                FROM attendances a
                LEFT JOIN employees e ON e.id = a.employee_id
                LEFT JOIN departments d ON d.id = e.department_id
                LEFT JOIN employee_designations dg ON dg.id = e.designation_id
                WHERE a.id = ?' . ($filter ? ' AND ' . $filter : '');
        $params = array((int)$id);
        if ($companyId !== null) {
            $sql .= ' AND a.company_id = ?';
            $params[] = (int)$companyId;
        }
        return $this->db->selectOne($sql, $params);
    }

    public function mine($employeeId, $page = 1, $perPage = 10, $companyId = null)
    {
        $page = max(1, (int)$page);
        $perPage = min(50, max(1, (int)$perPage));
        $offset = ($page - 1) * $perPage;

        $filter = $this->deletedFilter();
        $where = 'employee_id = ?' . ($filter ? ' AND ' . $filter : '');
        $params = array((int)$employeeId);
        if ($companyId !== null) {
            $where .= ' AND company_id = ?';
            $params[] = (int)$companyId;
        }
        $totalRow = $this->db->selectOne(
            'SELECT COUNT(id) AS total FROM attendances WHERE ' . $where,
            $params
        );
        $filterAlias = $this->deletedFilter('a');
        $whereAlias = ' WHERE a.employee_id = ?' . ($filterAlias ? ' AND ' . $filterAlias : '');
        if ($companyId !== null) {
            $whereAlias .= ' AND a.company_id = ?';
        }
        $rows = $this->db->selectAll(
            'SELECT a.id, a.employee_id, a.`date`, a.clock_in, a.latitude, a.longitude,
                    a.status, a.created_at' . $this->optionalAttendanceColumns() . ',
                    e.first_name, e.last_name, e.mobile_no AS phone
             FROM attendances a
             LEFT JOIN employees e ON e.id = a.employee_id' . $whereAlias . '
             ORDER BY a.`date` DESC, a.clock_in DESC
             LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset,
            $params
        );

        return array(
            'items' => $rows,
            'total' => $totalRow ? (int)$totalRow['total'] : 0,
            'page' => $page,
            'per_page' => $perPage
        );
    }

    public function all($filters = array(), $page = 1, $perPage = 15, $companyId = null)
    {
        $page = max(1, (int)$page);
        $perPage = min(100, max(1, (int)$perPage));
        $offset = ($page - 1) * $perPage;

        $where = array();
        $params = array();
        if ($companyId !== null) {
            $where[] = 'a.company_id = ?';
            $params[] = (int)$companyId;
        }

        if (!empty($filters['date'])) {
            $where[] = 'a.`date` = ?';
            $params[] = $filters['date'];
        }
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $where[] = 'a.`date` BETWEEN ? AND ?';
            $params[] = $filters['start_date'];
            $params[] = $filters['end_date'];
        }
        if (!empty($filters['keyword'])) {
            $where[] = '(e.first_name LIKE ? OR e.last_name LIKE ? OR e.user_name LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }

        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $totalRow = $this->db->selectOne(
            'SELECT COUNT(a.id) AS total FROM attendances a
             LEFT JOIN employees e ON e.id = a.employee_id' . $whereSql,
            $params
        );

        $rows = $this->db->selectAll(
            'SELECT a.id, a.employee_id, a.`date`, a.clock_in, a.latitude, a.longitude,
                    a.gps_accuracy, a.status, a.created_at' . $this->optionalAttendanceColumns() . ',
                    e.first_name, e.last_name, e.user_name AS employee_nik,
                    e.mobile_no AS phone,
                    d.name AS department_name, dg.title AS designation_name
             FROM attendances a
             LEFT JOIN employees e ON e.id = a.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN employee_designations dg ON dg.id = e.designation_id'
            . $whereSql .
            ' ORDER BY a.`date` DESC, a.clock_in DESC
             LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset,
            $params
        );

        return array(
            'items' => $rows,
            'total' => $totalRow ? (int)$totalRow['total'] : 0,
            'page' => $page,
            'per_page' => $perPage
        );
    }

    private function optionalAttendanceColumns()
    {
        $columns = '';
        foreach (array('selfie_path', 'selfie_mime_type', 'selfie_size', 'selfie_out_path', 'selfie_out_mime_type') as $column) {
            $exists = $this->db->selectOne("SHOW COLUMNS FROM attendances LIKE '" . $column . "'");
            if ($exists) {
                $columns .= ', a.' . $column;
            }
        }
        return $columns;
    }

    /** Data untuk export: diambil dari database sesuai rentang tanggal. */
    public function forExport($startDate, $endDate, $hrEmployeeId = null, $companyId = null)
    {
        $where = ' WHERE a.`date` BETWEEN ? AND ?';
        $params = array($startDate, $endDate);
        if ($companyId !== null) {
            $where .= ' AND a.company_id = ?';
            $params[] = (int)$companyId;
        }
        return $this->db->selectAll(
            'SELECT a.employee_id, a.`date`, a.clock_in, a.latitude, a.longitude, a.status,
                    e.first_name, e.last_name, e.user_name AS employee_nik,
                    e.mobile_no AS phone,
                    d.name AS department_name, dg.title AS designation_name
             FROM attendances a
             LEFT JOIN employees e ON e.id = a.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN employee_designations dg ON dg.id = e.designation_id'
            . $where . '
             ORDER BY e.first_name, e.last_name, a.clock_in',
            $params
        );
    }
}
