<?php

class Config
{
    // Timezone perusahaan (dipakai untuk tanggal absensi & export)
    const TIMEZONE = 'Asia/Jakarta';

    // --- Absensi ---
    // Kebijakan frekuensi absensi: true = satu record absensi per karyawan per hari.
    // Ubah konfigurasi ini jika kebijakan perusahaan berubah (check-in/out, dll).
    const ONE_ATTENDANCE_PER_DAY = true;
    const MAX_SELFIE_BYTES = 2097152; // 2 MB
    const ALLOWED_SELFIE_MIME = array('image/jpeg', 'image/png', 'image/webp');
    const GPS_TIMEOUT_SECONDS = 15;
    const RATE_LIMIT_ATTENDANCE_SECONDS = 30; // jeda minimum antar request absensi

    // --- Cuti ---
    // Aturan hitung jumlah hari: 'calendar' = hari kalender (termasuk akhir pekan).
    // 'kerja' = hari kerja Senin-Jumat (belum termasuk hari libur nasional).
    const LEAVE_DAY_COUNT_RULE = 'calendar';
    const LEAVE_ENTITLEMENT_DEFAULT = 12; // jatah awal tahunan (hari), dibuat otomatis bila belum ada
    const LEAVE_TYPE_DEFAULT = 'annual';
    const PREVENT_LEAVE_OVERLAP = true; // cegah tanggal cuti bertabrakan dengan pengajuan approved
    const ALLOW_PAST_START_DATE = false;
    const RATE_LIMIT_LEAVE_SECONDS = 10;

    // --- Export laporan ---
    const MAX_EXPORT_RANGE_DAYS = 31; // batas maksimum rentang tanggal export

    // --- Penyimpanan foto ---
    const SELFIE_STORAGE_DIR = __DIR__ . '/../../storage/selfies';

    public static function companyNow()
    {
        return new DateTime('now', new DateTimeZone(self::TIMEZONE));
    }

    public static function companyToday()
    {
        return self::companyNow()->format('Y-m-d');
    }
}
