-- Migrasi idempotent: pengajuan izin keluar karyawan (tidak memotong kuota cuti).
CREATE TABLE IF NOT EXISTS `izin_keluar` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `employee_id` INT(11) NOT NULL,
  `tanggal` DATE NOT NULL,
  `jam_keluar` TIME NOT NULL,
  `jam_kembali` TIME NULL DEFAULT NULL,
  `kembali_ke_kantor` TINYINT(1) NOT NULL DEFAULT 1,
  `kategori` VARCHAR(100) NOT NULL DEFAULT 'Keperluan Pribadi',
  `alasan` TEXT NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `approved_by` INT(11) NULL DEFAULT NULL,
  `catatan_hr` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_izin_keluar_employee` (`employee_id`),
  KEY `idx_izin_keluar_status` (`status`),
  KEY `idx_izin_keluar_tanggal` (`tanggal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
