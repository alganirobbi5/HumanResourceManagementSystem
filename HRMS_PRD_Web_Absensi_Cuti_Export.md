# PRODUCT REQUIREMENTS DOCUMENT (PRD)
# Human Resource Information System (HRIS)
## Web Application — Fitur Absensi, Cuti Karyawan, dan Export Laporan

**Versi:** 1.0  
**Tanggal:** 10 November 2026  
**Status:** Dokumen kebutuhan pengembangan  
**Platform tahap ini:** Web Based  
**Platform tahap berikutnya:** Mobile Application menggunakan Flutter  
**Prinsip arsitektur:** Web dan aplikasi mobile akan menggunakan backend/API serta database yang sama.

---

## 1. Ringkasan Proyek

Human Resource Information System (HRIS) adalah aplikasi untuk membantu perusahaan mengelola aktivitas dan administrasi sumber daya manusia. Pengembangan dilakukan bertahap. Tahap pertama berfokus pada aplikasi web, khususnya tiga fitur yang telah ditentukan:

1. **Absensi Karyawan** — karyawan melakukan absensi dengan verifikasi izin GPS dan kamera, mengambil lokasi serta foto selfie, lalu sistem menyimpan data absensi.
2. **Pengajuan Cuti Karyawan** — karyawan mengajukan cuti melalui formulir. Sistem memvalidasi kelengkapan dan sisa kuota, kemudian HR/atasan menyetujui atau menolak pengajuan.
3. **Export Laporan Absensi** — HR memilih rentang tanggal dan mengunduh laporan Excel yang memiliki satu sheet untuk setiap tanggal dalam rentang tersebut.

Sistem harus dirancang agar nantinya aplikasi Flutter dapat menggunakan layanan backend dan database yang sama. Karena itu, proses bisnis tidak boleh hanya ditanam di frontend web. Validasi, otorisasi, penyimpanan, dan aturan bisnis harus berada di backend melalui API yang dapat dipakai oleh web maupun mobile.

### 1.1 Tujuan Produk

- Mempermudah pencatatan absensi karyawan.
- Menyediakan proses pengajuan dan persetujuan cuti yang tercatat.
- Mempermudah HR mengunduh laporan absensi berdasarkan periode.
- Menjaga data web dan mobile tetap konsisten melalui satu sumber data.
- Menyediakan fondasi yang aman dan dapat dikembangkan untuk aplikasi Flutter.

### 1.2 Ruang Lingkup Tahap Ini

**Termasuk:**
- Implementasi tiga fitur sesuai flowchart yang diberikan.
- Halaman web, komponen UI, formulir, tabel, status, validasi, dan notifikasi di dalam aplikasi.
- Integrasi backend dan database yang dibutuhkan untuk ketiga fitur.
- Role/permission minimum agar karyawan dan HR/atasan memiliki akses yang sesuai.
- API yang dirancang agar dapat digunakan Flutter pada tahap berikutnya.
- Pengujian alur utama dan kondisi gagal.

**Tidak termasuk pada tahap ini:**
- Pengembangan aplikasi Flutter.
- Payroll/penggajian.
- Rekrutmen, penilaian kinerja, manajemen kontrak, dan modul HR lain yang belum ditentukan.
- Integrasi otomatis dengan perangkat fingerprint atau sistem absensi pihak ketiga.
- Fitur tambahan yang tidak tercantum dalam tiga flowchart, kecuali kebutuhan teknis minimum agar fitur dapat berjalan.

---

## 2. Referensi Desain dan Flowchart

Gambar berikut merupakan referensi yang diberikan. Flowchart menjadi acuan utama alur bisnis. Screenshot dashboard menjadi referensi tampilan umum, bukan instruksi untuk menyalin seluruh menu atau data contoh.

### 2.1 Referensi dashboard

![Referensi dashboard HRIS](hrms_prd_assets/dashboard-reference.png)

**Catatan:** tampilan dashboard pada gambar menunjukkan pola admin dashboard dengan sidebar gelap, area konten terang, ringkasan statistik, dan grafik. Gunakan sebagai referensi visual awal. Jangan menganggap angka, label, menu, atau data pada screenshot sebagai data produksi.

### 2.2 Flowchart fitur Absensi

![Flowchart Absensi](hrms_prd_assets/flow-absensi.png)

### 2.3 Flowchart fitur Cuti

![Flowchart Cuti](hrms_prd_assets/flow-cuti.png)

### 2.4 Flowchart fitur Export Laporan

![Flowchart Export Laporan](hrms_prd_assets/flow-export.png)

---

## 3. Pengguna, Role, dan Hak Akses

Sistem minimal memiliki dua kelompok pengguna.

### 3.1 Karyawan

Karyawan dapat:
- Melihat halaman absensi.
- Melakukan absensi dengan memberikan izin GPS dan kamera.
- Mengambil foto selfie sebagai bukti absensi.
- Melihat hasil/status absensi miliknya.
- Melihat sisa kuota cuti miliknya.
- Membuat pengajuan cuti.
- Melihat status pengajuan cuti miliknya.
- Melihat alasan penolakan apabila pengajuan ditolak.

Karyawan tidak dapat:
- Mengubah data absensi yang sudah tersimpan.
- Menyetujui atau menolak pengajuan cuti.
- Melihat data pribadi/absensi/cuti karyawan lain.
- Mengunduh laporan seluruh karyawan.

### 3.2 HR / Atasan

HR/atasan dapat:
- Melihat daftar absensi karyawan sesuai cakupan kewenangannya.
- Meninjau pengajuan cuti yang menunggu persetujuan.
- Menyetujui atau menolak pengajuan cuti.
- Mengisi alasan penolakan.
- Melihat status dan riwayat pengajuan.
- Membuka menu laporan absensi.
- Memilih rentang tanggal dan mengunduh Excel.

**Catatan otorisasi:** jika HR dan atasan nantinya merupakan role berbeda, backend harus mendukung cakupan persetujuan berdasarkan struktur organisasi atau penempatan karyawan. Untuk tahap awal, keduanya dapat menggunakan permission yang sama jika memang sesuai kebijakan perusahaan.

---

## 4. Navigasi dan Struktur Halaman Web

