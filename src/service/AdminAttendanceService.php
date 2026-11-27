<?php

require_once __DIR__ . '/../common/DBConnection.php';
require_once __DIR__ . '/../common/Config.php';
require_once __DIR__ . '/../service/AttendanceService.php';

class AdminAttendanceService
{
    private $db;

    public function __construct(DBConnection $db = null)
    {
        $this->db = $db ?: new DBConnection();
    }

    private function hasColumn($column)
    {
        return (bool)$this->db->selectOne("SHOW COLUMNS FROM admin_attendances LIKE '" . $column . "'");
    }

    public function status($adminId, $tanggal = null, $companyId = null)
    {
        if (!$adminId) {
            throw new InvalidArgumentException('Identitas Admin HR tidak valid');
        }
        $tanggal = $tanggal ?: Config::companyToday();
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            throw new InvalidArgumentException('Format tanggal tidak valid');
        }

        $columns = 'id, waktu_masuk, waktu_pulang';
        if ($this->hasColumn('selfie_masuk')) {
            $columns .= ', selfie_masuk';
        }
        if ($this->hasColumn('selfie_pulang')) {
            $columns .= ', selfie_pulang';
        }
        $statusSql = 'SELECT ' . $columns . ' FROM admin_attendances WHERE admin_id = ? AND tanggal = ?';
        $statusParams = array((int)$adminId, $tanggal);
        if ($companyId !== null) {
            $statusSql .= ' AND company_id = ?';
            $statusParams[] = (int)$companyId;
        }
        $row = $this->db->selectOne($statusSql . ' LIMIT 1', $statusParams);

