<?php

require_once __DIR__ . '/../common/DBConnection.php';
require_once __DIR__ . '/../common/Config.php';

class AdminAttendanceService
{
    private $db;

    public function __construct(DBConnection $db = null)
    {
        $this->db = $db ?: new DBConnection();
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

        $row = $this->db->selectOne(
            'SELECT waktu_masuk, waktu_pulang FROM admin_attendances WHERE admin_id = ? AND tanggal = ? LIMIT 1',
            array((int)$adminId, $tanggal)
        );

        return array(
            'tanggal' => $tanggal,
            'sudah_masuk' => $row && $row['waktu_masuk'] !== null,
            'jam_masuk' => $row ? $row['waktu_masuk'] : null,
            'sudah_pulang' => $row && $row['waktu_pulang'] !== null,
            'jam_pulang' => $row ? $row['waktu_pulang'] : null,
        );
    }

    /**
     * Check-in Admin HR.
     * Identitas berasal dari token (adminId), bukan dari request.
     */
    public function checkIn($adminId, $latitude, $longitude, $gpsAccuracy, $requestId = null)
    {
        if (!$adminId) {
            throw new InvalidArgumentException('Identitas Admin HR tidak valid');
        }

        $latitude = $this->validateCoordinate($latitude, -90, 90, 'latitude');
        $longitude = $this->validateCoordinate($longitude, -180, 180, 'longitude');
        $this->assertInsideOffice($latitude, $longitude);

        $tanggal = Config::companyToday();
        $now = Config::companyNow();

        $this->db->beginTransaction();
        try {
            // Kunci baris tanggal tersebut agar dua request bersamaan tidak duplikat.
            $existing = $this->db->selectOne(
                'SELECT id FROM admin_attendances WHERE admin_id = ? AND tanggal = ? FOR UPDATE',
                array((int)$adminId, $tanggal)
            );
            if ($existing) {
                $this->db->rollBack();
                throw new RuntimeException('Anda sudah melakukan absen masuk hari ini');
            }

            $this->db->run(
                'INSERT INTO admin_attendances
                    (admin_id, tanggal, waktu_masuk, latitude_masuk, longitude_masuk,
                     created_at, updated_at)
                  VALUES (?, ?, ?, ?, ?, ?, ?)',
                array(
                    (int)$adminId,
                    $tanggal,
                    $now->format('H:i:s'),
                    $latitude,
                    $longitude,
                    $now->format('Y-m-d H:i:s'),
                    $now->format('Y-m-d H:i:s'),
                )
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
    public function checkOut($adminId, $waktuLokal, $latitude, $longitude)
    {
        if (!$adminId) {
            throw new InvalidArgumentException('Identitas Admin HR tidak valid');
        }

        $timestamp = strtotime((string)$waktuLokal);
        if (!$timestamp) {
            throw new InvalidArgumentException('Waktu absen pulang tidak valid');
        }
        $tanggal = date('Y-m-d', $timestamp);
        $waktu = date('H:i:s', $timestamp);

        $latitude = $this->validateCoordinate($latitude, -90, 90, 'latitude');
        $longitude = $this->validateCoordinate($longitude, -180, 180, 'longitude');
        $this->assertInsideOffice($latitude, $longitude);

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

            $stmt = $this->db->run(
                'UPDATE admin_attendances
                  SET waktu_pulang = ?, latitude_pulang = ?, longitude_pulang = ?,
                      updated_at = ?
                  WHERE id = ? AND admin_id = ? AND waktu_pulang IS NULL',
                array(
                    $waktu,
                    $latitude,
                    $longitude,
                    Config::companyNow()->format('Y-m-d H:i:s'),
                    (int)$row['id'],
                    (int)$adminId,
                )
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

    private function isDuplicateKeyError(Exception $e)
    {
        return $e instanceof PDOException && $e->getCode() === '23000';
    }

}