Gunakan pola dashboard pada referensi dengan sidebar dan area konten utama. Struktur menu minimum yang diperlukan:

- **Dashboard** — halaman ringkasan yang sudah ada atau menjadi halaman awal aplikasi.
- **Absensi**
  - Absensi Saya (akses karyawan).
  - Data Absensi (akses HR/atasan).
- **Cuti**
  - Pengajuan Cuti (akses karyawan).
  - Persetujuan Cuti (akses HR/atasan).
  - Riwayat Cuti (sesuai hak akses).
- **Laporan**
  - Laporan Absensi / Export (akses HR/atasan).

Menu boleh disesuaikan dengan struktur aplikasi yang sudah tersedia, tetapi ketiga fitur wajib dapat ditemukan dengan jelas. Jangan membuat menu yang mengarah ke halaman kosong atau fitur yang belum termasuk scope.

---

# 5. Kebutuhan Fitur 1 — Absensi Karyawan

## 5.1 Deskripsi

Fitur absensi memungkinkan karyawan mencatat kehadiran melalui aplikasi web. Sesuai flowchart, sistem meminta izin akses GPS dan kamera. Jika izin diberikan, sistem mengambil koordinat lokasi, karyawan mengambil foto selfie, lalu sistem mencatat waktu dan tanggal serta menyimpan lokasi, foto, waktu, dan tanggal ke database.

Jika izin GPS atau kamera tidak diberikan, sistem menampilkan pesan error dan proses absensi dibatalkan.

## 5.2 Alur Utama (mengikuti flowchart)

1. Karyawan login ke aplikasi.
2. Karyawan membuka menu **Absensi**.
3. Sistem meminta izin akses GPS/lokasi dan kamera.
4. Sistem memeriksa apakah izin yang diperlukan diberikan.
5. Jika izin tidak diberikan:
   - Sistem menampilkan pesan bahwa izin lokasi dan/atau kamera diperlukan.
   - Sistem tidak mengirim absensi sebagai absensi berhasil.
   - Proses berakhir/dibatalkan.
6. Jika izin diberikan:
   - Sistem mengambil lokasi GPS (latitude dan longitude).
   - Sistem menampilkan fitur pengambilan foto selfie.
7. Karyawan mengambil foto selfie.
8. Sistem mencatat waktu dan tanggal absensi menggunakan waktu server.
9. Sistem menyimpan user/karyawan, lokasi, foto, waktu, dan tanggal ke database.
10. Sistem menampilkan notifikasi bahwa absensi berhasil.

## 5.3 Kebutuhan UI Halaman Absensi

Halaman karyawan setidaknya menampilkan:
- Judul halaman **Absensi**.
- Identitas singkat karyawan yang sedang login.
- Tanggal dan waktu saat ini.
- Status izin lokasi dan kamera.
- Tombol **Mulai Absensi** atau **Ambil Absensi**.
- Area informasi lokasi (latitude/longitude atau alamat hasil reverse geocoding jika tersedia).
- Preview kamera.
- Tombol **Ambil Foto** / **Ulangi Foto**.
- Preview foto yang akan dikirim.
- Tombol **Kirim Absensi** setelah persyaratan terpenuhi.
- Indikator proses saat mengambil lokasi/mengunggah foto.
- Pesan berhasil atau gagal.
- Ringkasan absensi hari ini dan riwayat absensi milik karyawan.

Tombol pengiriman harus dinonaktifkan sebelum izin yang diwajibkan diberikan, lokasi berhasil diperoleh, dan foto selfie tersedia.

## 5.4 Aturan Bisnis dan Validasi

- Pengguna harus sudah login.
- Absensi hanya boleh dibuat untuk akun karyawan yang sedang login; identitas karyawan tidak boleh diterima begitu saja dari input frontend.
- Browser harus meminta izin lokasi dan kamera melalui mekanisme permission browser.
- Jika permission ditolak, tampilkan instruksi singkat untuk mengaktifkannya melalui pengaturan browser, lalu izinkan pengguna mencoba kembali.
- Jika GPS tidak tersedia, timeout, atau koordinat tidak valid, tampilkan error dan jangan menyimpan absensi berhasil.
- Jika kamera tidak tersedia atau foto gagal diambil, tampilkan error dan jangan lanjutkan proses.
- Foto harus memiliki tipe file gambar yang diizinkan dan ukuran maksimum yang ditentukan konfigurasi server.
- Timestamp resmi dibuat oleh server, bukan mempercayai jam perangkat pengguna.
- Simpan latitude dan longitude sebagai angka desimal dengan presisi memadai.
- Simpan foto di object/file storage privat; database hanya menyimpan path/key dan metadata file, bukan data gambar base64 berukuran besar.
- File foto hanya dapat diakses oleh pemilik yang berwenang.
- Sistem harus mencegah pengiriman ganda yang tidak disengaja. Kebijakan satu kali absensi per hari atau check-in/check-out harus dikonfirmasi kepada pemilik proses sebelum diterapkan.
- Sistem tidak boleh mengubah koordinat atau waktu dari request tanpa validasi.
- Karena flowchart hanya menyebut satu proses absensi, fitur check-out belum menjadi kewajiban tahap ini.

## 5.5 Data yang Disimpan

| Field | Keterangan |
|---|---|
| attendance_id | ID unik absensi |
| employee_id | Relasi ke karyawan |
| attendance_date | Tanggal absensi berdasarkan zona waktu perusahaan |
| attendance_time | Waktu absensi dari server |
| latitude | Koordinat lintang |
| longitude | Koordinat bujur |
| selfie_path | Lokasi file selfie di storage |
| selfie_mime_type | Jenis file foto |
| selfie_size | Ukuran file |
| status | Status absensi, minimal recorded/success |
| created_at | Waktu data dibuat |
| updated_at | Waktu data diperbarui |

Opsional untuk audit:
- accuracy GPS (meter).
- device/browser metadata yang tidak invasif.
- request identifier untuk mencegah duplikasi.

## 5.6 Kondisi Error

