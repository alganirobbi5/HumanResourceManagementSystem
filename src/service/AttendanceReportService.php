<?php

require_once __DIR__ . '/../common/DBConnection.php';

class AttendanceReportService
{
    private $pdo;
    private $lastHolidays = array();

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

    private function pickColumn($table, array $candidates)
    {
        foreach ($candidates as $column) {
            if ($this->hasColumn($table, $column)) {
                return $column;
            }
        }
        return null;
    }

    private function jamMasukKerja($companyId = null)
    {
        if (!$this->hasTable('pengaturan_jam')) {
            return null;
        }
        if ($companyId !== null) {
            $stmt = $this->pdo->prepare('SELECT jam_masuk FROM pengaturan_jam WHERE company_id = ? ORDER BY id LIMIT 1');
            $stmt->execute(array((int)$companyId));
        } else {
            $stmt = $this->pdo->query('SELECT jam_masuk FROM pengaturan_jam ORDER BY id LIMIT 1');
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['jam_masuk'])) {
            return null;
        }
        return substr((string)$row['jam_masuk'], 0, 5);
    }

    private function holidayDates()
    {
        if (!$this->hasTable('holidays')) {
            return array();
        }
        require_once __DIR__ . '/HolidayService.php';
        $holidays = new HolidayService($this->pdo);
        $dates = $holidays->datesFor(null);
        $map = array();
        foreach ($dates as $date) {
            $date = (string)$date;
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $map[$date] = true;
            }
        }
        return $map;
    }

    private function employeeRows($companyId = null)
    {
        $jamMasukKerja = $this->jamMasukKerja($companyId);
        $holidayDates = $this->holidayDates();
        $columns = array('a.id', 'a.employee_id', 'a.date', 'a.clock_in', 'a.clock_out',
            'a.total_work', 'a.status', 'a.latitude', 'a.longitude');
        foreach (array('gps_accuracy', 'selfie_path', 'selfie_out_path') as $column) {
            if ($this->hasColumn('attendances', $column)) {
                $columns[] = 'a.' . $column;
            }
        }
        $outLat = $this->pickColumn('attendances', array('out_latitude', 'latitude_pulang'));
        $outLng = $this->pickColumn('attendances', array('out_longitude', 'longitude_pulang'));
        if ($outLat) {
            $columns[] = 'a.' . $outLat . ' AS out_latitude';
        }
        if ($outLng) {
            $columns[] = 'a.' . $outLng . ' AS out_longitude';
        }
        $columns[] = 'e.first_name';
        $columns[] = 'e.last_name';
        $columns[] = 'e.user_name AS employee_nik';
        $columns[] = 'e.mobile_no AS phone';
        $columns[] = 'u.username';
        if ($this->hasTable('departments')) {
            $columns[] = 'd.name AS department_name';
        }
        if ($this->hasTable('employee_designations')) {
            $columns[] = 'dg.title AS designation_name';
        }

        $sql = 'SELECT ' . implode(', ', $columns) . '
                FROM attendances a
                LEFT JOIN employees e ON e.id = a.employee_id
                LEFT JOIN users u ON u.employee_id = e.id';
        if ($this->hasTable('departments')) {
            $sql .= ' LEFT JOIN departments d ON d.id = e.department_id';
        }
        if ($this->hasTable('employee_designations')) {
            $sql .= ' LEFT JOIN employee_designations dg ON dg.id = e.designation_id';
        }
        $params = array();
        if ($this->hasColumn('attendances', 'deleted_at')) {
            $sql .= ' WHERE a.deleted_at IS NULL';
        }
        if ($companyId !== null) {
            $sql .= ($this->hasColumn('attendances', 'deleted_at') ? ' AND' : ' WHERE') . ' a.company_id = ?';
            $params[] = (int)$companyId;
        }
        $sql .= ' ORDER BY a.date DESC, a.clock_in DESC, a.id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = array();
        foreach ($rows as $row) {
            $nama = trim((string)$row['first_name'] . ' ' . (string)$row['last_name']);
            if ($nama === '') {
                $nama = (string)($row['username'] ?: $row['employee_nik'] ?: '-');
            }
            $result[] = array(
                'id' => (int)$row['id'],
                'record_type' => 'employee',
                'employee_id' => $row['employee_id'] !== null ? (int)$row['employee_id'] : null,
                'admin_id' => null,
                'nama' => $nama,
                'employee_name' => $nama,
                'first_name' => (string)$row['first_name'],
                'last_name' => (string)$row['last_name'],
                'username' => $row['username'],
                'employee_nik' => $row['employee_nik'],
                'phone' => isset($row['phone']) ? (string)$row['phone'] : '',
                'department_name' => isset($row['department_name']) ? $row['department_name'] : '',
                'designation_name' => isset($row['designation_name']) ? $row['designation_name'] : '',
                'department' => isset($row['department_name']) ? $row['department_name'] : '',
                'jabatan' => isset($row['designation_name']) ? $row['designation_name'] : '',
                'tanggal' => $row['date'],
                'date' => $row['date'],
                'clock_in' => $row['clock_in'],
                'jam_masuk' => $row['clock_in'],
                'clock_out' => $row['clock_out'],
                'jam_pulang' => $row['clock_out'],
                'total_work' => $row['total_work'],
                'status' => isset($holidayDates[(string)$row['date']])
                    ? 'Libur'
                    : ((string)$row['status'] !== '' ? (string)$row['status'] : 'recorded'),
                'terlambat' => ($jamMasukKerja !== null && !empty($row['clock_in'])
                    && substr((string)$row['clock_in'], 0, 5) > $jamMasukKerja),
                'jam_masuk_kerja' => $jamMasukKerja,
                'latitude' => $row['latitude'],
                'longitude' => $row['longitude'],
                'out_latitude' => isset($row['out_latitude']) ? $row['out_latitude'] : null,
                'out_longitude' => isset($row['out_longitude']) ? $row['out_longitude'] : null,
                'gps_accuracy' => isset($row['gps_accuracy']) ? $row['gps_accuracy'] : null,
                'selfie_path' => isset($row['selfie_path']) ? $row['selfie_path'] : null,
                'selfie_out_path' => isset($row['selfie_out_path']) ? $row['selfie_out_path'] : null,
                'selfie_in_url' => !empty($row['selfie_path']) ? 'selfie.php?id=' . (int)$row['id'] . '&type=in' : null,
                'selfie_out_url' => !empty($row['selfie_out_path']) ? 'selfie.php?id=' . (int)$row['id'] . '&type=out' : null,
            );
        }
        return $result;
    }

    private function adminRows($companyId = null)
    {
        $holidayDates = $this->holidayDates();
        if (!$this->hasTable('admin_attendances')) {
            return array();
        }
        $columns = array('aa.id', 'aa.admin_id', 'aa.tanggal', 'aa.waktu_masuk', 'aa.waktu_pulang',
            'aa.latitude_masuk', 'aa.longitude_masuk', 'aa.latitude_pulang', 'aa.longitude_pulang',
            'aa.selfie_masuk', 'aa.selfie_pulang');
        if ($this->hasTable('admins')) {
            $columns[] = 'a.name AS admin_name';
        }
        if ($this->hasColumn('users', 'admin_id')) {
            $columns[] = 'u.username';
        }
        $sql = 'SELECT ' . implode(', ', $columns) . ' FROM admin_attendances aa';
        if ($this->hasTable('admins')) {
            $sql .= ' LEFT JOIN admins a ON a.id = aa.admin_id';
        }
        if ($this->hasColumn('users', 'admin_id')) {
            $sql .= ' LEFT JOIN users u ON u.admin_id = aa.admin_id';
        }
        $params = array();
        if ($companyId !== null) {
            $sql .= ' WHERE aa.company_id = ?';
            $params[] = (int)$companyId;
        }
        $sql .= ' ORDER BY aa.tanggal DESC, aa.waktu_masuk DESC, aa.id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result = array();
        foreach ($rows as $row) {
            $nama = trim((string)(isset($row['admin_name']) ? $row['admin_name'] : ''));
            if ($nama === '') {
                $nama = trim((string)(isset($row['username']) ? $row['username'] : ''));
            }
            if ($nama === '') {
                $nama = 'HR / Admin';
            }
            $result[] = array(
                'id' => (int)$row['id'],
                'record_type' => 'admin',
                'employee_id' => null,
                'admin_id' => (int)$row['admin_id'],
                'nama' => $nama,
                'employee_name' => $nama,
                'first_name' => $nama,
                'last_name' => '',
                'username' => isset($row['username']) ? $row['username'] : null,
                'employee_nik' => isset($row['username']) ? $row['username'] : null,
                'phone' => '',
                'department_name' => 'HR / Admin',
                'designation_name' => 'HR / Admin',
                'department' => 'HR / Admin',
                'jabatan' => 'HR / Admin',
                'tanggal' => $row['tanggal'],
                'date' => $row['tanggal'],
                'clock_in' => $row['waktu_masuk'],
                'jam_masuk' => $row['waktu_masuk'],
                'clock_out' => $row['waktu_pulang'],
                'jam_pulang' => $row['waktu_pulang'],
                'total_work' => null,
                'status' => isset($holidayDates[(string)$row['tanggal']]) ? 'Libur' : 'recorded',
                'latitude' => $row['latitude_masuk'],
                'longitude' => $row['longitude_masuk'],
                'out_latitude' => $row['latitude_pulang'],
                'out_longitude' => $row['longitude_pulang'],
                'gps_accuracy' => null,
                'selfie_path' => $row['selfie_masuk'],
                'selfie_out_path' => $row['selfie_pulang'],
                'selfie_in_url' => !empty($row['selfie_masuk'])
                    ? 'selfie.php?id=' . (int)$row['id'] . '&type=in&scope=admin' : null,
                'selfie_out_url' => !empty($row['selfie_pulang'])
                    ? 'selfie.php?id=' . (int)$row['id'] . '&type=out&scope=admin' : null,
            );
        }
        return $result;
    }

    public function all(array $filters = array())
    {
        $companyId = isset($filters['company_id']) && $filters['company_id'] !== null
            ? (int)$filters['company_id'] : null;
        $rows = array_merge($this->employeeRows($companyId), $this->adminRows($companyId));
        $tanggal = isset($filters['tanggal']) ? trim((string)$filters['tanggal']) : '';
        if ($tanggal === '' && isset($filters['date'])) {
            $tanggal = trim((string)$filters['date']);
        }
        $start = isset($filters['start_date']) ? trim((string)$filters['start_date']) : '';
        $end = isset($filters['end_date']) ? trim((string)$filters['end_date']) : '';
        $keyword = isset($filters['keyword']) ? trim((string)$filters['keyword']) : '';

        $filtered = array();
        foreach ($rows as $row) {
            if ($tanggal !== '' && (string)$row['tanggal'] !== $tanggal) {
                continue;
            }
            if ($start !== '' && (string)$row['tanggal'] < $start) {
                continue;
            }
            if ($end !== '' && (string)$row['tanggal'] > $end) {
                continue;
            }
            if ($keyword !== '') {
                $haystack = (string)$row['nama'] . ' ' . (string)$row['username'] . ' ' . (string)$row['employee_nik'];
                if (stripos($haystack, $keyword) === false) {
                    continue;
                }
            }
            $filtered[] = $row;
        }

        $holidayDates = $this->holidayDates();
        $holidayList = array();
        foreach ($holidayDates as $date => $unused) {
            $date = (string)$date;
            if ($tanggal !== '' && $date !== $tanggal) {
                continue;
            }
            if ($start !== '' && $date < $start) {
                continue;
            }
            if ($end !== '' && $date > $end) {
                continue;
            }
            $holidayList[] = $date;
        }
        sort($holidayList);
        $this->lastHolidays = $holidayList;

        usort($filtered, function ($a, $b) {
            $cmp = strcmp((string)$b['tanggal'], (string)$a['tanggal']);
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmp = strcmp((string)$b['clock_in'], (string)$a['clock_in']);
            if ($cmp !== 0) {
                return $cmp;
            }
            return (int)$b['id'] - (int)$a['id'];
        });
        return $filtered;
    }

    public function holidays()
    {
        return $this->lastHolidays;
    }

    public function paginate(array $rows, $page = 1, $perPage = 15)
    {
        $page = max(1, (int)$page);
        $perPage = min(100, max(1, (int)$perPage));
        return array(
            'items' => array_slice($rows, ($page - 1) * $perPage, $perPage),
            'total' => count($rows),
            'page' => $page,
            'per_page' => $perPage,
            'holidays' => $this->lastHolidays,
        );
    }
}
