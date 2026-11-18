<?php
/** Halaman masuk. */

declare(strict_types=1);

require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/util.php';
require __DIR__ . '/lib/auth.php';

date_default_timezone_set(konfig('zona'));
mulaiSesi();

if (!sudahDipasang()) {
    pergi('pasang.php');
}
if (sudahMasuk()) {
    pergi(peran() === 'penyewa' ? 'portal.php' : 'index.php');
}

$galat = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    periksaCsrf();
    $galat = cobaMasuk(post('username', 30), (string)($_POST['sandi'] ?? '')) ?? '';
    if ($galat === '') {
        pergi(peran() === 'penyewa' ? 'portal.php' : 'index.php');
    }
}
$k = kabar();
?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#12383c">
<meta name="robots" content="noindex, nofollow">
<title>Masuk — Rajiman Living</title>
<link rel="icon" href="../assets/logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="masuk">
  <div class="masuk-kiri">
    <div class="blob"></div>
    <div class="in">
      <img src="../assets/logo.png" alt="">
      <h2>RAJIMAN LIVING</h2>
      <p>Cozy Living and Safety Space</p>
    </div>
  </div>

  <div class="masuk-kanan">
    <div class="masuk-kotak">
      <a class="kembali" href="../">
        <svg viewBox="0 0 24 24"><path d="M19 12H5m0 0 5-5m-5 5 5 5"/></svg>Kembali ke Beranda
      </a>
      <h1>Masuk</h1>
      <div class="garis"></div>
      <p class="sub">Untuk penghuni, pengelola, dan pemilik.</p>

      <?php if ($k): ?><div class="kabar <?= h($k['jenis']) ?>"><?= h($k['teks']) ?></div><?php endif; ?>
      <?php if ($galat): ?><div class="kabar galat"><?= h($galat) ?></div><?php endif; ?>

      <form method="post" class="form" autocomplete="on">
        <?= kolomCsrf() ?>
        <div>
          <label for="username">Username</label>
          <input id="username" name="username" type="text" required maxlength="30"
                 autocapitalize="none" spellcheck="false" autocomplete="username"
                 value="<?= h($_POST['username'] ?? '') ?>" autofocus>
        </div>
        <div>
          <label for="sandi">Password</label>
          <input id="sandi" name="sandi" type="password" required autocomplete="current-password">
        </div>
        <button class="btn utama" type="submit">Lanjutkan</button>
      </form>

      <p class="bantuan" style="margin-top:18px">
        Penyewa baru? <a href="../#hunian" style="color:var(--deep);font-weight:700;text-decoration:underline">Lihat kamar yang tersedia</a>
      </p>
    </div>
  </div>
</div>
</body>
</html>