| Kondisi | Respons sistem |
|---|---|
| Izin lokasi ditolak | Tampilkan pesan izin lokasi diperlukan; batalkan proses |
| Izin kamera ditolak | Tampilkan pesan izin kamera diperlukan; batalkan proses |
| GPS tidak tersedia | Tampilkan pesan lokasi tidak dapat diperoleh |
| GPS timeout | Tampilkan opsi coba lagi |
| Kamera gagal dibuka | Tampilkan pesan kamera tidak dapat diakses |
| Foto belum diambil | Tombol kirim tetap nonaktif |
| Upload foto gagal | Jangan buat record absensi berhasil; izinkan coba lagi |
| Koneksi terputus | Tampilkan kegagalan dan cegah duplikasi saat retry |
| Pengguna belum login | Arahkan ke halaman login |

## 5.7 Kriteria Penerimaan (Acceptance Criteria)

- [ ] Karyawan yang login dapat membuka halaman absensi.
- [ ] Sistem meminta akses lokasi dan kamera saat proses absensi dimulai.
- [ ] Jika izin yang diperlukan ditolak, proses dibatalkan dan pesan error ditampilkan.
- [ ] Jika izin diberikan, sistem berhasil mendapatkan latitude dan longitude.
- [ ] Karyawan dapat mengambil dan melihat preview selfie.
- [ ] Karyawan dapat mengulang pengambilan foto sebelum mengirim.
- [ ] Absensi hanya dikirim ketika lokasi dan foto tersedia.
- [ ] Waktu/tanggal yang tersimpan berasal dari server.
- [ ] Database menyimpan relasi karyawan, tanggal, waktu, lokasi, dan referensi foto.
- [ ] Sistem menampilkan notifikasi berhasil setelah penyimpanan benar-benar berhasil.
- [ ] Karyawan hanya dapat melihat data absensinya sendiri.
- [ ] Kegagalan upload tidak meninggalkan record absensi sukses yang tidak lengkap.

---

# 6. Kebutuhan Fitur 2 — Pengajuan Cuti Karyawan

## 6.1 Deskripsi

Fitur cuti memungkinkan karyawan mengajukan cuti dengan mengisi alasan cuti, jumlah hari, tanggal mulai, tanggal selesai, dan nama karyawan pengganti. Sistem memvalidasi kelengkapan formulir serta kecukupan kuota cuti. Jika valid, pengajuan disimpan dengan status **Menunggu Persetujuan** dan notifikasi dikirim ke HR/atasan.

HR/atasan meninjau pengajuan dan memilih menyetujui atau menolak. Jika disetujui, sistem mengubah status menjadi **Disetujui** dan mengurangi kuota cuti karyawan. Jika ditolak, sistem mengubah status menjadi **Ditolak**, menyimpan alasan penolakan, dan mengirim notifikasi kepada karyawan.

## 6.2 Alur Karyawan (mengikuti flowchart)

1. Karyawan membuka menu **Pengajuan Cuti**.
2. Karyawan mengisi:
   - Alasan cuti.
   - Jumlah hari.
   - Tanggal mulai.
   - Tanggal selesai.
   - Nama pengganti.
3. Sistem memvalidasi kelengkapan form dan sisa kuota cuti.
4. Jika form tidak lengkap atau kuota tidak mencukupi:
   - Sistem menampilkan pesan error.
   - Karyawan memperbaiki form atau tanggal/jumlah hari.
   - Sistem mengulangi validasi.
5. Jika form lengkap dan kuota mencukupi:
   - Sistem menyimpan pengajuan dengan status **Menunggu Persetujuan**.
   - Sistem mengirim notifikasi ke HR/atasan.
6. Karyawan dapat melihat status pengajuannya.

## 6.3 Alur HR/Atasan (mengikuti flowchart)

1. HR/atasan membuka daftar pengajuan cuti yang menunggu persetujuan.
2. HR/atasan memilih dan meninjau detail pengajuan.
3. HR/atasan menentukan apakah pengajuan disetujui.
4. Jika disetujui:
   - Sistem mengubah status menjadi **Disetujui**.
   - Sistem mengurangi kuota cuti karyawan sesuai jumlah hari yang disetujui.
   - Sistem mengirim notifikasi kepada karyawan dan karyawan pengganti.
5. Jika ditolak:
   - HR/atasan mengisi alasan penolakan.
   - Sistem mengubah status menjadi **Ditolak**.
   - Sistem mengirim notifikasi beserta alasan penolakan kepada karyawan.
6. Pengajuan berakhir dengan status final Disetujui atau Ditolak.

## 6.4 Kebutuhan UI — Form Pengajuan Cuti

Field wajib:
- **Alasan Cuti** — textarea.
- **Tanggal Mulai** — date picker.
- **Tanggal Selesai** — date picker.
- **Jumlah Hari** — angka; sistem sebaiknya menghitung otomatis berdasarkan tanggal dan kebijakan hari kerja.
- **Nama Pengganti** — pilihan karyawan atau input yang terhubung ke data karyawan.

Informasi yang ditampilkan:
- Sisa kuota cuti saat ini.
- Jumlah hari yang akan diajukan.
- Ringkasan tanggal pengajuan.
- Status pengajuan sebelumnya.
- Tombol **Ajukan Cuti**.

Jumlah hari sebaiknya dihitung sistem agar tidak mudah dimanipulasi. Jika perusahaan memiliki aturan khusus (hari kalender, hari kerja, hari libur nasional, atau cuti bersama), perhitungan harus mengikuti kebijakan yang dikonfirmasi perusahaan. Sampai kebijakan tersebut ditetapkan, tampilkan hasil hitung yang konsisten dan jadikan aturan perhitungan sebagai konfigurasi bisnis, bukan angka yang tersebar di frontend.

## 6.5 Kebutuhan UI — Persetujuan Cuti

Halaman HR/atasan menampilkan tabel pengajuan dengan kolom minimal:
- Nama karyawan.
- Departemen/jabatan (jika tersedia).
- Tanggal mulai.
- Tanggal selesai.
- Jumlah hari.
- Alasan cuti.
- Nama pengganti.
- Tanggal pengajuan.
- Status.

Detail pengajuan menyediakan:
- Informasi lengkap karyawan.
- Sisa kuota cuti sebelum keputusan.
- Rentang cuti dan jumlah hari.
- Nama pengganti.
- Tombol **Setujui**.
- Tombol **Tolak**.
- Field **Alasan Penolakan** yang wajib diisi saat menolak.

