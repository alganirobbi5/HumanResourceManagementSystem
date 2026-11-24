-- Migrasi: kolom selfie absen pulang pada tabel attendances.
-- Idempotent: hanya menambahkan kolom yang belum ada. Tidak ada DROP/UPDATE data.
DROP PROCEDURE IF EXISTS hris_add_selfie_out_columns;
DELIMITER $$
CREATE PROCEDURE hris_add_selfie_out_columns()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                  WHERE table_schema = DATABASE() AND table_name = 'attendances'
                    AND column_name = 'selfie_out_path') THEN
    ALTER TABLE `attendances` ADD COLUMN `selfie_out_path` varchar(255) DEFAULT NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                  WHERE table_schema = DATABASE() AND table_name = 'attendances'
                    AND column_name = 'selfie_out_mime_type') THEN
    ALTER TABLE `attendances` ADD COLUMN `selfie_out_mime_type` varchar(100) DEFAULT NULL;
  END IF;
END$$
DELIMITER ;
CALL hris_add_selfie_out_columns();
DROP PROCEDURE IF EXISTS hris_add_selfie_out_columns;
