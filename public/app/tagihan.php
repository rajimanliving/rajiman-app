<?php
/** Tagihan — menyusul pada tahap berikutnya. */

declare(strict_types=1);

require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/util.php';
require __DIR__ . '/lib/auth.php';

date_default_timezone_set(konfig('zona'));
mulaiSesi();
if (!sudahDipasang()) pergi('pasang.php');
wajibKelola();
require __DIR__ . '/lib/layout.php';

tataLetakAtas('Tagihan', 'tagihan.php');
judulHalaman('Tagihan', 'Tagihan sewa, faktur, dan kuitansi');
?>
<div class="kartu">
  <div class="kosong" style="padding:48px 20px">
    <p style="font-size:17px;font-weight:700;color:var(--tx);margin:0 0 6px">Bagian ini sedang dibangun</p>
    <p style="margin:0">Tabel <code>tagihan</code> sudah ada di database dan siap diisi.<br>
       Halaman pengelolaannya menyusul pada tahap berikutnya.</p>
    <p style="margin:18px 0 0"><a class="btn utama" href="index.php">Kembali ke dashboard</a></p>
  </div>
</div>
<?php tataLetakBawah(); ?>
