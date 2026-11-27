-- Migrasi idempotent: kolom Password di users sebagai cermin password
-- dari employees/admins. Superadmin tetap hanya di tabel users.
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

DROP PROCEDURE IF EXISTS hris_backfill_user_password;
DELIMITER $$
CREATE PROCEDURE hris_backfill_user_password()
BEGIN
  -- Superadmin tetap hanya di users (tidak masuk admins/employees).
  UPDATE `users` u
  SET u.`Password` = 'superadmin'
  WHERE u.`username` = 'superadmin' AND u.`role` = 'super_admin' AND u.`Password` IS NULL;

  -- Backfill untuk karyawan dan admin lama yang belum punya nilai Password.
  IF EXISTS (SELECT 1 FROM information_schema.tables
              WHERE table_schema = DATABASE() AND table_name = 'employees')
     AND EXISTS (SELECT 1 FROM information_schema.tables
              WHERE table_schema = DATABASE() AND table_name = 'admins') THEN
    UPDATE `users` u
    LEFT JOIN `employees` e ON e.id = u.employee_id
    LEFT JOIN `admins` a ON a.id = u.admin_id
    SET u.`Password` = COALESCE(e.password, a.password)
    WHERE u.`Password` IS NULL AND (e.id IS NOT NULL OR a.id IS NOT NULL);
  END IF;
END$$
DELIMITER ;
CALL hris_backfill_user_password();
DROP PROCEDURE IF EXISTS hris_backfill_user_password;
