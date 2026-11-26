-- Migrasi idempotent: hari libur nasional dan perusahaan.
DROP TABLE IF EXISTS `holydays`;
DROP TABLE IF EXISTS `holiday_informations`;

CREATE TABLE IF NOT EXISTS `holidays` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `company_id` INT(11) NULL DEFAULT NULL,
  `holiday_date` DATE NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `description` TEXT NULL,
  `created_by` INT(11) NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_holidays_company_date` (`company_id`, `holiday_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
