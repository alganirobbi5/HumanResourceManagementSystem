-- Migrasi idempotent: kolom logo pada profil perusahaan.
-- Jalankan: mysql -uroot hrm < DB/migrations/20261127_add_company_logo.sql

DROP PROCEDURE IF EXISTS hris_add_company_logo;
DELIMITER $$
CREATE PROCEDURE hris_add_company_logo()
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables
              WHERE table_schema = DATABASE() AND table_name = 'company_profile')
     AND NOT EXISTS (SELECT 1 FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'company_profile' AND column_name = 'logo') THEN
    ALTER TABLE `company_profile` ADD COLUMN `logo` LONGTEXT NULL AFTER `alamat`;
  END IF;
END$$
DELIMITER ;
CALL hris_add_company_logo();
DROP PROCEDURE IF EXISTS hris_add_company_logo;
