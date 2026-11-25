-- Migrasi idempotent: absensi Admin HR beserta selfie masuk/pulang.
-- Tidak menghapus atau mengubah data yang sudah ada.
DROP PROCEDURE IF EXISTS hris_ensure_admin_attendance_selfie;
DELIMITER $$
CREATE PROCEDURE hris_ensure_admin_attendance_selfie()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.tables
                  WHERE table_schema = DATABASE() AND table_name = 'admin_attendances') THEN
    CREATE TABLE `admin_attendances` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `admin_id` int(11) NOT NULL,
      `tanggal` date NOT NULL,
      `waktu_masuk` time DEFAULT NULL,
      `waktu_pulang` time DEFAULT NULL,
      `latitude_masuk` decimal(10,7) DEFAULT NULL,
      `longitude_masuk` decimal(10,7) DEFAULT NULL,
      `latitude_pulang` decimal(10,7) DEFAULT NULL,
      `longitude_pulang` decimal(10,7) DEFAULT NULL,
      `selfie_masuk` varchar(255) DEFAULT NULL,
      `selfie_pulang` varchar(255) DEFAULT NULL,
      `selfie_masuk_mime` varchar(100) DEFAULT NULL,
      `selfie_pulang_mime` varchar(100) DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uq_admin_attendance_date` (`admin_id`, `tanggal`),
      KEY `idx_admin_attendance_tanggal` (`tanggal`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  END IF;

  IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                  WHERE table_schema = DATABASE() AND table_name = 'admin_attendances'
                    AND column_name = 'selfie_masuk') THEN
    ALTER TABLE `admin_attendances` ADD COLUMN `selfie_masuk` varchar(255) DEFAULT NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                  WHERE table_schema = DATABASE() AND table_name = 'admin_attendances'
                    AND column_name = 'selfie_pulang') THEN
    ALTER TABLE `admin_attendances` ADD COLUMN `selfie_pulang` varchar(255) DEFAULT NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                  WHERE table_schema = DATABASE() AND table_name = 'admin_attendances'
                    AND column_name = 'selfie_masuk_mime') THEN
    ALTER TABLE `admin_attendances` ADD COLUMN `selfie_masuk_mime` varchar(100) DEFAULT NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                  WHERE table_schema = DATABASE() AND table_name = 'admin_attendances'
                    AND column_name = 'selfie_pulang_mime') THEN
    ALTER TABLE `admin_attendances` ADD COLUMN `selfie_pulang_mime` varchar(100) DEFAULT NULL;
  END IF;
END$$
DELIMITER ;
CALL hris_ensure_admin_attendance_selfie();
DROP PROCEDURE IF EXISTS hris_ensure_admin_attendance_selfie;
