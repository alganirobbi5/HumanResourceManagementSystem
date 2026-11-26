<?php

require_once __DIR__ . '/../common/DBConnection.php';

class CompanyProfileService
{
    private $pdo;

    public function __construct(PDO $pdo = null)
    {
        $this->pdo = $pdo ?: DBConnection::pdo();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->ensureSchema();
    }

    private function hasColumn($table, $column)
    {
        return (bool)$this->pdo->query("SHOW COLUMNS FROM " . $table . " LIKE '" . $column . "'")->fetch();
    }

    private function ensureSchema()
    {
        if (!$this->pdo->query("SHOW TABLES LIKE 'company_profile'")->fetchColumn()) {
            $this->pdo->exec('CREATE TABLE company_profile (
                id INT(11) NOT NULL AUTO_INCREMENT,
                nama_perusahaan VARCHAR(150) NOT NULL,
                kode_perusahaan VARCHAR(50) NOT NULL,
                company_id INT(11) NULL DEFAULT NULL,
                alamat TEXT NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_company_profile_company (company_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            return;
        }
        if (!$this->hasColumn('company_profile', 'company_id')) {
            $this->pdo->exec('ALTER TABLE company_profile ADD COLUMN company_id INT(11) NULL DEFAULT NULL AFTER kode_perusahaan');
            $this->pdo->exec('UPDATE company_profile SET company_id = 1 WHERE company_id IS NULL');
        }
        if (!$this->hasColumn('company_profile', 'alamat')) {
            $this->pdo->exec('ALTER TABLE company_profile ADD COLUMN alamat TEXT NULL AFTER company_id');
        }
    }

    public function rowForCompany($companyId)
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, company_id, nama_perusahaan, kode_perusahaan, alamat
             FROM company_profile WHERE company_id = ? ORDER BY id LIMIT 1'
        );
        $stmt->execute(array((int)$companyId));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function upsert($companyId, $nama, $kode, $alamat = null)
    {
        $row = $this->rowForCompany($companyId);
        if ($row) {
            $stmt = $this->pdo->prepare(
                'UPDATE company_profile SET nama_perusahaan = ?, kode_perusahaan = ?, alamat = ? WHERE id = ?'
            );
            $stmt->execute(array($nama, $kode, $alamat, (int)$row['id']));
            return (int)$row['id'];
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO company_profile (nama_perusahaan, kode_perusahaan, company_id, alamat)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute(array($nama, $kode, (int)$companyId, $alamat));
        return (int)$this->pdo->lastInsertId();
    }

    public function syncCompany($companyId, $nama, $kode)
    {
        if ($companyId === null || (int)$companyId <= 0) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE companies SET company_name = ?, company_code = ? WHERE id = ?'
        );
        $stmt->execute(array($nama, $kode, (int)$companyId));
        return $stmt->rowCount() > 0;
    }

    public function displayName($companyId, $fallback)
    {
        $row = $this->rowForCompany($companyId);
        if ($row && trim((string)$row['nama_perusahaan']) !== '') {
            return (string)$row['nama_perusahaan'];
        }
        return $fallback;
    }
}
