# Dokumentasi API HRIS (Web & Flutter)

Semua endpoint berada di bawah `src/api/` dan menggunakan **satu backend serta satu database**
yang sama dengan halaman web. Autentikasi:

- **Web:** session cookie (otomatis saat request dari browser).
- **Mobile (Flutter, tahap berikutnya):** header `Authorization: Bearer <api_token>`.
  Token diterbitkan pada saat login (`users.api_token`) dan di-reset saat logout.

Semua endpoint privat mengembalikan JSON:

```json
{ "success": true,  "message": "...", "data": { } }
{ "success": false, "message": "..." }
```

Status HTTP: `200` OK, `401` belum login, `403` tidak berizin, `422` validasi, `429` rate limit, `500` gagal.

---

## Auth & Profil

| Method | Endpoint | Fungsi |
|---|---|---|
| POST | `/src/store/Login.php` | Login form (`userName`, `password`) → session + token |
| GET | `/src/store/Logout.php` | Logout (reset token & session) |
| GET | `/src/api/me.php` | User + profil karyawan yang sedang login |

## Absensi

| Method | Endpoint | Fungsi |
|---|---|---|
| GET | `/src/api/attendances.php?scope=me&page=1` | Riwayat absensi milik karyawan login |
| GET | `/src/api/attendances.php?scope=all&date=&start_date=&end_date=&keyword=&page=` | Daftar absensi (khusus role `hr`) |
| POST | `/src/api/attendances.php` | Kirim absensi (multipart form-data) |

Field POST absensi:

| Field | Keterangan |
|---|---|
| `latitude` | wajib, desimal |
| `longitude` | wajib, desimal |
| `gps_accuracy` | opsional (meter) |
| `request_id` | opsional, pencegah duplikasi |
| `selfie` | file gambar (jpg/png/webp, maks 2 MB) |

Tanggal & waktu resmi dibuat oleh server (timezone perusahaan di `src/common/Config.php`).
Identitas karyawan diambil dari sesi/token, bukan dari input client.

| Method | Endpoint | Fungsi |
|---|---|---|
| GET | `/src/api/selfie.php?id={attendance_id}` | Foto selfie (pemilik atau HR) |

## Cuti

| Method | Endpoint | Fungsi |
|---|---|---|
| GET | `/src/api/leave-balances.php` | Sisa kuota cuti karyawan login |
| GET | `/src/api/leave-requests.php?scope=me&page=` | Riwayat pengajuan milik karyawan |
| GET | `/src/api/leave-requests.php?scope=pending&page=` | Pengajuan menunggu persetujuan (khusus `hr`) |
| GET | `/src/api/leave-requests.php?scope=all&status=&keyword=&page=` | Seluruh pengajuan (khusus `hr`) |
| GET | `/src/api/leave-requests.php?scope=detail&id=` | Detail satu pengajuan |
| POST | `/src/api/leave-requests.php` | Aksi pengajuan (JSON body) |

Body POST:

```json
{ "action": "create", "reason": "...", "start_date": "YYYY-MM-DD", "end_date": "YYYY-MM-DD", "replacement_employee_id": 1 }
{ "action": "approve", "id": 1 }
{ "action": "reject",  "id": 1, "rejection_reason": "..." }
```

Aturan bisnis (backend): `total_days` dihitung ulang server, validasi kuota saat create,
validasi ulang kuota + transaksi database saat approve, tolak wajib beralasan,
kuota hanya berkurang pada approve, notifikasi in-app + audit log.

| Method | Endpoint | Fungsi |
|---|---|---|
| GET | `/src/api/employees.php` | Daftar karyawan aktif (untuk memilih pengganti) |

## Laporan / Export

| Method | Endpoint | Fungsi |
|---|---|---|
| GET | `/src/api/export-attendance.php?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD` | Unduh `.xlsx`, satu sheet per tanggal (khusus `hr`) |

Batas rentang: `Config::MAX_EXPORT_RANGE_DAYS`.

## Notifikasi

| Method | Endpoint | Fungsi |
|---|---|---|
| GET | `/src/api/notifications.php` | Daftar notifikasi + jumlah belum dibaca |
| POST | `/src/api/notifications.php` | `{ "action": "mark_read", "id": 1 }` (opsional `id`) |

---

## Halaman Web terkait

| Halaman | Role |
|---|---|
| `views/attendance/absensiSaya.php` | karyawan |
| `views/attendance/dataAbsensi.php` | hr |
| `views/leave/pengajuanCuti.php` | karyawan |
| `views/leave/persetujuanCuti.php` | hr |
| `views/leave/riwayatCuti.php` | karyawan (miliknya) / hr (seluruh) |
| `views/reports/laporanAbsensi.php` | hr |

## Menjalankan migrasi

```bash
mysql -uroot hrm < DB/hrm_prd_migration.sql
```

Akun seed: `budi` / `password123` (karyawan) dan `siti` / `password123` (HR) — ganti password di produksi.
