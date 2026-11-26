<?php

require_once __DIR__ . '/../common/DBConnection.php';

class HolidayService
{
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
            $this->pdo->exec('DROP TABLE IF EXISTS `holydays`');
            $this->pdo->exec('DROP TABLE IF EXISTS `holiday_informations`');
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci');
        } catch (PDOException $e) {
            // Biarkan method yang memakai tabel melaporkan kegagalan.
        }
    }

    public function listing($companyId, $year = null, $nationalOnly = false)
    {
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
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['company_id'] = $row['company_id'] !== null ? (int)$row['company_id'] : null;
            $row['is_national'] = $row['company_id'] === null;
        }
        return $rows;
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
