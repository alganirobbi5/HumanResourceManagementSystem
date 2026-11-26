-- Migrasi idempotent: arsitektur multi-perusahaan dan akun super_admin.
CREATE TABLE IF NOT EXISTS `companies` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `company_code` VARCHAR(50) NOT NULL,
  `company_name` VARCHAR(150) NOT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_companies_code` (`company_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `companies` (`id`, `company_code`, `company_name`, `status`)
VALUES (1, 'DEFAULT', 'Perusahaan Default', 1);

DROP PROCEDURE IF EXISTS hris_multi_company_columns;
DELIMITER $$
CREATE PROCEDURE hris_multi_company_columns()
BEGIN
  DECLARE done INT DEFAULT 0;
  DECLARE tbl VARCHAR(64);
  DECLARE cur CURSOR FOR
    SELECT 'users' UNION ALL SELECT 'employees' UNION ALL
    SELECT 'attendances' UNION ALL SELECT 'absensi' UNION ALL
    SELECT 'admin_attendances' UNION ALL SELECT 'leaves' UNION ALL
    SELECT 'cuti' UNION ALL SELECT 'leave_requests' UNION ALL
    SELECT 'employee_leaves' UNION ALL SELECT 'employee_leavs' UNION ALL
    SELECT 'izin_keluar' UNION ALL SELECT 'leave_leaders' UNION ALL
    SELECT 'notices' UNION ALL SELECT 'pengumuman' UNION ALL
    SELECT 'leave_balances';
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
  OPEN cur;
  read_loop: LOOP
    FETCH cur INTO tbl;
    IF done = 1 THEN LEAVE read_loop; END IF;
    IF EXISTS (SELECT 1 FROM information_schema.tables
                WHERE table_schema = DATABASE() AND table_name = tbl) THEN
      IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                      WHERE table_schema = DATABASE() AND table_name = tbl AND column_name = 'company_id') THEN
        SET @sql = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN `company_id` INT(11) NULL DEFAULT NULL');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
      END IF;
      SET @sql = CONCAT('UPDATE `', tbl, '` SET `company_id` = 1 WHERE `company_id` IS NULL');
      PREPARE stmt FROM @sql;
      EXECUTE stmt;
      DEALLOCATE PREPARE stmt;
    END IF;
  END LOOP;
  CLOSE cur;
END$$
DELIMITER ;
CALL hris_multi_company_columns();
DROP PROCEDURE IF EXISTS hris_multi_company_columns;

INSERT INTO `users` (`username`, `password_hash`, `role`, `employee_id`, `company_id`, `is_active`)
SELECT 'superadmin', '$2y$10$xqP2rxbHSRRgSHwLJyVsn.RssIOFJTJL6bhJrKFC.qY55mFUvDuDu', 'super_admin', NULL, NULL, 1
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `username` = 'superadmin');
