# Rajiman Living

Aplikasi manajemen kos untuk **rajimanliving.com**, dibangun dengan **PHP 8 + MySQL**
agar bisa dijalankan langsung di hosting cPanel Rumahweb.

> **Status: tahap 1 dari 4.**
> Yang sudah jalan: pemasangan, login dengan peran, dashboard, serta pengelolaan
> properti, kamar, dan penghuni. Tagihan, pembayaran, portal penyewa, PDF, email,
> dan perjanjian menyusul pada tahap berikutnya.

---

## Susunan folder

```
rajiman-app/
├─ .cpanel.yml            Pemasangan otomatis dari GitHub ke public_html
├─ config/
│  └─ config.contoh.php   Contoh pengaturan — salin, jangan diubah di tempat
├─ database/
│  └─ skema.sql           Seluruh tabel MySQL
└─ public/                Isi folder ini yang disalin ke public_html
   ├─ index.html          Halaman depan untuk calon penyewa
   ├─ assets/logo.png
   ├─ unggah/             Berkas unggahan (KTP, bukti bayar) — skrip dimatikan
   └─ app/                Aplikasi
      ├─ pasang.php       Pemasangan sekali jalan
      ├─ masuk.php        Halaman masuk
      ├─ keluar.php
      ├─ index.php        Dashboard pengelola
      ├─ properti.php     Daftar, tambah, ubah, hapus properti
      ├─ kamar.php        Daftar, tambah, ubah, hapus kamar
      ├─ penghuni.php     Daftar, tambah, ubah, tandai keluar
      ├─ assets/app.css
      └─ lib/             db, util, auth, layout
```

Berkas `public/app/config.php` **tidak ikut masuk GitHub** (dicegah `.gitignore`)
karena memuat kata sandi database.

---

## Pemasangan di Rumahweb

### 1. Buat database di cPanel
**cPanel → MySQL Databases**

1. *Create New Database* — misalnya `rajiman`
   Nama jadinya otomatis diawali username cPanel, contoh `rajb8852_rajiman`
2. *Add New User* — buat pengguna dan kata sandi, catat keduanya
3. *Add User To Database* — pilih pengguna dan database tadi, centang **ALL PRIVILEGES**

### 2. Unggah berkas
Salin seluruh isi folder `public/` ke `public_html`, sehingga di sana ada
`index.html`, `assets/`, `unggah/`, dan `app/`.

Bisa lewat File Manager, atau otomatis dari GitHub (lihat bagian bawah).

### 3. Buat config.php
Salin `config/config.contoh.php` menjadi `public_html/app/config.php`,
lalu isi empat baris ini dengan data dari langkah 1:

```php
'db_host' => 'localhost',
'db_nama' => 'rajb8852_rajiman',
'db_user' => 'rajb8852_rajiman',
'db_pass' => 'kata-sandi-database',
```

Ganti juga `kunci_rahasia` dengan teks acak panjang.

### 4. Jalankan pemasangan
Buka `https://rajimanliving.com/app/pasang.php`, isi nama dan akun pemilik,
lalu klik **Pasang sekarang**. Tabel dibuat otomatis.

**Setelah berhasil, hapus `app/pasang.php` dari server.**

### 5. Masuk
`https://rajimanliving.com/app/masuk.php`

---

## Pemasangan otomatis dari GitHub (opsional)

cPanel bisa menarik kode dari GitHub dan menyalinnya sendiri ke `public_html`.

1. **cPanel → Git Version Control → Create**
2. Isi URL repo, misalnya `https://github.com/rajimanliving/rajiman-app.git`
3. Setelah kode masuk, klik **Manage → Pull or Deploy → Update from Remote**,
   lalu **Deploy HEAD Commit**

Berkas `.cpanel.yml` mengatur agar isi `public/` disalin ke `public_html`.
Periksa dulu baris `TUJUAN` di dalamnya — harus cocok dengan username cPanel Anda
(sekarang tertulis `/home/rajb8852/public_html`).

Karena `config.php` tidak ikut di repo, berkas itu tidak akan tertimpa saat deploy.

---

## Peran pengguna

| Peran | Akses |
|---|---|
| **pemilik** | Semua, termasuk laba dan pengaturan |
| **pengelola** | Operasional harian; angka laba disembunyikan |
| **penyewa** | Hanya portal miliknya sendiri |

---

## Keamanan yang sudah dipasang

- Kata sandi disimpan sebagai hash bcrypt, tidak pernah disimpan apa adanya
- Semua kueri memakai *prepared statement*, aman dari SQL injection
- Semua keluaran dilewatkan `h()`, aman dari XSS
- Setiap formulir dilindungi token CSRF
- Percobaan masuk dibatasi 8 kali gagal per 15 menit per alamat IP
- Sesi berakhir sendiri setelah 4 jam menganggur
- Folder `unggah/` tidak boleh menjalankan skrip

---

## Memindahkan data lama dari Google Sheets

Belum otomatis. Rencananya lewat ekspor CSV per sheet, lalu diimpor dengan
phpMyAdmin. Nama kolom di `database/skema.sql` sengaja dibuat sama dengan
nama kolom di sheet lama agar pemetaannya mudah.

Aplikasi Apps Script lama **jangan dihapus dulu** sampai pemindahan selesai
dan sudah diperiksa.