Sediakan dialog konfirmasi sebelum menyetujui atau menolak agar keputusan tidak terjadi karena salah klik.

## 6.6 Aturan Bisnis dan Validasi

- Hanya karyawan terautentikasi yang dapat membuat pengajuan untuk dirinya sendiri.
- Semua field wajib pada flowchart harus diisi.
- Tanggal selesai tidak boleh lebih awal daripada tanggal mulai.
- Tanggal mulai tidak boleh melanggar kebijakan perusahaan (misalnya tanggal lampau), jika kebijakan tersebut diberlakukan.
- Jumlah hari harus positif dan sesuai hasil perhitungan sistem.
- Sisa kuota cuti harus mencukupi saat pengajuan dibuat.
- Pengajuan yang valid disimpan dengan status `pending` / **Menunggu Persetujuan**.
- Pengajuan pending belum mengurangi kuota cuti final.
- Kuota hanya dikurangi ketika status berubah menjadi Disetujui.
- Pengurangan kuota dan perubahan status harus dilakukan dalam satu transaksi database agar tidak terjadi status disetujui tanpa pengurangan kuota atau sebaliknya.
- Backend harus memvalidasi ulang kuota ketika HR/atasan menyetujui. Hal ini mencegah dua pengajuan bersamaan menyebabkan kuota negatif.
- Hanya HR/atasan dengan permission yang sesuai yang dapat menyetujui/menolak.
- Pengajuan yang sudah final tidak boleh diproses kembali tanpa mekanisme pembatalan/perubahan yang ditetapkan.
- Alasan penolakan wajib diisi ketika HR/atasan memilih Tolak.
- Keputusan persetujuan harus mencatat approver dan waktu keputusan.
- Notifikasi tidak boleh dianggap terkirim jika proses pengiriman gagal; simpan status pengiriman untuk retry atau tampilkan kegagalan sesuai mekanisme aplikasi.
- Nama pengganti sebaiknya dipilih dari daftar karyawan aktif agar relasinya valid.
- Cegah tanggal cuti yang bertabrakan dengan pengajuan lain yang sudah disetujui, sesuai kebijakan perusahaan.

## 6.7 Status Pengajuan

| Status internal | Label UI | Makna |
|---|---|---|
| pending | Menunggu Persetujuan | Pengajuan tersimpan dan menunggu HR/atasan |
| approved | Disetujui | Pengajuan disetujui dan kuota dikurangi |
| rejected | Ditolak | Pengajuan ditolak dan alasan penolakan tersedia |

## 6.8 Data yang Disimpan

| Field | Keterangan |
|---|---|
| leave_request_id | ID unik pengajuan |
| employee_id | Karyawan pemohon |
| reason | Alasan cuti |
| start_date | Tanggal mulai |
| end_date | Tanggal selesai |
| total_days | Jumlah hari hasil perhitungan |
| replacement_employee_id | Relasi karyawan pengganti |
| status | pending / approved / rejected |
| submitted_at | Waktu pengajuan |
| reviewed_by | ID HR/atasan yang memutuskan |
| reviewed_at | Waktu keputusan |
| rejection_reason | Alasan penolakan, wajib jika rejected |
| created_at | Waktu data dibuat |
| updated_at | Waktu data diperbarui |

Data kuota cuti disimpan pada entitas/saldo cuti karyawan, misalnya:
- employee_id
- leave_type (jika jenis cuti digunakan)
- entitlement_year
- entitled_days
- used_days
- remaining_days atau dihitung dari entitlement - used

## 6.9 Notifikasi

Minimal notifikasi dalam aplikasi:
- HR/atasan menerima pemberitahuan pengajuan cuti baru.
- Karyawan menerima pemberitahuan pengajuan disetujui.
- Karyawan pengganti menerima pemberitahuan ketika cuti disetujui.
- Karyawan menerima pemberitahuan penolakan beserta alasan.

Jika email/push notification belum dikonfigurasi, sistem tetap harus menyimpan notifikasi in-app. Pengiriman email/push dapat ditambahkan tanpa mengubah alur status inti.

## 6.10 Kriteria Penerimaan (Acceptance Criteria)

- [ ] Karyawan dapat membuka form pengajuan cuti.
- [ ] Form menyediakan alasan, jumlah hari, tanggal mulai, tanggal selesai, dan nama pengganti.
- [ ] Sistem menampilkan sisa kuota cuti karyawan.
- [ ] Form tidak dapat dikirim jika field wajib belum lengkap.
- [ ] Sistem menolak rentang tanggal yang tidak valid.
- [ ] Sistem menolak pengajuan jika kuota tidak mencukupi.
- [ ] Pengajuan valid tersimpan dengan status Menunggu Persetujuan.
- [ ] HR/atasan mendapat notifikasi pengajuan baru.
- [ ] HR/atasan dapat melihat detail pengajuan pending.
- [ ] HR/atasan dapat menyetujui pengajuan.
- [ ] Saat disetujui, status berubah dan kuota berkurang tepat satu kali.
- [ ] HR/atasan dapat menolak pengajuan.
- [ ] Penolakan tidak mengurangi kuota.
- [ ] Penolakan wajib memiliki alasan.
- [ ] Karyawan mendapat notifikasi hasil keputusan.
- [ ] Karyawan pengganti mendapat notifikasi ketika pengajuan disetujui.
- [ ] Karyawan tidak dapat melihat atau memutuskan pengajuan milik karyawan lain.
- [ ] Keputusan dan perubahan saldo tercatat dengan approver serta timestamp.

---

# 7. Kebutuhan Fitur 3 — Export Laporan Absensi

## 7.1 Deskripsi

Fitur Export Laporan Absensi digunakan HR untuk mengunduh data absensi dalam format Excel berdasarkan rentang tanggal. Sesuai flowchart, HR membuka menu Laporan Absensi, memilih tanggal mulai dan tanggal akhir, sistem memvalidasi rentang tanggal, kemudian HR menekan tombol Export.

Sistem mengambil data absensi sesuai rentang tanggal, membuat file Excel, membuat satu sheet untuk setiap tanggal, memberi nama sheet sesuai tanggal, lalu menyediakan file untuk diunduh.

