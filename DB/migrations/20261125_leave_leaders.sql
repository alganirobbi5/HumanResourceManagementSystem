-- Migrasi idempotent: Leader persetujuan cuti dikelola HR.
CREATE TABLE IF NOT EXISTS `leave_leaders` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL UNIQUE,
  `employee_id` INT(11) NULL DEFAULT NULL,
  `keterangan` VARCHAR(100) NULL DEFAULT NULL,
  `added_by` INT(11) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_leave_leaders_employee` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP PROCEDURE IF EXISTS hris_add_leave_leader_columns;
DELIMITER $$
CREATE PROCEDURE hris_add_leave_leader_columns()
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables
              WHERE table_schema = DATABASE() AND table_name = 'leave_requests') THEN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                    WHERE table_schema = DATABASE() AND table_name = 'leave_requests' AND column_name = 'leader_user_id') THEN
      ALTER TABLE `leave_requests` ADD COLUMN `leader_user_id` INT(11) NULL DEFAULT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                    WHERE table_schema = DATABASE() AND table_name = 'leave_requests' AND column_name = 'approved_by_user_id') THEN
      ALTER TABLE `leave_requests` ADD COLUMN `approved_by_user_id` INT(11) NULL DEFAULT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                    WHERE table_schema = DATABASE() AND table_name = 'leave_requests' AND column_name = 'approved_by_name') THEN
      ALTER TABLE `leave_requests` ADD COLUMN `approved_by_name` VARCHAR(150) NULL DEFAULT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                    WHERE table_schema = DATABASE() AND table_name = 'leave_requests' AND column_name = 'catatan_leader') THEN
      ALTER TABLE `leave_requests` ADD COLUMN `catatan_leader` TEXT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                    WHERE table_schema = DATABASE() AND table_name = 'leave_requests' AND column_name = 'decided_at') THEN
      ALTER TABLE `leave_requests` ADD COLUMN `decided_at` DATETIME NULL DEFAULT NULL;
    END IF;
  END IF;

  IF EXISTS (SELECT 1 FROM information_schema.tables
              WHERE table_schema = DATABASE() AND table_name = 'leaves') THEN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                    WHERE table_schema = DATABASE() AND table_name = 'leaves' AND column_name = 'leader_user_id') THEN
      ALTER TABLE `leaves` ADD COLUMN `leader_user_id` INT(11) NULL DEFAULT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                    WHERE table_schema = DATABASE() AND table_name = 'leaves' AND column_name = 'approved_by_user_id') THEN
      ALTER TABLE `leaves` ADD COLUMN `approved_by_user_id` INT(11) NULL DEFAULT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                    WHERE table_schema = DATABASE() AND table_name = 'leaves' AND column_name = 'approved_by_name') THEN
      ALTER TABLE `leaves` ADD COLUMN `approved_by_name` VARCHAR(150) NULL DEFAULT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                    WHERE table_schema = DATABASE() AND table_name = 'leaves' AND column_name = 'catatan_leader') THEN
      ALTER TABLE `leaves` ADD COLUMN `catatan_leader` TEXT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                    WHERE table_schema = DATABASE() AND table_name = 'leaves' AND column_name = 'decided_at') THEN
      ALTER TABLE `leaves` ADD COLUMN `decided_at` DATETIME NULL DEFAULT NULL;
    END IF;
  END IF;

  IF EXISTS (SELECT 1 FROM information_schema.tables
              WHERE table_schema = DATABASE() AND table_name = 'employee_leavs') THEN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                    WHERE table_schema = DATABASE() AND table_name = 'employee_leavs' AND column_name = 'leader_user_id') THEN
      ALTER TABLE `employee_leavs` ADD COLUMN `leader_user_id` INT(11) NULL DEFAULT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                    WHERE table_schema = DATABASE() AND table_name = 'employee_leavs' AND column_name = 'approved_by_user_id') THEN
      ALTER TABLE `employee_leavs` ADD COLUMN `approved_by_user_id` INT(11) NULL DEFAULT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                    WHERE table_schema = DATABASE() AND table_name = 'employee_leavs' AND column_name = 'approved_by_name') THEN
      ALTER TABLE `employee_leavs` ADD COLUMN `approved_by_name` VARCHAR(150) NULL DEFAULT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                    WHERE table_schema = DATABASE() AND table_name = 'employee_leavs' AND column_name = 'catatan_leader') THEN
      ALTER TABLE `employee_leavs` ADD COLUMN `catatan_leader` TEXT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                    WHERE table_schema = DATABASE() AND table_name = 'employee_leavs' AND column_name = 'decided_at') THEN
      ALTER TABLE `employee_leavs` ADD COLUMN `decided_at` DATETIME NULL DEFAULT NULL;
    END IF;
  END IF;
END$$
DELIMITER ;
CALL hris_add_leave_leader_columns();
DROP PROCEDURE IF EXISTS hris_add_leave_leader_columns;
