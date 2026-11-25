-- Migrasi: Menambahkan kolom fcm_token ke tabel users
-- Dibuat untuk mendukung Firebase Cloud Messaging (push notification)

-- - fcm_token disimpan dari Flutter setelah login / token refresh
-- - PushNotificationService mengirim notifikasi ke token ini
-- - Token dihapus saat logout / saat FCM melaporkan token tidak valid

ALTER TABLE users ADD COLUMN fcm_token VARCHAR(255) NULL AFTER api_token;
ALTER TABLE users ADD COLUMN fcm_token_updated_at TIMESTAMP NULL DEFAULT NULL AFTER fcm_token;

CREATE INDEX idx_users_fcm_token ON users(fcm_token);