## 7.2 Alur Utama (mengikuti flowchart)

1. HR membuka menu **Laporan Absensi**.
2. HR memilih tanggal mulai dan tanggal akhir.
3. Sistem memvalidasi rentang tanggal.
4. Sistem memeriksa apakah tanggal akhir lebih besar atau sama dengan tanggal mulai.
5. Jika tidak valid:
   - Sistem menampilkan pesan error.
   - HR memilih ulang rentang tanggal.
   - Sistem kembali ke langkah pemilihan tanggal.
6. Jika valid:
   - HR menekan tombol **Export**.
   - Sistem mengambil data absensi sesuai rentang tanggal.
   - Sistem membuat file Excel.
   - Sistem membuat satu sheet untuk setiap tanggal pada rentang yang dipilih.
   - Nama setiap sheet mengikuti tanggal terkait.
   - Jumlah sheet mengikuti jumlah hari pada rentang tanggal.
7. Sistem menyediakan file Excel untuk diunduh.
8. HR mengunduh file Excel.

## 7.3 Kebutuhan UI Halaman Export

Halaman menampilkan:
- Judul **Laporan Absensi**.
- Date picker **Tanggal Mulai**.
- Date picker **Tanggal Akhir**.
- Tombol **Export Excel**.
- Keterangan periode yang dipilih.
- Indikator loading ketika data sedang diproses.
- Pesan error jika tanggal tidak valid atau export gagal.
- Notifikasi sukses dan file unduhan setelah file selesai dibuat.

Tombol Export tidak boleh mengirim request jika tanggal belum lengkap atau tanggal akhir lebih awal dari tanggal mulai.

## 7.4 Aturan Pembuatan Excel

- Format file: `.xlsx`.
- File memuat satu worksheet untuk setiap tanggal kalender dalam rentang yang dipilih, termasuk tanggal yang tidak memiliki absensi.
- Nama worksheet menggunakan format tanggal yang konsisten, misalnya `2026-11-10`. Pastikan nama sheet memenuhi batasan Excel dan unik.
- Setiap sheet berisi data absensi pada tanggal tersebut.
- Kolom minimum:
  - No.
  - ID/NIK karyawan (jika tersedia dan boleh ditampilkan).
  - Nama karyawan.
  - Departemen/jabatan (jika tersedia).
  - Tanggal.
  - Jam absensi.
  - Latitude.
  - Longitude.
  - Status.
- Foto selfie tidak perlu ditanamkan ke file Excel pada versi awal; cukup simpan referensi/path jika memang diperlukan dan aman untuk dibagikan.
- Jika tidak ada data pada suatu tanggal, sheet tetap dibuat dan menampilkan informasi **Tidak ada data absensi**.
- File memiliki nama yang informatif, misalnya `laporan-absensi-2026-11-01_sampai_2026-11-10.xlsx`.
- Sistem harus membatasi rentang tanggal maksimum sesuai konfigurasi untuk menghindari proses export terlalu berat.
- Data harus mengikuti timezone perusahaan.
- Export harus menggunakan data yang dapat diakses oleh role HR/atasan tersebut.
- Data harus diambil dari database, bukan data dummy atau hardcoded.

## 7.5 Validasi dan Error Handling

| Kondisi | Respons sistem |
|---|---|
| Tanggal mulai kosong | Minta HR memilih tanggal mulai |
| Tanggal akhir kosong | Minta HR memilih tanggal akhir |
| Tanggal akhir sebelum tanggal mulai | Tampilkan pesan error dan minta pilih ulang |
| Rentang terlalu panjang | Tampilkan batas periode yang diizinkan |
| Tidak ada data absensi | Buat workbook dengan sheet per tanggal dan pesan tidak ada data |
| Gagal mengambil data | Tampilkan pesan gagal dan opsi mencoba kembali |
| Gagal membuat Excel | Tampilkan pesan export gagal |
| Sesi login habis | Minta HR login kembali |
| Tidak memiliki izin | Tolak request export |

## 7.6 Kriteria Penerimaan (Acceptance Criteria)

- [ ] HR/atasan yang memiliki akses dapat membuka halaman Laporan Absensi.
- [ ] HR dapat memilih tanggal mulai dan tanggal akhir.
- [ ] Sistem memvalidasi kedua tanggal wajib terisi.
- [ ] Sistem menerima tanggal akhir yang sama dengan tanggal mulai.
- [ ] Sistem menolak tanggal akhir yang lebih awal daripada tanggal mulai.
- [ ] Tombol Export menjalankan proses hanya untuk rentang yang valid.
- [ ] Sistem mengambil data absensi sesuai rentang tanggal dari database.
- [ ] File yang dihasilkan berformat `.xlsx`.
- [ ] Workbook memiliki satu sheet untuk setiap tanggal kalender dalam rentang tersebut.
- [ ] Nama sheet mengikuti tanggal masing-masing.
- [ ] Sheet tanpa data tetap tersedia dengan keterangan tidak ada data.
- [ ] Kolom laporan memuat informasi absensi minimum yang ditentukan.
- [ ] File dapat diunduh dan dibuka menggunakan aplikasi spreadsheet umum.
- [ ] Pengguna tanpa permission HR tidak dapat mengekspor data seluruh karyawan.

---

# 8. Arsitektur Web Sekarang dan Flutter Nanti

## 8.1 Prinsip Wajib: Satu Backend, Satu Database

Aplikasi web dan aplikasi Flutter **bukan dua sistem terpisah**. Keduanya merupakan dua client yang mengakses layanan backend dan database yang sama.

```text
                 ┌──────────────────────┐
                 │     Web HRIS         │
                 │  (Frontend Browser)  │
                 └──────────┬───────────┘
                            │ HTTPS / REST API
                            │
                 ┌──────────▼───────────┐
                 │   Backend / API     │
                 │ Auth, Rules, Access  │
                 │ Absensi, Cuti, Export│
                 └──────────┬───────────┘
                            │
                  ┌─────────▼─────────┐
                  │  Shared Database │
                  │ Employee, User,   │
                  │ Attendance, Leave│
                  └───────────────────┘
                            ▲
                            │ HTTPS / REST API
                 ┌──────────┴───────────┐
                 │  Flutter Mobile App │
                 │   (Tahap berikutnya)│
                 └──────────────────────┘
```

