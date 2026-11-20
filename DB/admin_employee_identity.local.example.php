<?php
// SALIN file ini menjadi: DB/admin_employee_identity.local.php
// Isi dengan data AKTUAL Admin HR dari sumber resmi (HR/Supervisor).
// Jangan mengisi nilai fiktif. Script migrasi akan menolak field kosong.
//
// Data yang tersedia otomatis dari tabel admins (TIDAK perlu diisi di sini):
//   user_name = admins.name
//   password  = admins.password
//
// Field yang WAJIB disediakan karena tidak tersedia di tabel admins:
return array(
    'first_name'     => '', // contoh: nama depan resmi admin
    'last_name'      => '', // contoh: nama belakang resmi admin
    'gender'         => '', // 'Male' atau 'Female' sesuai data resmi
    'email'          => '', // email resmi
    'department_id'  => 0,  // ID departemen yang benar-benar ada
    'designation_id' => 0,  // ID jabatan yang benar-benar ada
    'mobile_no'      => '', // nomor resmi
);
