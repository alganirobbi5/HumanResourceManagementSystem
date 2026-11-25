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
| POST | `/src/api/attendances.php` | Kirim absensi (JSON) |

Field POST absensi:

| Field | Keterangan |
|---|---|
| `latitude` | wajib, desimal |
| `longitude` | wajib, desimal |
| `gps_accuracy` | opsional (meter) |
| `request_id` | opsional, pencegah duplikasi |

Foto selfie tidak dikirim ke server dan disimpan permanen di penyimpanan privat aplikasi HP.
Server hanya menerima koordinat, akurasi GPS, dan waktu.

Tanggal & waktu resmi dibuat oleh server (timezone perusahaan di `src/common/Config.php`).
Identitas karyawan diambil dari sesi/token, bukan dari input client.

| Method | Endpoint | Fungsi |
|---|---|---|
| GET | `/src/api/selfie.php?id={attendance_id}` | Foto selfie historis (pemilik atau HR) |

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

## Absensi Admin HR (self-service)

Endpoint ini khusus akun **Admin HR legacy** (tabel `admins`). Identitas diambil dari token,
bukan dari payload. Admin HR **tidak memiliki employee_id** dan absensinya disimpan di tabel
terpisah (`admin_attendances`), bukan `attendances`.

Autentikasi: `Authorization: Bearer <token>` + role `hr` + relasi `users.admin_id` yang valid.
Response mengikuti envelope standar `{ success, message, data }`.

| Method | Endpoint | Fungsi |
|---|---|---|
| GET | `/src/api/status_absen_admin.php` | Status absensi admin yang login. Opsional `?date=YYYY-MM-DD` |
| POST | `/src/api/absen_admin.php` | Absen masuk (JSON) |
| POST | `/src/api/absen_pulang_admin.php` | Absen pulang (JSON) |
| GET | `/src/api/laporan_absensi_admin.php` | Laporan absensi admin yang login (dipakai Flutter) |

Field JSON (absen masuk dan pulang):

| Field | Wajib | Keterangan |
|---|---|---|
| `latitude` | ya | desimal, rentang -90..90 |
| `longitude` | ya | desimal, rentang -180..180 |
| `waktu_lokal` | ya (pulang) | `YYYY-MM-DD HH:MM:SS` |

Field opsional absen masuk: `gps_accuracy`, `request_id`.
Foto selfie disimpan lokal di HP dan tidak dikirim ke server.

Contoh request (Flutter, JSON):

```dart
final uri = Uri.parse('${ApiConfig.baseUrl}/absen_admin.php');
final response = await http.post(
  uri,
  headers: ApiConfig.authHeaders(token),
  body: jsonEncode(<String, dynamic>{
    'latitude': latitude.toString(),
    'longitude': longitude.toString(),
    'gps_accuracy': accuracy.toStringAsFixed(2),
  }),
);
```

Response sukses (check-in / check-out):

```json
{
  "success": true,
  "message": "Absen masuk Admin HR berhasil disimpan",
  "data": {
    "tanggal": "2026-11-20",
    "sudah_masuk": true,
    "jam_masuk": "08:00:00",
    "sudah_pulang": false,
    "jam_pulang": null
  }
}
```

Response status:

```json
{
  "success": true,
  "message": "OK",
  "data": {
    "tanggal": "2026-11-20",
    "sudah_masuk": true,
    "jam_masuk": "08:00:00",
    "sudah_pulang": true,
    "jam_pulang": "17:00:00"
  }
}
```

Error:

| HTTP | Penyebab |
|---|---|
| `401` | token tidak ada / invalid |
| `403` | bukan role HR atau tidak punya relasi `admin_id` |
| `422` | koordinat tidak valid, di luar rentang, atau di luar geofence |
| `409` | sudah check-in / check-out pada tanggal tersebut |
| `429` | terlalu sering mengirim absensi |
| `500` | gagal menyimpan (transaksi digulung) |

Catatan migrasi: jalankan `DB/migrations/20261120_admin_api_identity.sql` sebelum memakai
endpoint ini. Migrasi menambah `users.admin_id` (UNIQUE) dan tabel `admin_attendances`
(UNIQUE `(admin_id, tanggal)`). Tidak ada employee record yang dibuat untuk admin.

### Laporan absensi admin

`GET /src/api/laporan_absensi_admin.php` mengembalikan hanya baris `admin_attendances`
milik admin yang login. Filter: `?date=YYYY-MM-DD` atau `?start_date=...&end_date=...`.

```json
{
  "success": true,
  "message": "Daftar absensi Admin HR",
  "data": [
    {
      "id": 9,
      "record_type": "admin",
      "admin_id": 1,
      "nama_lengkap": "Admin HR",
      "nama": "Admin HR",
      "tanggal": "2026-11-25",
      "date": "2026-11-25",
      "jam_masuk": "08:01:00",
      "clock_in": "08:01:00",
      "jam_keluar": "17:05:00",
      "clock_out": "17:05:00",
      "status": "recorded",
      "selfie_in_url": "selfie.php?id=9&type=in&scope=admin",
      "selfie_out_url": "selfie.php?id=9&type=out&scope=admin"
    }
  ]
}
```

Catatan: `laporan_absensi.php` juga menyertakan data admin, tetapi dengan field `nama`
(bukan `nama_lengkap`). Endpoint ini disediakan agar Flutter dapat menggabungkan laporan
dengan deduplikasi berdasarkan `id`.

## Menjalankan migrasi

```bash
mysql -uroot hrm < DB/hrm_prd_migration.sql
```

Tidak ada akun seed bawaan. Buat akun produksi melalui admin/system administrator.