### Ketentuan arsitektur

- Web dan Flutter menggunakan backend/API yang sama.
- Web dan Flutter menggunakan database yang sama melalui backend.
- Flutter tidak membuat database terpisah.
- Aturan validasi dan bisnis berada di backend, bukan hanya di frontend.
- Identitas karyawan diambil dari sesi/token yang tervalidasi.
- API menggunakan autentikasi dan authorization.
- Upload selfie dilakukan melalui endpoint backend atau mekanisme signed upload yang aman.
- API mengembalikan format data konsisten agar dapat digunakan oleh kedua platform.
- Jangan membuat implementasi fitur yang hanya berjalan di browser tanpa endpoint backend yang dapat digunakan kembali.

### 8.2 Rekomendasi Stack

Stack final harus mengikuti teknologi yang telah ditetapkan oleh pemilik proyek atau repository yang sudah ada. Jika belum ada keputusan, rekomendasi:

| Layer | Rekomendasi |
|---|---|
| Web frontend | Next.js |
| Backend/API | Laravel atau backend API terpisah yang disepakati |
| Database | MySQL atau PostgreSQL |
| Mobile tahap berikutnya | Flutter |
| API | REST API melalui HTTPS |
| Authentication | Token/session yang mendukung web dan mobile |
| File selfie | Private object storage / file storage |
| Excel export | Library backend untuk membuat `.xlsx` |

**Penting:** jangan memasang atau mengganti framework, database, atau struktur proyek yang sudah ada tanpa memeriksa repository dan meminta konfirmasi bila perubahan berdampak besar.

---

# 9. Rancangan Entitas Database

Rancangan ini bersifat konseptual dan perlu disesuaikan dengan database proyek yang sudah ada. Hindari membuat tabel duplikat jika tabel sejenis sudah tersedia.

## 9.1 Tabel `users`

Menyimpan akun autentikasi.

- id
- name/username
- email
- password_hash (dikelola sistem autentikasi)
- role_id atau role
- employee_id (relasi ke karyawan, jika arsitektur memisahkan user dan employee)
- is_active
- created_at
- updated_at

## 9.2 Tabel `employees`

Menyimpan profil karyawan.

- id
- employee_number / NIK
- full_name
- department_id
- position_id
- employment_status
- manager_id (opsional)
- created_at
- updated_at

## 9.3 Tabel `attendances`

Menyimpan absensi.

- id
- employee_id
- attendance_date
- attendance_time
- latitude
- longitude
- gps_accuracy (opsional)
- selfie_path
- selfie_mime_type
- selfie_size
- status
- created_at
- updated_at

Tambahkan index pada employee_id dan attendance_date. Terapkan unique constraint hanya setelah kebijakan frekuensi absensi (misalnya satu record per hari atau check-in/check-out) dipastikan.

## 9.4 Tabel `leave_balances`

Menyimpan saldo cuti karyawan.

- id
- employee_id
- leave_type (jika digunakan)
- entitlement_year
- entitled_days
- used_days
- created_at
- updated_at

Saldo tersisa dapat dihitung sebagai `entitled_days - used_days`, atau disimpan terpisah dengan mekanisme konsistensi yang jelas.

## 9.5 Tabel `leave_requests`

Menyimpan pengajuan cuti.

- id
- employee_id
- reason
- start_date
- end_date
- total_days
- replacement_employee_id
- status
- submitted_at
- reviewed_by
- reviewed_at
- rejection_reason
- created_at
- updated_at

## 9.6 Tabel `notifications`

Menyimpan notifikasi in-app.

- id
- recipient_user_id
- title
- message
- type
- related_entity_type
- related_entity_id
- read_at
- created_at

## 9.7 Tabel `audit_logs` (direkomendasikan)

Mencatat aktivitas penting:
- actor_user_id
- action
- entity_type
- entity_id
- before_data / after_data (sesuai kebijakan privasi)
- created_at

Minimal catat persetujuan/penolakan cuti dan perubahan saldo cuti.

---

# 10. Rancangan API untuk Web dan Flutter

Endpoint berikut adalah rancangan awal, bukan kontrak final. Sesuaikan prefix, format response, dan konvensi dengan backend yang sudah ada.

## 10.1 Authentication dan Profil

| Method | Endpoint | Fungsi |
|---|---|---|
| GET | `/api/me` | Mendapatkan user dan profil karyawan aktif |

Endpoint login/logout mengikuti sistem autentikasi proyek.

## 10.2 Absensi

| Method | Endpoint | Fungsi |
|---|---|---|
| GET | `/api/attendances/me` | Riwayat absensi karyawan login |
| POST | `/api/attendances` | Mengirim absensi dengan lokasi dan selfie |
| GET | `/api/attendances` | Daftar absensi untuk HR/atasan |
| GET | `/api/attendances/{id}` | Detail absensi sesuai permission |

Request `POST /api/attendances` dapat menggunakan multipart form-data:
- latitude
- longitude
- gps_accuracy (opsional)
- selfie (file gambar)

Tanggal dan waktu resmi dibuat backend. Jangan menerima employee_id dari client sebagai sumber identitas utama.

## 10.3 Cuti

| Method | Endpoint | Fungsi |
|---|---|---|
| GET | `/api/leave-balances/me` | Melihat saldo cuti karyawan |
| GET | `/api/leave-requests/me` | Riwayat pengajuan milik karyawan |
| POST | `/api/leave-requests` | Membuat pengajuan cuti |
| GET | `/api/leave-requests/pending` | Daftar pengajuan untuk approver |
| GET | `/api/leave-requests/{id}` | Detail pengajuan |
| POST | `/api/leave-requests/{id}/approve` | Menyetujui pengajuan |
| POST | `/api/leave-requests/{id}/reject` | Menolak pengajuan beserta alasan |

Request pengajuan minimal:
- reason
- start_date
- end_date
- replacement_employee_id

`total_days` idealnya dihitung backend. Jika frontend menampilkan perhitungan, backend tetap menghitung ulang.

Request penolakan minimal:
- rejection_reason

