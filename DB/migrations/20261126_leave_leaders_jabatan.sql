-- Migrasi idempotent: tambah kolom jabatan pada leave_leaders.
DROP PROCEDURE IF EXISTS hris_add_leave_leaders_jabatan;
DELIMITER $$
CREATE PROCEDURE hris_add_leave_leaders_jabatan()
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables
              WHERE table_schema = DATABASE() AND table_name = 'leave_leaders') THEN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                    WHERE table_schema = DATABASE() AND table_name = 'leave_leaders' AND column_name = 'jabatan') THEN
      ALTER TABLE `leave_leaders` ADD COLUMN `jabatan` VARCHAR(150) NULL DEFAULT NULL AFTER `keterangan`;
    END IF;
  END IF;
END$$
DELIMITER ;
CALL hris_add_leave_leaders_jabatan();
DROP PROCEDURE IF EXISTS hris_add_leave_leaders_jabatan;
