-- Migrasi: identitas API Admin HR + absensi pribadi Admin HR
-- BELUM dijalankan ke produksi. Jalankan setelah backup terverifikasi.
-- Tidak membuat employee record; tidak memakai admins.id sebagai employee_id.

-- 1. users.admin_id -> relasi eksplisit ke admins.id (nullable, unik)
DROP PROCEDURE IF EXISTS hris_add_users_admin_id;
DELIMITER $$
CREATE PROCEDURE hris_add_users_admin_id()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = 'users'
                   AND column_name = 'admin_id') THEN
    ALTER TABLE `users` ADD COLUMN `admin_id` int(11) NULL AFTER `employee_id`;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.statistics
                 WHERE table_schema = DATABASE() AND table_name = 'users'
                   AND index_name = 'uq_users_admin_id') THEN
    ALTER TABLE `users` ADD UNIQUE KEY `uq_users_admin_id` (`admin_id`);
  END IF;
END$$
DELIMITER ;
CALL hris_add_users_admin_id();
DROP PROCEDURE IF EXISTS hris_add_users_admin_id;

-- 2. Tabel absensi khusus Admin HR
CREATE TABLE IF NOT EXISTS `admin_attendances` (
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
  KEY `idx_admin_attendance_tanggal` (`tanggal`),
  CONSTRAINT `fk_admin_attendance_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Verifikasi (read-only):
-- SHOW COLUMNS FROM users LIKE 'admin_id';
-- SHOW COLUMNS FROM admin_attendances;
