-- Migrasi idempotent: isolasi data master per company_id.
DROP PROCEDURE IF EXISTS hris_master_company_id;
DELIMITER $$
CREATE PROCEDURE hris_master_company_id()
BEGIN
  DECLARE done INT DEFAULT 0;
  DECLARE tbl VARCHAR(64);
  DECLARE cur CURSOR FOR
    SELECT 'office_locations' UNION ALL SELECT 'pengaturan_jam' UNION ALL
    SELECT 'departments' UNION ALL SELECT 'employee_designations';
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
CALL hris_master_company_id();
DROP PROCEDURE IF EXISTS hris_master_company_id;