## 10.4 Export

| Method | Endpoint | Fungsi |
|---|---|---|
| GET | `/api/reports/attendances/export?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD` | Menghasilkan file Excel |

Response berhasil berupa file `.xlsx` dengan header download yang sesuai. Endpoint hanya dapat diakses role/permission yang berwenang.

---

# 11. Keamanan, Privasi, dan Integritas Data

- Seluruh endpoint privat harus membutuhkan autentikasi.
- Setiap endpoint harus memeriksa role dan cakupan akses.
- Karyawan hanya dapat mengakses data miliknya sendiri.
- HR/atasan hanya dapat mengakses data sesuai kewenangannya.
- Jangan menyimpan password dalam bentuk plaintext.
- Gunakan HTTPS pada deployment.
- Validasi file upload di backend: MIME type, ekstensi, ukuran, dan isi file.
- Simpan selfie dalam storage privat, bukan folder publik yang dapat diakses tanpa otorisasi.
- Jangan menaruh secret key database/storage di frontend.
- Hindari mengirim data sensitif yang tidak diperlukan ke browser.
- Gunakan database transaction untuk keputusan cuti dan pengurangan kuota.
- Gunakan audit log untuk aksi sensitif.
- Terapkan rate limiting pada endpoint login, absensi, dan upload.
- Validasi ulang seluruh input di backend walaupun frontend sudah memiliki validasi.
- Tentukan kebijakan retensi foto absensi dan data karyawan bersama pemilik sistem.

---

# 12. UX, UI, dan Responsivitas

## 12.1 Gaya Visual

Gunakan screenshot dashboard sebagai referensi struktur:
- Sidebar navigasi di sisi kiri.
- Topbar sederhana.
- Area konten utama terang dan rapi.
- Ringkasan informasi menggunakan cards.
- Tabel dengan header jelas.
- Grafik hanya jika memang menampilkan data nyata dan relevan.

Jangan menyalin angka statistik dari screenshot. Gunakan data database. Jika belum ada data, tampilkan empty state yang jujur, bukan angka dummy seolah data asli.

## 12.2 Konsistensi Komponen

- Gunakan komponen form, date picker, tabel, badge status, modal konfirmasi, toast, dan loading state yang konsisten.
- Status pending, approved, rejected harus mudah dibedakan.
- Tombol aksi harus memiliki label yang jelas.
- Berikan konfirmasi untuk aksi persetujuan/penolakan.
- Berikan pesan validasi dekat dengan field terkait.
- Sediakan empty state ketika belum ada data.
- Pastikan halaman dapat digunakan pada desktop dan tablet; layout mobile web tetap responsif meskipun aplikasi Flutter belum dibuat.

## 12.3 Aksesibilitas dan Browser

- Label setiap input harus jelas.
- Tombol dapat diakses melalui keyboard.
- Jangan hanya menggunakan warna untuk menyampaikan status.
- Minta izin GPS/kamera hanya saat fitur absensi digunakan.
- Jelaskan kepada pengguna mengapa lokasi dan kamera diperlukan.
- Fitur GPS/kamera browser umumnya membutuhkan secure context (HTTPS), kecuali localhost untuk pengembangan.

---

# 13. Non-Functional Requirements

| Area | Kebutuhan |
|---|---|
| Reliability | Data tidak boleh hilang ketika request gagal atau koneksi terputus |
| Performance | Tabel menggunakan pagination untuk data besar |
| Security | Autentikasi, authorization, validasi backend, dan penyimpanan privat |
| Maintainability | Komponen UI dan business logic terpisah |
| Scalability | API dapat digunakan web dan Flutter |
| Data integrity | Transaksi database untuk proses yang mengubah saldo cuti |
| Compatibility | Web berjalan pada browser modern |
| Timezone | Tanggal dan waktu mengikuti timezone perusahaan yang dikonfigurasi |
| Observability | Error penting dicatat tanpa membocorkan data sensitif |

---

# 14. Skenario Pengujian Minimum

## 14.1 Absensi

1. Login sebagai karyawan, izinkan GPS dan kamera, lakukan absensi sampai berhasil.
2. Tolak izin lokasi.
3. Tolak izin kamera.
4. Simulasikan GPS tidak tersedia.
5. Simulasikan kamera tidak tersedia.
6. Kirim tanpa foto.
7. Simulasikan upload gagal.
8. Coba mengirim request dengan employee_id karyawan lain.
9. Coba membuka riwayat absensi karyawan lain.
10. Uji pengiriman ganda sesuai aturan frekuensi absensi yang telah diputuskan.

## 14.2 Cuti

1. Ajukan cuti dengan seluruh field valid dan kuota cukup.
2. Kosongkan alasan cuti.
3. Kosongkan nama pengganti.
4. Pilih tanggal selesai sebelum tanggal mulai.
5. Ajukan cuti melebihi sisa kuota.
6. Ajukan cuti dengan jumlah hari yang tidak sesuai perhitungan tanggal.
7. Login sebagai HR dan setujui pengajuan.
8. Pastikan kuota berkurang tepat satu kali.
9. Tolak pengajuan tanpa alasan; sistem harus menolak.
10. Tolak pengajuan dengan alasan; pastikan karyawan menerima status dan alasan.
11. Pastikan pengajuan pending tidak mengurangi kuota.
12. Coba approve pengajuan yang sudah final.
13. Uji dua pengajuan yang disetujui hampir bersamaan agar saldo tidak menjadi negatif.

## 14.3 Export

1. Export satu tanggal.
2. Export beberapa tanggal.
3. Tanggal mulai sama dengan tanggal akhir.
4. Tanggal akhir lebih awal daripada tanggal mulai.
5. Tanggal tidak lengkap.
6. Rentang melebihi batas maksimum.
7. Rentang tanpa data absensi.
8. Pastikan jumlah sheet sesuai jumlah hari kalender.
9. Pastikan nama sheet sesuai tanggal.
10. Pastikan file dapat dibuka dan kolom data benar.
11. Login sebagai karyawan biasa dan coba akses endpoint export; harus ditolak.

---

# 15. Tahapan Implementasi yang Disarankan

### Tahap 0 — Pemeriksaan proyek

