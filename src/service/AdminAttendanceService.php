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

    public function status($adminId, $tanggal = null)
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
        $row = $this->db->selectOne(
            'SELECT ' . $columns . ' FROM admin_attendances WHERE admin_id = ? AND tanggal = ? LIMIT 1',
            array((int)$adminId, $tanggal)
        );

        return array(
            'id' => $row ? (int)$row['id'] : null,
            'tanggal' => $tanggal,
            'sudah_masuk' => $row && $row['waktu_masuk'] !== null,
            'jam_masuk' => $row ? $row['waktu_masuk'] : null,
            'sudah_pulang' => $row && $row['waktu_pulang'] !== null,
            'jam_pulang' => $row ? $row['waktu_pulang'] : null,
            'selfie_in_url' => $row && !empty($row['selfie_masuk'])
                ? 'selfie.php?id=' . (int)$row['id'] . '&type=in&scope=admin' : null,
            'selfie_out_url' => $row && !empty($row['selfie_pulang'])
                ? 'selfie.php?id=' . (int)$row['id'] . '&type=out&scope=admin' : null,
        );
    }

    /**
     * Check-in Admin HR. Identitas berasal dari token (adminId), bukan request.
     */
    public function checkIn($adminId, $latitude, $longitude, $gpsAccuracy, $requestId = null, $selfie = null)
    {
        if (!$adminId) {
            throw new InvalidArgumentException('Identitas Admin HR tidak valid');
        }

        $latitude = $this->validateCoordinate($latitude, -90, 90, 'latitude');
        $longitude = $this->validateCoordinate($longitude, -180, 180, 'longitude');
        $this->assertInsideOffice($latitude, $longitude);

        $tanggal = Config::companyToday();
        $now = Config::companyNow();
        $selfieData = AttendanceService::storeSelfie($selfie, $tanggal, $adminId, 'selfie_masuk', 'admin');

        $this->db->beginTransaction();
        try {
            $existing = $this->db->selectOne(
                'SELECT id FROM admin_attendances WHERE admin_id = ? AND tanggal = ? FOR UPDATE',
                array((int)$adminId, $tanggal)
            );
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

        return $this->status($adminId, $tanggal);
    }

    /**
     * Check-out Admin HR.
     */
    public function checkOut($adminId, $waktuLokal, $latitude, $longitude, $selfie = null)
    {
        if (!$adminId) {
            throw new InvalidArgumentException('Identitas Admin HR tidak valid');
        }

        $now = Config::companyNow();
        $tanggal = $now->format('Y-m-d');
        $waktu = $now->format('H:i:s');

        $latitude = $this->validateCoordinate($latitude, -90, 90, 'latitude');
        $longitude = $this->validateCoordinate($longitude, -180, 180, 'longitude');
        $this->assertInsideOffice($latitude, $longitude);
        $selfieData = AttendanceService::storeSelfie($selfie, $tanggal, $adminId, 'selfie_pulang', 'admin');

        $this->db->beginTransaction();
        try {
            $row = $this->db->selectOne(
                'SELECT id, waktu_pulang FROM admin_attendances WHERE admin_id = ? AND tanggal = ? FOR UPDATE',
                array((int)$adminId, $tanggal)
            );
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
            $stmt = $this->db->run(
                'UPDATE admin_attendances SET ' . implode(', ', $updates) .
                ' WHERE id = ? AND admin_id = ? AND waktu_pulang IS NULL',
                $values
            );
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

    private function assertInsideOffice($latitude, $longitude)
    {
        $lokasi = $this->db->selectAll('SELECT latitude, longitude, radius_meter FROM office_locations');
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
