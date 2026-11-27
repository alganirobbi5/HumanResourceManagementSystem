<?php

require_once __DIR__ . '/../common/DBConnection.php';

class HolidayService
{
    const ICS_URL = 'https://calendar.google.com/calendar/ical/id.indonesian%23holiday%40group.v.calendar.google.com/public/basic.ics';

    private static $autoSynced = false;
    private $pdo;

    public function __construct(PDO $pdo = null)
    {
        $this->pdo = $pdo ?: DBConnection::pdo();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->ensureSchema();
    }

    private function ensureSchema()
    {
        try {
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS `holidays` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `company_id` INT(11) NULL DEFAULT NULL,
                `holiday_date` DATE NOT NULL,
                `title` VARCHAR(150) NOT NULL,
                `description` TEXT NULL,
                `created_by` INT(11) NULL DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                INDEX `idx_holidays_company_date` (`company_id`, `holiday_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        } catch (PDOException $e) {
            // Biarkan method yang memakai tabel melaporkan kegagalan.
        }
    }

    private function httpGet($url)
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'HRMS Holiday Sync/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
        ));
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($error !== '' || $status < 200 || $status >= 300 || !is_string($body) || $body === '') {
            return null;
        }
        return $body;
    }

    private function decodeIcsText($text)
    {
        $text = str_replace(array('\\n', '\\N'), ' ', (string)$text);
        $text = str_replace(array('\\,', '\\;', '\\\\'), array(',', ';', '\\'), $text);
        return trim(html_entity_decode($text, ENT_QUOTES, 'UTF-8'));
    }

    private function parseIcs($body)
    {
        $body = str_replace(array("\r\n", "\r"), "\n", (string)$body);
        $unfolded = array();
        foreach (explode("\n", $body) as $line) {
            if ($line !== '' && ($line[0] === ' ' || $line[0] === "\t") && $unfolded) {
                $unfolded[count($unfolded) - 1] .= substr($line, 1);
            } else {
                $unfolded[] = $line;
            }
        }

        $events = array();
        $inEvent = false;
        $date = null;
        $summary = null;
        foreach ($unfolded as $line) {
            if (strpos($line, 'BEGIN:VEVENT') === 0) {
                $inEvent = true;
                $date = null;
                $summary = null;
                continue;
            }
            if (strpos($line, 'END:VEVENT') === 0) {
                if ($inEvent && $date !== null && $summary !== null && $summary !== '') {
                    $events[$date] = $summary;
                }
                $inEvent = false;
                continue;
            }
            if (!$inEvent) {
                continue;
            }
            if (preg_match('/^DTSTART(?:;[^:]*)?:(\d{8})/', $line, $m)) {
                $year = substr($m[1], 0, 4);
                $month = substr($m[1], 4, 2);
                $day = substr($m[1], 6, 2);
                if (checkdate((int)$month, (int)$day, (int)$year)) {
                    $date = $year . '-' . $month . '-' . $day;
                }
            } elseif (preg_match('/^SUMMARY(?:;[^:]*)?:(.*)$/u', $line, $m)) {
                $summary = $this->decodeIcsText($m[1]);
            }
        }
        return $events;
    }

    private function fetchFallback($year)
    {
        $urls = array(
            'https://dayoffapi.vercel.app/api?year=' . (int)$year,
            'https://api-harilibur.vercel.app/api?year=' . (int)$year,
        );
        foreach ($urls as $url) {
            $body = $this->httpGet($url);
            if ($body === null) {
                continue;
            }
            $decoded = json_decode($body, true);
            if (!is_array($decoded)) {
                continue;
            }
            $events = array();
            foreach ($decoded as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $date = '';
                foreach (array('tanggal', 'holiday_date', 'date', 'tgl') as $key) {
                    if (!empty($item[$key])) {
                        $date = substr((string)$item[$key], 0, 10);
                        break;
                    }
                }
                $title = '';
                foreach (array('keterangan', 'holiday_name', 'name', 'title', 'nama') as $key) {
                    if (!empty($item[$key])) {
                        $title = trim((string)$item[$key]);
                        break;
                    }
                }
                if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)
                    && checkdate((int)$m[2], (int)$m[3], (int)$m[1]) && $title !== '') {
                    $events[$date] = $title;
                }
            }
            if ($events) {
                return $events;
            }
        }
        return array();
    }

    private function hasNationalYear($year)
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM holidays
             WHERE company_id IS NULL AND holiday_date BETWEEN ? AND ? LIMIT 1'
        );
        $stmt->execute(array((int)$year . '-01-01', (int)$year . '-12-31'));
        return $stmt->fetch() !== false;
    }

    private function saveNational(array $events)
    {
        $saved = 0;
        foreach ($events as $date => $title) {
            $title = trim((string)$title);
            if ($title === '' || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string)$date, $m)
                || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
                continue;
            }
            if (function_exists('mb_substr')) {
                $title = mb_substr($title, 0, 150, 'UTF-8');
            } else {
                $title = substr($title, 0, 150);
            }
            $description = 'Sinkronisasi otomatis kalender libur nasional Indonesia';
            $check = $this->pdo->prepare(
                'SELECT id FROM holidays WHERE company_id IS NULL AND holiday_date = ? LIMIT 1'
            );
            $check->execute(array($date));
            $id = $check->fetchColumn();
            if ($id) {
                $this->pdo->prepare('UPDATE holidays SET title = ?, description = ? WHERE id = ?')
                    ->execute(array($title, $description, (int)$id));
            } else {
                $this->pdo->prepare(
                    'INSERT INTO holidays (company_id, holiday_date, title, description, created_by)
                     VALUES (NULL, ?, ?, ?, NULL)'
                )->execute(array($date, $title, $description));
            }
            $saved++;
        }
        return $saved;
    }

    public function syncNational($force = false, $years = null)
    {
        if (!is_array($years) || !$years) {
            $years = array((int)date('Y'), (int)date('Y') + 1);
        }
        $pending = array();
        foreach ($years as $year) {
            $year = (int)$year;
            if ($force || !$this->hasNationalYear($year)) {
                $pending[] = $year;
            }
        }
        self::$autoSynced = true;
        if (!$pending) {
            return array('synced' => 0, 'years' => array(), 'skipped' => true, 'source' => null);
        }

        $source = null;
        $allEvents = array();
        $ics = $this->httpGet(self::ICS_URL);
        if ($ics !== null) {
            $allEvents = $this->parseIcs($ics);
            if ($allEvents) {
                $source = 'google';
            }
        }

        $saved = 0;
        foreach ($pending as $year) {
            $events = array();
            foreach ($allEvents as $date => $title) {
                if ((int)substr($date, 0, 4) === $year) {
                    $events[$date] = $title;
                }
            }
            if (!$events) {
                $events = $this->fetchFallback($year);
                if ($events) {
                    $source = $source === null ? 'fallback' : $source . '+fallback';
                }
            }
            $saved += $this->saveNational($events);
        }
        return array('synced' => $saved, 'years' => $pending, 'skipped' => false, 'source' => $source);
    }

    private function decorate(array $rows)
    {
        foreach ($rows as &$row) {
            $row['company_id'] = $row['company_id'] !== null ? (int)$row['company_id'] : null;
            $row['is_national'] = $row['company_id'] === null;
        }
        return $rows;
    }

    public function listing($companyId, $year = null, $nationalOnly = false)
    {
        if (!self::$autoSynced) {
            $this->syncNational();
        }
        $where = array();
        $params = array();
        if ($nationalOnly || $companyId === null) {
            $where[] = 'company_id IS NULL';
        } else {
            $where[] = '(company_id IS NULL OR company_id = ?)';
            $params[] = (int)$companyId;
        }
        if ($year !== null && $year !== '') {
            $where[] = 'YEAR(holiday_date) = ?';
            $params[] = (int)$year;
        }
        $stmt = $this->pdo->prepare(
            'SELECT id, company_id, holiday_date, title, description, created_by, created_at
             FROM holidays WHERE ' . implode(' AND ', $where) . ' ORDER BY holiday_date ASC, id ASC'
        );
        $stmt->execute($params);
        return $this->decorate($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function upcoming($year = null)
    {
        if (!self::$autoSynced) {
            $this->syncNational();
        }
        $endYear = $year !== null && $year !== '' ? (int)$year : (int)date('Y') + 1;
        $stmt = $this->pdo->prepare(
            'SELECT id, company_id, holiday_date, title, description, created_by, created_at
             FROM holidays
             WHERE company_id IS NULL AND holiday_date >= CURDATE() AND holiday_date <= ?
             ORDER BY holiday_date ASC, id ASC'
        );
        $stmt->execute(array($endYear . '-12-31'));
        return $this->decorate($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function companyListing($companyId, $year = null)
    {
        $sql = 'SELECT id, company_id, holiday_date, title, description, created_by, created_at
                FROM holidays WHERE company_id = ?';
        $params = array((int)$companyId);
        if ($year !== null && $year !== '') {
            $sql .= ' AND YEAR(holiday_date) = ?';
            $params[] = (int)$year;
        }
        $sql .= ' ORDER BY holiday_date ASC, id ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $this->decorate($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function create($companyId, $holidayDate, $title, $description, $createdBy)
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO holidays (company_id, holiday_date, title, description, created_by)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute(array(
            $companyId !== null ? (int)$companyId : null,
            $holidayDate,
            $title,
            $description !== '' ? $description : null,
            $createdBy !== null ? (int)$createdBy : null
        ));
        return (int)$this->pdo->lastInsertId();
    }

    public function remove($id, $companyId, $isSuperAdmin)
    {
        $stmt = $this->pdo->prepare('SELECT id, company_id FROM holidays WHERE id = ? LIMIT 1');
        $stmt->execute(array((int)$id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Hari libur tidak ditemukan', 404);
        }
        if ($isSuperAdmin) {
            if ($row['company_id'] !== null) {
                throw new RuntimeException('Super Admin hanya dapat menghapus libur nasional', 403);
            }
        } else {
            if ($row['company_id'] === null || $companyId === null
                || (int)$row['company_id'] !== (int)$companyId) {
                throw new RuntimeException('HR hanya dapat menghapus libur perusahaan sendiri', 403);
            }
        }
        $this->pdo->prepare('DELETE FROM holidays WHERE id = ?')->execute(array((int)$id));
        return true;
    }

    public function datesFor($companyId)
    {
        if (!self::$autoSynced) {
            $this->syncNational();
        }
        $where = 'company_id IS NULL';
        $params = array();
        if ($companyId !== null) {
            $where .= ' OR company_id = ?';
            $params[] = (int)$companyId;
        }
        $stmt = $this->pdo->prepare('SELECT DISTINCT holiday_date FROM holidays WHERE ' . $where);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
