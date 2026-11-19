-- Migrasi: Menambahkan kolom api_token ke tabel users
-- Dibuat untuk mendukung autentikasi Bearer Token pada API HRIS
-- File ini seharusnya dieksekusi di environment development/local sebelum deploy ke produksi

-- Catatan: 
-- - Kolom api_token digunakan oleh Auth::login() untuk menyimpan token kriptografis
-- - Kolom ini divalidasi oleh Auth::identify() melalui query: 
--   SELECT id FROM users WHERE api_token = ? AND is_active = 1
-- - Nilai token dihasilkan oleh: bin2hex(random_bytes(32))
-- - Token direset saat logout melalui: UPDATE users SET api_token = NULL WHERE id = ?

ALTER TABLE users ADD COLUMN api_token VARCHAR(64) NULL AFTER is_active;

-- Tambahkan index untuk mempercepat validasi token pada setiap request API
CREATE INDEX idx_users_api_token ON users(api_token);

-- Catatan penting: 
-- - Migration ini dirancang untuk database localhost (sesuai konfigurasi login.php)
-- - Jika dijalankan ke VPS (31.97.199.58), pastikan tabel users memiliki struktur yang kompatibel
-- - Jangan menjalankan migration ini langsung ke database produksi VPS tanpa verifikasi schema terlebih dahulu