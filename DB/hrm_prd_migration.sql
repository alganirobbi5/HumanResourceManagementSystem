-- =====================================================================
-- Migration fitur Absensi, Cuti, dan Export Laporan (PRD v1.0)
-- Jalankan sekali pada database `hrm` yang sudah ada (tidak menghapus
-- tabel lama yang masih dipakai modul lain).
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+07:00';
-- Tabel lama masih memakai default tanggal nol; longgarkan sql_mode saat migrasi.
SET SESSION sql_mode = '';

-- ---------------------------------------------------------------------
-- 1. users (akun autentikasi terpisah dari profil karyawan)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('employee','hr') NOT NULL DEFAULT 'employee',
  `employee_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `api_token` varchar(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  KEY `idx_users_employee` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 2. attendances: penambahan kolom sesuai PRD (tabel lama dipakai ulang)
-- ---------------------------------------------------------------------
ALTER TABLE `attendances`
  MODIFY `clock_in` time DEFAULT NULL,
  MODIFY `clock_out` time DEFAULT NULL,
  MODIFY `total_work` float DEFAULT NULL,
  MODIFY `status` varchar(20) NOT NULL DEFAULT 'recorded',
  ADD COLUMN `latitude` decimal(10,7) DEFAULT NULL AFTER `date`,
  ADD COLUMN `longitude` decimal(10,7) DEFAULT NULL AFTER `latitude`,
  ADD COLUMN `gps_accuracy` float DEFAULT NULL AFTER `longitude`,
  ADD COLUMN `selfie_path` varchar(255) DEFAULT NULL AFTER `gps_accuracy`,
  ADD COLUMN `selfie_mime_type` varchar(100) DEFAULT NULL AFTER `selfie_path`,
  ADD COLUMN `selfie_size` int(11) DEFAULT NULL AFTER `selfie_mime_type`,
  ADD COLUMN `request_id` varchar(64) DEFAULT NULL AFTER `selfie_size`,
  ADD KEY `idx_attendances_employee_date` (`employee_id`, `date`),
  ADD KEY `idx_attendances_date` (`date`);

-- ---------------------------------------------------------------------
-- 3. leave_balances (saldo cuti karyawan)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `leave_balances` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `leave_type` varchar(30) NOT NULL DEFAULT 'annual',
  `entitlement_year` year NOT NULL,
  `entitled_days` decimal(5,1) NOT NULL DEFAULT 0,
  `used_days` decimal(5,1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_leave_balance` (`employee_id`, `leave_type`, `entitlement_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 4. leave_requests (pengajuan cuti)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `leave_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `reason` text NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_days` decimal(5,1) NOT NULL,
  `replacement_employee_id` int(11) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `submitted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_leave_requests_employee` (`employee_id`),
  KEY `idx_leave_requests_status` (`status`),
  KEY `idx_leave_requests_dates` (`start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 5. notifications (notifikasi in-app)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recipient_user_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'general',
  `related_entity_type` varchar(50) DEFAULT NULL,
  `related_entity_id` int(11) DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_recipient` (`recipient_user_id`, `read_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 6. audit_logs
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `actor_user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `before_data` text,
  `after_data` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_entity` (`entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 7. Seed awal (akun awal — GANTI password setelah masuk produksi)
--    Employee 1 : karyawan biasa
--    Employee 2 : HR / atasan
--    password untuk keduanya: password123
-- ---------------------------------------------------------------------
INSERT INTO `employee_designations` (`id`, `title`, `status`, `created_by`, `create_date`)
VALUES (3, 'Administrator', 1, 'system', NOW())
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `status` = 1;

INSERT INTO `employees`
  (`id`, `first_name`, `last_name`, `user_name`, `password`, `gender`, `email`, `department_id`, `designation_id`, `mobile_no`, `status`, `create_by`, `created_date`)
VALUES
  (1, 'Budi', 'Santoso', 'budi', 'password123', 'Male', 'budi@company.test', 1, 2, '081200000001', 1, 'system', NOW()),
  (2, 'Siti', 'HR', 'siti', 'password123', 'Female', 'siti@company.test', 1, 1, '081200000002', 1, 'system', NOW()),
  (3, 'Razu', '', 'razu', 'razu123', 'Male', 'razu@company.test', 1, 3, '081200000003', 1, 'system', NOW())
ON DUPLICATE KEY UPDATE `first_name` = VALUES(`first_name`);

INSERT INTO `users` (`username`, `email`, `password_hash`, `role`, `employee_id`, `is_active`)
VALUES
  ('budi', 'budi@company.test', '$2y$10$5uvXRfdZF10.2NifEuDM7uGN2USvzpppXsV3QtF9lIpsGqj4wWmyq', 'employee', 1, 1),
  ('siti', 'siti@company.test', '$2y$10$5uvXRfdZF10.2NifEuDM7uGN2USvzpppXsV3QtF9lIpsGqj4wWmyq', 'hr', 2, 1)
ON DUPLICATE KEY UPDATE `role` = VALUES(`role`);

-- Hubungkan akun lama (tabel admins, mis. 'razu') ke profil karyawan bila NIK-nya sama.
UPDATE `users` u
JOIN `employees` e ON e.`user_name` = u.`username`
SET u.`employee_id` = e.`id`
WHERE u.`employee_id` IS NULL;

INSERT INTO `leave_balances` (`employee_id`, `leave_type`, `entitlement_year`, `entitled_days`, `used_days`)
SELECT e.id, 'annual', YEAR(CURDATE()), 12, 0
FROM employees e
WHERE e.id IN (1, 2, 3)
  AND NOT EXISTS (
    SELECT 1 FROM leave_balances lb
    WHERE lb.employee_id = e.id AND lb.leave_type = 'annual' AND lb.entitlement_year = YEAR(CURDATE())
  );
