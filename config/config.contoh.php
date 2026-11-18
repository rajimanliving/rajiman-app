<?php
/**
 * Contoh berkas pengaturan.
 *
 * Cara pakai:
 *   1. Salin berkas ini menjadi  public/app/config.php
 *   2. Isi data database dari cPanel Rumahweb (menu "MySQL Databases")
 *   3. Jangan pernah mengunggah config.php yang sudah terisi ke GitHub
 *      (sudah dicegah lewat .gitignore)
 */

return [
    // ---- Database MySQL dari cPanel ----
    // Nama database dan pengguna di Rumahweb selalu diawali username cPanel,
    // contoh: rajb8852_rajiman
    'db_host' => 'localhost',
    'db_nama' => 'NAMA_DATABASE',
    'db_user' => 'PENGGUNA_DATABASE',
    'db_pass' => 'KATA_SANDI_DATABASE',

    // ---- Identitas aplikasi ----
    'nama_app'  => 'Rajiman Living',
    'zona'      => 'Asia/Jakarta',

    // Alamat dasar aplikasi, tanpa garis miring di akhir.
    'base_url'  => 'https://rajimanliving.com/app',

    // Email pengirim untuk tagihan dan pengingat.
    // Buat dulu di cPanel > Email Accounts agar tidak masuk folder spam.
    'email_dari'      => 'no-reply@rajimanliving.com',
    'email_dari_nama' => 'Rajiman Living',

    // ---- Keamanan ----
    // Ganti dengan teks acak panjang. Dipakai untuk menandatangani sesi.
    'kunci_rahasia' => 'GANTI_DENGAN_TEKS_ACAK_PANJANG_MINIMAL_32_KARAKTER',

    // Berapa menit sesi bertahan tanpa aktivitas.
    'sesi_menit' => 240,
];
