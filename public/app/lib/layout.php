<?php
/** Kerangka halaman: kepala, menu samping, dan penutup. */

declare(strict_types=1);

const MENU_KELOLA = [
    ['index.php',      'Dashboard',  'M3 10l9-7 9 7v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z'],
    ['properti.php',   'Properti',   'M4 21V8l8-5 8 5v13M9 21v-6h6v6'],
    ['kamar.php',      'Kamar',      'M3 18v-5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v5M6 11V8a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v3'],
    ['penghuni.php',   'Penghuni',   'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4 21c1-4 4-6 8-6s7 2 8 6'],
    ['tagihan.php',    'Tagihan',    'M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2zM9 8h6M9 12h6'],
    ['pembayaran.php', 'Pembayaran', 'M3 7h18v10H3zM3 11h18M7 15h3'],
];

function tataLetakAtas(string $judul, string $halamanAktif = ''): void
{
    $u = saya();
    $nama = $u['nama'] ?? '';
    $pr   = $u['peran'] ?? '';
    ?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#12383c">
<meta name="robots" content="noindex, nofollow">
<title><?= h($judul) ?> — <?= h(konfig('nama_app')) ?></title>
<link rel="icon" href="../assets/logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/app.css?v=1">
</head>
<body>
<aside class="sisi">
  <a class="merek" href="index.php">
    <span class="mk"><img src="../assets/logo.png" alt=""></span>
    <span>Rajiman <b>Living</b></span>
  </a>
  <nav>
    <?php foreach (MENU_KELOLA as [$url, $label, $ikon]): ?>
      <a href="<?= h($url) ?>"<?= $halamanAktif === $url ? ' class="on"' : '' ?>>
        <svg viewBox="0 0 24 24"><path d="<?= h($ikon) ?>"/></svg><?= h($label) ?>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="akun">
    <div class="av"><?= h(mb_strtoupper(mb_substr($nama, 0, 1))) ?></div>
    <div class="akun-t"><b><?= h($nama) ?></b><small><?= h(ucfirst($pr)) ?></small></div>
    <a class="keluar" href="keluar.php" title="Keluar">
      <svg viewBox="0 0 24 24"><path d="M15 17l5-5-5-5M20 12H9M12 20H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h6"/></svg>
    </a>
  </div>
</aside>

<main class="isi">
<?php
    $k = kabar();
    if ($k): ?>
  <div class="kabar <?= h($k['jenis']) ?>"><?= h($k['teks']) ?></div>
<?php endif;
}

function tataLetakBawah(): void
{
    ?>
</main>
<script>
document.addEventListener('click', function (e) {
  var t = e.target.closest('[data-konfirmasi]');
  if (t && !confirm(t.dataset.konfirmasi)) { e.preventDefault(); e.stopPropagation(); }
}, true);
</script>
</body>
</html>
<?php
}

/** Kepala halaman dengan judul dan tombol aksi di kanan. */
function judulHalaman(string $judul, string $sub = '', string $aksiHtml = ''): void
{
    ?>
  <header class="kepala">
    <div><h1><?= h($judul) ?></h1><?= $sub ? '<p>' . h($sub) . '</p>' : '' ?></div>
    <?= $aksiHtml ? '<div class="kepala-aksi">' . $aksiHtml . '</div>' : '' ?>
  </header>
<?php
}
