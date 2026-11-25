-- Migrasi idempotent: leader pada pengajuan izin keluar.
DROP PROCEDURE IF EXISTS hris_add_izin_keluar_leader_columns;
DELIMITER $$
CREATE PROCEDURE hris_add_izin_keluar_leader_columns()
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables
              WHERE table_schema = DATABASE() AND table_name = 'izin_keluar') THEN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                    WHERE table_schema = DATABASE() AND table_name = 'izin_keluar' AND column_name = 'leader_user_id') THEN
      ALTER TABLE `izin_keluar` ADD COLUMN `leader_user_id` INT(11) NULL DEFAULT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                    WHERE table_schema = DATABASE() AND table_name = 'izin_keluar' AND column_name = 'approved_by_user_id') THEN
      ALTER TABLE `izin_keluar` ADD COLUMN `approved_by_user_id` INT(11) NULL DEFAULT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                    WHERE table_schema = DATABASE() AND table_name = 'izin_keluar' AND column_name = 'approved_by_name') THEN
      ALTER TABLE `izin_keluar` ADD COLUMN `approved_by_name` VARCHAR(150) NULL DEFAULT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                    WHERE table_schema = DATABASE() AND table_name = 'izin_keluar' AND column_name = 'catatan_leader') THEN
      ALTER TABLE `izin_keluar` ADD COLUMN `catatan_leader` TEXT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                    WHERE table_schema = DATABASE() AND table_name = 'izin_keluar' AND column_name = 'decided_at') THEN
      ALTER TABLE `izin_keluar` ADD COLUMN `decided_at` DATETIME NULL DEFAULT NULL;
    END IF;
  END IF;
END$$
DELIMITER ;
CALL hris_add_izin_keluar_leader_columns();
DROP PROCEDURE IF EXISTS hris_add_izin_keluar_leader_columns;