        $clockIn = $row ? AttendanceService::normalizedTime($row['waktu_masuk']) : null;
        $clockOut = $row ? AttendanceService::normalizedTime($row['waktu_pulang']) : null;
        return array(
            'id' => $row ? (int)$row['id'] : null,
            'tanggal' => $tanggal,
            'sudah_masuk' => $clockIn !== null,
            'jam_masuk' => $clockIn,
            'clock_in' => $clockIn,
            'sudah_pulang' => $clockOut !== null,
            'jam_pulang' => $clockOut,
            'clock_out' => $clockOut,
            'working_seconds' => AttendanceService::workingSeconds($clockIn, $clockOut, $tanggal),
            'selfie_in_url' => $row && !empty($row['selfie_masuk'])
                ? 'selfie.php?id=' . (int)$row['id'] . '&type=in&scope=admin' : null,
            'selfie_out_url' => $row && !empty($row['selfie_pulang'])
                ? 'selfie.php?id=' . (int)$row['id'] . '&type=out&scope=admin' : null,
        );
    }

    /**
     * Check-in Admin HR. Identitas berasal dari token (adminId), bukan request.
     */
    public function checkIn($adminId, $latitude, $longitude, $gpsAccuracy, $requestId = null, $selfie = null, $companyId = null)
    {
        if (!$adminId) {
            throw new InvalidArgumentException('Identitas Admin HR tidak valid');
        }

        $latitude = $this->validateCoordinate($latitude, -90, 90, 'latitude');
        $longitude = $this->validateCoordinate($longitude, -180, 180, 'longitude');
        $this->assertInsideOffice($latitude, $longitude, $companyId);

        $tanggal = Config::companyToday();
        $now = Config::companyNow();
        $selfieData = AttendanceService::storeSelfie($selfie, $tanggal, $adminId, 'selfie_masuk', 'admin');

        $this->db->beginTransaction();
        try {
            $existingSql = 'SELECT id FROM admin_attendances WHERE admin_id = ? AND tanggal = ?';
            $existingParams = array((int)$adminId, $tanggal);
            if ($companyId !== null) {
                $existingSql .= ' AND company_id = ?';
                $existingParams[] = (int)$companyId;
            }
            $existing = $this->db->selectOne($existingSql . ' FOR UPDATE', $existingParams);
            if ($existing) {
                $this->db->rollBack();
                throw new RuntimeException('Anda sudah melakukan absen masuk hari ini');
            }

            $columns = array('admin_id', 'tanggal', 'waktu_masuk', 'latitude_masuk', 'longitude_masuk',
                'created_at', 'updated_at');
            $values = array(
                (int)$adminId,
                $tanggal,
                $now->format('H:i:s'),
                $latitude,
                $longitude,
                $now->format('Y-m-d H:i:s'),
                $now->format('Y-m-d H:i:s'),
            );
            if ($selfieData && $this->hasColumn('selfie_masuk')) {
                $columns[] = 'selfie_masuk';
                $values[] = $selfieData['path'];
            }
            if ($selfieData && $this->hasColumn('selfie_masuk_mime')) {
                $columns[] = 'selfie_masuk_mime';
                $values[] = $selfieData['mime_type'];
            }
            if ($companyId !== null) {
                $columns[] = 'company_id';
                $values[] = (int)$companyId;
            }
            $this->db->run(
                'INSERT INTO admin_attendances (' . implode(', ', $columns) . ') VALUES (' .
                implode(', ', array_fill(0, count($values), '?')) . ')',
                $values
            );

            $this->db->commit();
        } catch (Exception $e) {
            if ($this->db->getPdo()->inTransaction()) {
                $this->db->rollBack();
            }
            if ($this->isDuplicateKeyError($e)) {
                throw new RuntimeException('Anda sudah melakukan absen masuk hari ini');
            }
            throw $e;
        }

        return $this->status($adminId, $tanggal, $companyId);
    }

    /**
     * Check-out Admin HR.
     */
    public function checkOut($adminId, $waktuLokal, $latitude, $longitude, $selfie = null, $companyId = null)
    {
        if (!$adminId) {
            throw new InvalidArgumentException('Identitas Admin HR tidak valid');
        }

        $now = Config::companyNow();
        $tanggal = $now->format('Y-m-d');
        $waktu = $now->format('H:i:s');

        $latitude = $this->validateCoordinate($latitude, -90, 90, 'latitude');
        $longitude = $this->validateCoordinate($longitude, -180, 180, 'longitude');
        $this->assertInsideOffice($latitude, $longitude, $companyId);
        $selfieData = AttendanceService::storeSelfie($selfie, $tanggal, $adminId, 'selfie_pulang', 'admin');

        $this->db->beginTransaction();
        try {
            $rowSql = 'SELECT id, waktu_pulang FROM admin_attendances WHERE admin_id = ? AND tanggal = ?';
            $rowParams = array((int)$adminId, $tanggal);
            if ($companyId !== null) {
                $rowSql .= ' AND company_id = ?';
                $rowParams[] = (int)$companyId;
            }
            $row = $this->db->selectOne($rowSql . ' FOR UPDATE', $rowParams);
            if (!$row) {
                $this->db->rollBack();
                throw new RuntimeException('Anda belum melakukan absen masuk pada tanggal tersebut');
            }
            if ($row['waktu_pulang'] !== null) {
                $this->db->rollBack();
                throw new RuntimeException('Anda sudah absen pulang hari ini');
            }

            $updates = array('waktu_pulang = ?', 'latitude_pulang = ?', 'longitude_pulang = ?', 'updated_at = ?');
            $values = array(
                $waktu,
                $latitude,
                $longitude,
                Config::companyNow()->format('Y-m-d H:i:s'),
            );
            if ($selfieData && $this->hasColumn('selfie_pulang')) {
                $updates[] = 'selfie_pulang = ?';
                $values[] = $selfieData['path'];
            }
            if ($selfieData && $this->hasColumn('selfie_pulang_mime')) {
                $updates[] = 'selfie_pulang_mime = ?';
                $values[] = $selfieData['mime_type'];
            }
            $values[] = (int)$row['id'];
            $values[] = (int)$adminId;
            $updateSql = 'UPDATE admin_attendances SET ' . implode(', ', $updates) .
                ' WHERE id = ? AND admin_id = ? AND waktu_pulang IS NULL';
            if ($companyId !== null) {
                $updateSql .= ' AND company_id = ?';
                $values[] = (int)$companyId;
            }
            $stmt = $this->db->run($updateSql, $values);
            if ($stmt->rowCount() === 0) {
                $this->db->rollBack();
                throw new RuntimeException('Anda sudah absen pulang hari ini');
            }

            $this->db->commit();
        } catch (Exception $e) {
            if ($this->db->getPdo()->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return $this->status($adminId, $tanggal);
    }

    private function validateCoordinate($value, $min, $max, $label)
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            throw new InvalidArgumentException('Koordinat ' . $label . ' tidak valid');
        }
        $value = (float)$value;
        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException('Koordinat ' . $label . ' di luar rentang');
        }
        return $value;
    }

    private function assertInsideOffice($latitude, $longitude, $companyId = null)
    {
        $sql = 'SELECT latitude, longitude, radius_meter FROM office_locations';
        $params = array();
        if ($companyId !== null) {
            $sql .= ' WHERE company_id = ?';
            $params[] = (int)$companyId;
        }
        $lokasi = $this->db->selectAll($sql, $params);
        foreach ($lokasi as $l) {
            $lat = (float)$l['latitude'];
            $lng = (float)$l['longitude'];
            $radius = (float)$l['radius_meter'];

            $dLat = deg2rad($lat - $latitude);
            $dLng = deg2rad($lng - $longitude);
            $a = sin($dLat / 2) * sin($dLat / 2) +
                 cos(deg2rad($latitude)) * cos(deg2rad($lat)) *
                 sin($dLng / 2) * sin($dLng / 2);
            $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
            $jarak = 6371000 * $c;

            if ($jarak <= $radius) {
                return true;
            }
        }
        throw new InvalidArgumentException('Anda berada di luar area absensi yang diizinkan');
    }

    public function selfiePaths($id)
    {
        if (!$id) {
            return array();
        }
        $columns = array('id');
        foreach (array('selfie_masuk', 'selfie_pulang') as $column) {
            if ($this->hasColumn($column)) {
                $columns[] = $column;
            }
        }
        $row = $this->db->selectOne(
            'SELECT ' . implode(', ', $columns) . ' FROM admin_attendances WHERE id = ? LIMIT 1',
            array((int)$id)
        );
        if (!$row) {
            return array();
        }
        $paths = array();
        foreach (array('selfie_masuk', 'selfie_pulang') as $column) {
            if (!empty($row[$column])) {
                $paths[] = $row[$column];
            }
        }
        return $paths;
    }

    /**
     * Hapus riwayat absensi Admin HR berdasarkan id.
     * Akses sudah dibatasi endpoint untuk role HR.
     * Mengembalikan false bila data tidak ditemukan.
     */
    public function delete($id)
    {
        if (!$id) {
            return false;
        }
        $row = $this->db->selectOne(
            'SELECT id FROM admin_attendances WHERE id = ? LIMIT 1',
            array((int)$id)
        );
        if (!$row) {
            return false;
        }
        $this->db->run(
            'DELETE FROM admin_attendances WHERE id = ?',
            array((int)$id)
        );
        return true;
    }

    private function isDuplicateKeyError(Exception $e)
    {
        return $e instanceof PDOException && $e->getCode() === '23000';
    }

}
