-- Migrasi idempotent: kolom Password di users, identitas admin, dan super_admin di admins.
-- Jalankan: mysql -uroot hrm < DB/migrations/20261127_user_plaintext_password_admin_identity.sql

DROP PROCEDURE IF EXISTS hris_user_password_admin_columns;
DELIMITER $$
CREATE PROCEDURE hris_user_password_admin_columns()
BEGIN
  IF EXISTS (SELECT 1 FROM information_schema.tables
              WHERE table_schema = DATABASE() AND table_name = 'users') THEN

    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                    WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'Password') THEN
      SET @sql = 'ALTER TABLE `users` ADD COLUMN `Password` VARCHAR(255) NULL AFTER `password_hash`';
      PREPARE stmt FROM @sql;
      EXECUTE stmt;
      DEALLOCATE PREPARE stmt;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                    WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'admin_id') THEN
      SET @sql = 'ALTER TABLE `users` ADD COLUMN `admin_id` INT(11) NULL AFTER `employee_id`';
      PREPARE stmt FROM @sql;
      EXECUTE stmt;
      DEALLOCATE PREPARE stmt;
    END IF;

    SET @sql = 'ALTER TABLE `users` MODIFY COLUMN `role` ENUM(''employee'',''hr'',''super_admin'') NOT NULL DEFAULT ''employee''';
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;
END$$
DELIMITER ;
CALL hris_user_password_admin_columns();
DROP PROCEDURE IF EXISTS hris_user_password_admin_columns;

-- Super_admin juga merupakan admin: pastikan baris admins dan tautan users.admin_id.
INSERT INTO `admins` (`name`, `password`, `created_at`)
SELECT 'superadmin', 'superadmin', NOW()
WHERE NOT EXISTS (SELECT 1 FROM `admins` WHERE `name` = 'superadmin');

UPDATE `users`
SET `admin_id` = (SELECT `id` FROM `admins` WHERE `name` = 'superadmin' LIMIT 1),
    `Password` = COALESCE(`Password`, 'superadmin')
WHERE `username` = 'superadmin' AND `role` = 'super_admin' AND `admin_id` IS NULL;