- Periksa repository, framework, database, autentikasi, dan struktur folder yang sudah ada.
- Identifikasi tabel user dan employee yang sudah tersedia.
- Jangan membuat ulang modul autentikasi atau tabel yang sudah ada.
- Konfirmasi kebijakan perusahaan yang belum ditentukan.

### Tahap 1 — Fondasi data dan akses

- Siapkan relasi user dan employee.
- Siapkan role/permission karyawan dan HR/atasan.
- Siapkan tabel absensi, saldo cuti, pengajuan cuti, notifikasi, dan audit log sesuai kebutuhan.
- Siapkan API dan validasi backend.

### Tahap 2 — Fitur Absensi

- Buat halaman absensi karyawan.
- Implementasikan permission GPS/kamera.
- Implementasikan pengambilan koordinat dan selfie.
- Implementasikan upload serta penyimpanan.
- Implementasikan riwayat dan validasi akses.
- Uji semua kondisi gagal.

### Tahap 3 — Fitur Cuti

- Buat halaman pengajuan cuti karyawan.
- Buat halaman riwayat cuti.
- Buat halaman persetujuan HR/atasan.
- Implementasikan validasi tanggal dan kuota.
- Implementasikan approval/rejection transaction.
- Implementasikan notifikasi dan audit log.
- Uji seluruh alur.

### Tahap 4 — Export Laporan

- Buat halaman pemilihan rentang tanggal.
- Implementasikan validasi rentang.
- Implementasikan query data absensi.
- Buat workbook dengan satu sheet per tanggal.
- Implementasikan download dan pengujian file.

### Tahap 5 — Pengujian dan penyelesaian web

- Jalankan pengujian role dan keamanan.
- Uji responsive layout.
- Uji integritas data dan error handling.
- Pastikan tidak ada data dummy yang tampil sebagai data nyata.
- Dokumentasikan endpoint API untuk digunakan Flutter pada tahap berikutnya.

### Tahap 6 — Flutter (di luar scope sekarang)

Setelah web dan API stabil, aplikasi Flutter dibangun sebagai client tambahan. Flutter menggunakan endpoint, autentikasi, aturan bisnis, dan database yang sama. Fitur absensi di Flutter dapat menggunakan plugin native untuk GPS dan kamera, tetapi tetap mengirim hasil ke backend yang sama.

---

# 16. Keputusan yang Perlu Dikonfirmasi Sebelum Implementasi

Beberapa detail belum ditentukan oleh flowchart. Jangan mengarang kebijakan perusahaan. Gunakan konfigurasi atau minta keputusan sebelum menetapkan:

1. **Pola absensi:** apakah hanya satu kali absensi per hari, atau ada check-in dan check-out?
2. **Batas lokasi:** apakah absensi hanya boleh dilakukan dalam radius tertentu dari kantor? Flowchart belum menyebut geofencing.
3. **Aturan cuti:** apakah jumlah hari dihitung berdasarkan hari kalender atau hari kerja? Bagaimana akhir pekan dan hari libur?
4. **Kuota cuti:** berapa jatah awal, periode reset, dan apakah ada jenis cuti berbeda?
5. **Approver:** apakah persetujuan dilakukan HR, atasan langsung, atau keduanya secara berurutan?
6. **Pengganti:** apakah wajib memilih karyawan aktif dari daftar perusahaan?
7. **Rentang export:** berapa rentang tanggal maksimum yang diizinkan?
8. **Timezone:** timezone perusahaan yang digunakan untuk pencatatan tanggal dan waktu.

Jika keputusan belum tersedia, implementasikan hanya aturan yang secara eksplisit ada di flowchart dan buat bagian yang belum pasti mudah dikonfigurasi.

---

# 17. Definition of Done

Fitur dianggap selesai apabila:

- Seluruh halaman web untuk tiga fitur tersedia dan dapat digunakan.
- Alur bisnis sesuai flowchart yang diberikan.
- Validasi frontend dan backend berjalan.
- Data tersimpan dan dibaca dari database, bukan hardcoded/dummy.
- Hak akses karyawan dan HR/atasan diterapkan.
- Absensi menyimpan koordinat, selfie, tanggal, dan waktu.
- Pengajuan cuti mengikuti proses pending, approve/reject, notifikasi, dan perubahan kuota yang benar.
- Export menghasilkan Excel dengan satu sheet per tanggal.
- API dapat dipakai oleh web dan siap digunakan Flutter nanti.
- Tidak ada database terpisah untuk web dan Flutter.
- Error state, loading state, empty state, dan success state tersedia.
- Skenario pengujian minimum lulus.
- Tidak ada secret atau kredensial sensitif di frontend/repository.

---

## 18. Instruksi Penting untuk Developer / AI Coding Agent

1. Baca PRD ini dan seluruh flowchart sebelum mengubah kode.
2. Periksa struktur repository dan teknologi yang sudah digunakan terlebih dahulu.
3. Implementasikan **web based saja** pada tahap ini. Jangan mulai membuat aplikasi Flutter.
4. Namun, desain backend dan API harus siap digunakan Flutter tanpa membuat ulang business logic.
5. Web dan Flutter wajib menggunakan backend serta database yang sama.
6. Ikuti alur tiga flowchart; jangan menghilangkan langkah validasi, error, approval, notifikasi, atau proses export.
7. Jangan menambahkan fitur besar di luar scope.
8. Jangan gunakan data dummy sebagai data produksi.
9. Jangan menganggap proses berhasil sebelum penyimpanan database atau pembuatan file benar-benar berhasil.
10. Jangan menaruh aturan bisnis penting hanya di frontend.
11. Terapkan keamanan dan hak akses pada backend.
12. Jika menemukan kebijakan yang belum ditentukan pada Bagian 16, jangan membuat asumsi permanen. Gunakan konfigurasi sementara yang jelas atau minta konfirmasi.
13. Setelah implementasi, jalankan test/lint/build yang tersedia dan perbaiki error yang muncul.
14. Pastikan perubahan tidak merusak fitur lama yang sudah ada.

**Prioritas utama:** implementasi yang benar-benar berfungsi, sesuai flowchart, aman, dan memiliki fondasi API/database bersama untuk aplikasi Flutter di tahap berikutnya.
