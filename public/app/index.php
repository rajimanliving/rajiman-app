<?php
/** Dashboard pengelola: ringkasan bulan berjalan. */

declare(strict_types=1);

require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/util.php';
require __DIR__ . '/lib/auth.php';

date_default_timezone_set(konfig('zona'));
mulaiSesi();
if (!sudahDipasang()) pergi('pasang.php');
wajibKelola();
require __DIR__ . '/lib/layout.php';

$periode = sekarang('Y-m');
$lalu    = date('Y-m', strtotime($periode . '-01 -1 month'));

// Kamar dan hunian
$totalKamar = (int)nilai('SELECT COUNT(*) FROM kamar', [], 0);
$terisi     = (int)nilai("SELECT COUNT(*) FROM kamar WHERE status = 'Terisi'", [], 0);
$okupansi   = $totalKamar > 0 ? round($terisi / $totalKamar * 100, 1) : 0.0;
$penyewa    = (int)nilai("SELECT COUNT(*) FROM penghuni WHERE status = 'Aktif'", [], 0);
$jmlProp    = (int)nilai('SELECT COUNT(*) FROM properti', [], 0);

// Uang masuk dan keluar
$masuk = (float)nilai(
    'SELECT COALESCE(SUM(b.jumlah),0) FROM pembayaran b
     JOIN tagihan t ON t.id_tagihan = b.id_tagihan
     WHERE t.periode = ?', [$periode], 0);
$keluar = (float)nilai(
    'SELECT COALESCE(SUM(jumlah),0) FROM pengeluaran WHERE tanggal LIKE ?', [$periode . '%'], 0);
$laba = $masuk - $keluar;

$masukLalu = (float)nilai(
    'SELECT COALESCE(SUM(b.jumlah),0) FROM pembayaran b
     JOIN tagihan t ON t.id_tagihan = b.id_tagihan
     WHERE t.periode = ?', [$lalu], 0);
$keluarLalu = (float)nilai(
    'SELECT COALESCE(SUM(jumlah),0) FROM pengeluaran WHERE tanggal LIKE ?', [$lalu . '%'], 0);
$labaLalu = $masukLalu - $keluarLalu;

// Tunggakan: tagihan belum lunas yang sudah lewat jatuh tempo
$tunggakan = semua(
    "SELECT t.id_tagihan, t.periode, t.jumlah, t.jatuh_tempo, p.nama, k.nama AS kamar,
            COALESCE((SELECT SUM(b.jumlah) FROM pembayaran b WHERE b.id_tagihan = t.id_tagihan),0) AS dibayar
     FROM tagihan t
     JOIN penghuni p ON p.id_penghuni = t.id_penghuni
     LEFT JOIN kamar k ON k.id_kamar = p.id_kamar
     WHERE t.status = 'Belum Lunas' AND t.jatuh_tempo < ?
     ORDER BY t.jatuh_tempo ASC", [hariIni()]);
$totalTunggak = 0.0;
foreach ($tunggakan as $t) {
    $totalTunggak += (float)$t['jumlah'] - (float)$t['dibayar'];
}

// Kamar kosong
$kamarKosong = (int)nilai("SELECT COUNT(*) FROM kamar WHERE status = 'Kosong'", [], 0);
$belumVerif  = (int)nilai("SELECT COUNT(*) FROM pendaftaran WHERE status = 'Baru'", [], 0);

$ringkas = $kamarKosong > 0
    ? $kamarKosong . ' kamar masih kosong'
    : 'Semua kamar terisi';
$ringkas .= $totalTunggak > 0 ? ' dan ada tunggakan yang perlu ditagih.' : ' dan tidak ada tunggakan.';

$ikon = fn(string $d) => '<svg viewBox="0 0 24 24"><path d="' . $d . '"/></svg>';

tataLetakAtas('Dashboard', 'index.php');
judulHalaman('Dashboard', ucfirst(strftime_id()) . ' · ' . $ringkas);
?>

<div class="grid g4" style="margin-bottom:16px">
  <div class="kpi utama">
    <div class="tt"><span class="ti"><?= $ikon('M3 17l6-6 4 4 7-7M14 8h7v7') ?></span><small><?= pemilik() ? 'Laba' : 'Pemasukan' ?> <?= h(periodeTeks($periode)) ?></small></div>
    <div class="v"><?= h(rp(pemilik() ? $laba : $masuk)) ?></div>
    <span class="s">Masuk <?= h(rp($masuk)) ?> · Keluar <?= h(rp($keluar)) ?><?= pemilik() ? ' · ' . h(periodeTeks($lalu)) . ': ' . h(rp($labaLalu)) : '' ?></span>
  </div>

  <div class="kpi">
    <div class="tt"><span class="ti"><?= $ikon('M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4 21c1-4 4-6 8-6s7 2 8 6') ?></span><small>Penyewa aktif</small></div>
    <div class="v"><?= $penyewa ?><u>orang</u></div>
    <span class="s">di <?= $jmlProp ?: 1 ?> properti</span>
  </div>

  <div class="kpi">
    <div class="tt"><span class="ti"><?= $ikon('M3 18v-5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v5M6 11V8a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v3') ?></span><small>Tingkat hunian</small></div>
    <div class="v"><?= h((string)$okupansi) ?><u>%</u></div>
    <span class="s"><?= $terisi ?> dari <?= $totalKamar ?> kamar terisi</span>
  </div>

  <div class="kpi">
    <div class="tt"><span class="ti"><?= $ikon('M12 8v5l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z') ?></span><small>Tunggakan</small>
      <span class="lenca <?= $tunggakan ? 'bad' : 'ok' ?>" style="margin-left:auto"><?= $tunggakan ? count($tunggakan) . ' tagihan' : 'Aman' ?></span></div>
    <div class="v"><?= h(rp($totalTunggak)) ?></div>
    <span class="s"><?= $tunggakan ? 'Lewat jatuh tempo, perlu ditagih' : 'Semua tagihan aman' ?></span>
  </div>
</div>

<?php if ($totalKamar === 0): ?>
<div class="kartu">
  <h2>Mulai dari sini</h2>
  <p class="sub">Database masih kosong. Tiga langkah berikut membuat aplikasi siap dipakai.</p>
  <div class="langkah"><i>1</i><div><b>Tambah properti</b> — nama kos, alamat, dan rekening pembayaran.</div></div>
  <div class="langkah"><i>2</i><div><b>Tambah kamar</b> — nomor kamar, tipe, harga sewa, dan deposit.</div></div>
  <div class="langkah"><i>3</i><div><b>Tambah penghuni</b> — data penyewa dan tanggal masuk.</div></div>
  <p style="margin-top:16px"><a class="btn utama" href="properti.php?aksi=baru">Tambah properti pertama</a></p>
</div>
<?php else: ?>

<div class="grid g2">
  <div class="kartu">
    <h2>Perlu dikerjakan</h2>
    <p class="sub">Pekerjaan rutin, yang paling mendesak di depan.</p>
    <div class="langkah"><i<?= $tunggakan ? ' class="x"' : '' ?>><?= $tunggakan ? '!' : '&#10003;' ?></i>
      <div><b>Tagih tunggakan</b><br><span style="color:var(--mut)">
        <?= $tunggakan ? count($tunggakan) . ' tagihan lewat jatuh tempo' : 'Tidak ada tunggakan' ?></span></div></div>
    <div class="langkah"><i<?= $belumVerif ? ' class="x"' : '' ?>><?= $belumVerif ? '!' : '&#10003;' ?></i>
      <div><b>Verifikasi pendaftaran</b><br><span style="color:var(--mut)">
        <?= $belumVerif ? $belumVerif . ' pendaftaran menunggu' : 'Tidak ada antrean' ?></span></div></div>
    <div class="langkah"><i><?= $kamarKosong ? $kamarKosong : '&#10003;' ?></i>
      <div><b>Kamar kosong</b><br><span style="color:var(--mut)">
        <?= $kamarKosong ? $kamarKosong . ' kamar siap disewakan' : 'Semua kamar terisi' ?></span></div></div>
  </div>

  <div class="kartu">
    <h2>Tunggakan</h2>
    <p class="sub">Tagihan yang sudah lewat tanggal jatuh tempo.</p>
    <?php if (!$tunggakan): ?>
      <div class="kosong">Semua tagihan aman. Tidak ada yang menunggak.</div>
    <?php else: ?>
      <div class="tabel-bungkus">
        <table>
          <thead><tr><th>Penyewa</th><th>Periode</th><th class="kanan">Sisa</th><th>Jatuh tempo</th></tr></thead>
          <tbody>
          <?php foreach (array_slice($tunggakan, 0, 6) as $t): ?>
            <tr>
              <td><b><?= h($t['nama']) ?></b><?= $t['kamar'] ? '<br><small style="color:var(--mut)">Kamar ' . h($t['kamar']) . '</small>' : '' ?></td>
              <td><?= h(periodeTeks($t['periode'])) ?></td>
              <td class="kanan"><b><?= h(rp((float)$t['jumlah'] - (float)$t['dibayar'])) ?></b></td>
              <td><?= h(tglPendek($t['jatuh_tempo'])) ?><br>
                  <small style="color:var(--bad)"><?= abs(selisihHari($t['jatuh_tempo'], hariIni())) ?> hari lewat</small></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php
tataLetakBawah();

/** Tanggal hari ini dalam bahasa Indonesia, contoh: "Rabu, 18 November 2026". */
function strftime_id(): string
{
    $hari  = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    return $hari[(int)sekarang('w')] . ', ' . tglPanjang(hariIni());
}
