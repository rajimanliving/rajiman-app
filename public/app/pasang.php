<?php
/**
 * Pemasangan sekali jalan: membuat tabel dan akun pemilik pertama.
 * Setelah selesai, berkas ini mengunci dirinya sendiri lewat tabel pengaturan.
 * Hapus berkas ini dari server bila sudah tidak diperlukan.
 */

declare(strict_types=1);

require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/util.php';
require __DIR__ . '/lib/auth.php';

date_default_timezone_set(konfig('zona'));
mulaiSesi();

$galat = '';
$sudah = sudahDipasang();

// Bila sudah ada akun, tolak akses.
if ($sudah && (int)nilai('SELECT COUNT(*) FROM pengguna', [], 0) > 0) {
    http_response_code(403);
    ?><!doctype html><html lang="id"><head><meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sudah terpasang</title><link rel="stylesheet" href="assets/app.css"></head>
    <body><div class="pasang"><div class="kartu">
      <h2>Aplikasi sudah terpasang</h2>
      <p class="sub">Pemasangan hanya bisa dijalankan sekali. Demi keamanan, hapus berkas
         <code>app/pasang.php</code> dari server lewat File Manager cPanel.</p>
      <a class="btn utama" href="masuk.php">Ke halaman masuk</a>
    </div></div></body></html><?php
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();
    $username = strtolower(post('username', 30));
    $nama     = post('nama', 80);
    $email    = post('email', 120);
    $sandi    = (string)($_POST['sandi'] ?? '');
    $sandi2   = (string)($_POST['sandi2'] ?? '');

    if ($nama === '')                              $galat = 'Nama lengkap wajib diisi.';
    elseif ($e = usernameSalah($username))         $galat = $e;
    elseif ($e = sandiLemah($sandi))               $galat = $e;
    elseif ($sandi !== $sandi2)                    $galat = 'Ulangi password tidak sama.';
    elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $galat = 'Email tidak sah.';

    if ($galat === '') {
        try {
            // 1. Buat tabel.
            $sql = file_get_contents(__DIR__ . '/../../database/skema.sql');
            if ($sql === false) {
                $sql = file_get_contents(__DIR__ . '/skema.sql');   // bila berkas disalin ke app/
            }
            if ($sql === false) {
                throw new RuntimeException('Berkas database/skema.sql tidak ditemukan.');
            }
            // Buang baris komentar dulu. Bila tidak, potongan perintah yang
            // diawali komentar ikut terbuang dan tabelnya tidak pernah dibuat.
            $sql = preg_replace('/^\s*--.*$/m', '', $sql);
            $dibuat = 0;
            foreach (explode(';', $sql) as $perintah) {
                $perintah = trim($perintah);
                if ($perintah === '') continue;
                db()->exec($perintah);
                $dibuat++;
            }
            if ($dibuat === 0) {
                throw new RuntimeException('Tidak ada perintah SQL yang dijalankan. Periksa isi database/skema.sql.');
            }

            // 2. Buat akun pemilik.
            sisip('pengguna', [
                'username' => $username,
                'nama'     => $nama,
                'peran'    => 'pemilik',
                'hash'     => password_hash($sandi, PASSWORD_DEFAULT),
                'email'    => $email !== '' ? $email : null,
                'aktif'    => 1,
                'dibuat'   => sekarang(),
            ]);

            // 3. Pengaturan awal.
            foreach ([
                'nama_kos'              => konfig('nama_app'),
                'hari_jatuh_tempo'      => '5',
                'reminder_hari_sebelum' => '3',
                'daftar_publik'         => 'Ya',
                'dipasang_pada'         => sekarang(),
            ] as $k => $v) {
                q('INSERT INTO pengaturan (kunci, nilai) VALUES (?, ?)', [$k, $v]);
            }

            kabar('Pemasangan selesai. Silakan masuk dengan akun yang baru dibuat.');
            pergi('masuk.php');
        } catch (Throwable $e) {
            error_log('Pemasangan gagal: ' . $e->getMessage());
            $galat = 'Pemasangan gagal: ' . $e->getMessage();
        }
    }
}
?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Pemasangan — Rajiman Living</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="pasang">
  <div class="kartu">
    <h2>Pemasangan Rajiman Living</h2>
    <p class="sub">Langkah terakhir. Buat akun pemilik, lalu tabel database dibuat otomatis.</p>

    <div class="langkah"><i>&#10003;</i><div><b>Koneksi database</b><br>
      <span style="color:var(--mut)">Berhasil terhubung ke <code><?= h(konfig('db_nama')) ?></code>.</span></div></div>
    <div class="langkah"><i<?= $sudah ? '' : ' class="x"' ?>><?= $sudah ? '&#10003;' : '!' ?></i><div><b>Tabel</b><br>
      <span style="color:var(--mut)"><?= $sudah ? 'Tabel sudah ada, akan dipakai ulang.' : 'Belum ada, akan dibuat sekarang.' ?></span></div></div>
  </div>

  <?php if ($galat): ?><div class="kabar galat" style="margin-top:16px"><?= h($galat) ?></div><?php endif; ?>

  <div class="kartu" style="margin-top:16px">
    <h2>Akun pemilik</h2>
    <p class="sub">Akun ini punya akses penuh, termasuk laporan keuangan dan pengaturan.</p>
    <form method="post" class="form" autocomplete="off">
      <?= kolomCsrf() ?>
      <div><label for="nama">Nama lengkap</label>
        <input id="nama" name="nama" type="text" required maxlength="80" value="<?= h($_POST['nama'] ?? '') ?>"></div>
      <div class="baris">
        <div><label for="username">Username</label>
          <input id="username" name="username" type="text" required maxlength="30" autocapitalize="none"
                 spellcheck="false" value="<?= h($_POST['username'] ?? '') ?>">
          <p class="bantuan">Huruf kecil, angka, titik, strip, garis bawah.</p></div>
        <div><label for="email">Email <span class="opsional">(untuk atur ulang password)</span></label>
          <input id="email" name="email" type="email" maxlength="120" value="<?= h($_POST['email'] ?? '') ?>"></div>
      </div>
      <div class="baris">
        <div><label for="sandi">Password</label>
          <input id="sandi" name="sandi" type="password" required autocomplete="new-password">
          <p class="bantuan">Minimal 8 karakter, memuat huruf dan angka.</p></div>
        <div><label for="sandi2">Ulangi password</label>
          <input id="sandi2" name="sandi2" type="password" required autocomplete="new-password"></div>
      </div>
      <button class="btn utama" type="submit">Pasang sekarang</button>
    </form>
  </div>

  <p class="bantuan" style="margin-top:14px">Setelah pemasangan selesai, hapus berkas <code>app/pasang.php</code> dari server.</p>
</div>
</body>
</html>
